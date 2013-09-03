<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing calculator for  pricing  billing lines with customer price.
 *
 * @package  calculator
 * @since    0.5
 */
class Billrun_Calculator_CustomerPricing extends Billrun_Calculator {

	protected $pricingField = 'price_customer';
	static protected $type = "pricing";

	/**
	 *
	 * @var boolean is customer price vatable by default
	 */
	protected $vatable = true;

	/**
	 *
	 * @var int timestamp
	 */
	protected $billrun_lower_bound_timestamp;

	public function __construct($options = array()) {
		$options['autoload'] = false;
		parent::__construct($options);

		if (isset($options['calculator']['limit'])) {
			$this->limit = $options['calculator']['limit'];
		}
		if (isset($options['calculator']['vatable'])) {
			$this->vatable = $options['calculator']['vatable'];
		}
		if (isset($options['calculator']['months_limit'])) {
			$this->months_limit = $options['calculator']['months_limit'];
		}
		$this->billrun_lower_bound_timestamp = is_null($this->months_limit) ? 0 : strtotime($this->months_limit . " months ago");
		// set months limit
		$this->load();
	}

	protected function getLines() {
		$query = array();
		$query['type'] = array('$in' => array('ggsn', 'smpp', 'smsc', 'nsn', 'tap3', 'credit'));
		return $this->getQueuedLines($query);
	}

	/**
	 * execute the calculation process
	 * @TODO this function mighh  be a duplicate of  @see Billrun_Calculator::calc() do we really  need the diffrence  between Rate/Pricing?
	 */
	public function calc() {
		Billrun_Factory::dispatcher()->trigger('beforePricingData', array('data' => $this->data));
		$lines_coll = Billrun_Factory::db()->linesCollection();
		foreach ($this->lines as $key => $item) {
			$line = $this->pullLine($item);
			if ($line) {
				Billrun_Factory::dispatcher()->trigger('beforePricingDataRow', array('data' => &$line));
				//Billrun_Factory::log()->log("Calcuating row : ".print_r($item,1),  Zend_Log::DEBUG);
				$line->collection($lines_coll);
				if($this->isLineLegitimate($line)) {
					if (!$this->updateRow($line)) {
						unset($this->lines[$key]);
						continue;
					}
					$this->writeLine($line);
				}
				$this->data[] = $line;
				//$this->updateLinePrice($item); //@TODO  this here to prevent divergance  between the priced lines and the subscriber's balance/billrun if the process fails in the middle.
				Billrun_Factory::dispatcher()->trigger('afterPricingDataRow', array('data' => &$line));
			} else {
				unset($this->lines[$key]);
			}
		}
		Billrun_Factory::dispatcher()->trigger('afterPricingData', array('data' => $this->data));
	}

	protected function updateRow($row) {
		$rate = $row->get('customer_rate');
		
		$billrun_key = Billrun_Util::getBillrunKey($row['unified_record_time']->sec);

		//TODO  change this to be configurable.
		$pricingData = array();
		$billrun_info = array();

		$usage_type = $row['usaget'];
		$volume = $row['usagev'];
		if ($row['type'] == 'tap3') {
			$usage_class_prefix = "intl_roam_";
		} else {
			$usage_class_prefix = "";
		}

		if (isset($volume)) {
			if ($row['type'] == 'credit') {
				$accessPrice = isset($rate['rates'][$usage_type]['access']) ? $rate['rates'][$usage_type]['access'] : 0;
				$pricingData = array($this->pricingField => $accessPrice + $this->getPriceByRates($rate['rates'][$usage_type]['rate'], $volume));
			} else {
				$pricingData = $this->updateSubscriberBalance(array($usage_class_prefix . $usage_type => $volume), $row, $billrun_key, $usage_type, $rate, $volume);
			}
			$vatable = (!(isset($rate['vatable']) && !$rate['vatable']) || (!isset($rate['vatable']) && !$this->vatable));
			if (!$billrun = Billrun_Billrun::updateBillrun($billrun_key, array($usage_type => $volume), $pricingData, $row, $vatable)) {
				return false;
			} else if ($billrun instanceof Mongodloid_Entity) {
				$billrun_info['billrun_key'] = $billrun['billrun_key'];
				$billrun_info['billrun_ref'] = $billrun->createRef(Billrun_Factory::db()->billrunCollection());
			}
		} else {
			Billrun_Factory::log()->log("Line with stamp " . $row['stamp'] . " is missing volume information", Zend_Log::ALERT);
			return false;
		}

		$row->setRawData(array_merge($row->getRawData(), $pricingData));
		return true;
	}

	/**
	 * Get pricing data for a given rate / subcriber.
	 * @param int $volume The usage volume (seconds of call, count of SMS, bytes  of data)
	 * @param string $usageType The type  of the usage (call/sms/data)
	 * @param mixed $rate The rate of associated with the usage.
	 * @param mixed $subr the  subscriber that generated the usage.
	 * @return Array the 
	 */
	protected function getLinePricingData($volumeToPrice, $usageType, $rate, $sub_balance) {
		$typedRates = $rate['rates'][$usageType];
		$accessPrice = isset($typedRates['access']) ? $typedRates['access'] : 0;
		$plan = Billrun_Factory::plan(array('data' => $sub_balance['current_plan']));

		if ($plan->isRateInSubPlan($rate, $sub_balance, $usageType)) {
			$volumeToPrice = $volumeToPrice - $plan->usageLeftInPlan($sub_balance['balance'], $usageType);

			if ($volumeToPrice < 0) {
				$volumeToPrice = 0;
				//@TODO  check  if that actually the action we want once all the usage is in the plan...
				$accessPrice = 0;
			} else if ($volumeToPrice > 0) {
				$ret['over_plan'] = $volumeToPrice;
			}
		} else {
			$ret['out_plan'] = $volumeToPrice;
		}

		$price = $accessPrice + $this->getPriceByRates($typedRates['rate'], $volumeToPrice);
		//Billrun_Factory::log()->log("Rate : ".print_r($typedRates,1),  Zend_Log::DEBUG);
		$ret[$this->pricingField] = $price;


		return $ret;
	}

	/**
	 * Calculates the price for the given volume (w/o access price)
	 * @param array $rates_arr the "rate" array of a rate entry
	 * @param int $volume The usage volume (seconds of call, count of SMS, bytes  of data)
	 * @return int the calculated price
	 */
	protected function getPriceByRates($rates_arr, $volume) {
		$price = 0;
		foreach ($rates_arr as $currRate) {
			if (0 == $volume) { // volume could be negative if it's a refund amount
				break;
			}//break if no volume left to price.
			$volumeToPriceCurrentRating = ($volume - $currRate['to'] < 0) ? $volume : $currRate['to']; // get the volume that needed to be priced for the current rating
			if (isset($currRate['ceil'])) {
				$ceil = $currRate['ceil'];
			} else {
				$ceil = false;
			}
			if ($ceil) {
				$price += floatval(ceil($volumeToPriceCurrentRating / $currRate['interval']) * $currRate['price']); // actually price the usage volume by the current 	
			} else {
				$price += floatval($volumeToPriceCurrentRating / $currRate['interval'] * $currRate['price']); // actually price the usage volume by the current 
			}
			$volume = $volume - $volumeToPriceCurrentRating; //decressed the volume that was priced
		}
		return $price;
	}
	/**
	 * Update the subscriber balance for a given usage.
	 * @param array $counters the counters to update
	 * @param Mongodloid_Entity $row the input line
	 * @param string $billrun_key the billrun key at the row time
	 * @param string $usageType The type  of the usage (call/sms/data)
	 * @param mixed $rate The rate of associated with the usage.
	 * @param int $volume The usage volume (seconds of call, count of SMS, bytes  of data)
	 * @return mixed array with the pricing data on success, false otherwise
	 */
	protected function updateSubscriberBalance($counters, $row, $billrun_key, $usage_type, $rate, $volume) {
		$subscriber_balance = Billrun_Factory::balance(array('subscriber_id' => $row['subscriber_id'], 'billrun_key' => $billrun_key));
		if (!$subscriber_balance  || !$subscriber_balance->isValid()) {
			Billrun_Factory::log()->log("couldn't get balance for : " . print_r(array(
					'subscriber_id' => $row['subscriber_id'],
					'billrun_month' => $billrun_key
					), 1), Zend_Log::ALERT);
			return false;
		}
		$pricingData = $this->getLinePricingData($volume, $usage_type, $rate, $subscriber_balance);

		$balances = Billrun_Factory::db()->balancesCollection();
		$subRaw = $subscriber_balance->getRawData();
		$stamp = strval($row['stamp']);
		if (array_key_exists($stamp, $subRaw['tx'])) { // we're after a crash
			$pricingData = $subRaw['tx'][$stamp]; // restore the pricingData from before the crash
			return $pricingData;
		}
		$query = array('_id' => $subRaw['_id']);
		$update = array();
		$update['$set']['tx'][$stamp] = $pricingData;
		foreach ($counters as $key => $value) {
			$old_usage = $subRaw['balance']['totals'][$key]['usagev'];
			$query['balance.totals.' . $key . '.usagev'] = $old_usage;
			$update['$set']['balance.totals.' . $key . '.usagev'] = $old_usage + $value;
		}
		$update['$set']['balance.cost'] = $subRaw['balance']['cost'] + $pricingData[$this->pricingField];
		$options = array('w' => 1);
		$ret = $balances->update($query, $update, $options);
		if (!($ret['ok'] && $ret['updatedExisting'])) { // failed because of different totals (could be that another server with another line raised the totals). Need to calculate pricingData from the beginning
			$this->updateSubscriberBalance($counters, $row, $billrun_key, $usage_type, $rate, $volume);
		}
		return $pricingData;
	}

	/**
	 * removes the transactions from the subscriber's balance to save space.
	 * @param type $row
	 */
	protected function removeBalanceTx($row) {
		$balances_coll = Billrun_Factory::db()->balancesCollection();
		$subscriber_id = $row['subscriber_id'];
		$billrun_key = Billrun_Util::getBillrunKey($row['unified_record_time']->sec);
		$query = array(
			'billrun_month' => $billrun_key,
			'subscriber_id' => $subscriber_id,
		);
		$values = array(
			'$set' => array(
				'tx' => array(
				)
			)
		);
		$balances_coll->update($query, $values);
	}

	/**
	 * @see Billrun_Calculator::getCalculatorQueueType
	 */
	static protected function getCalculatorQueueType() {
		return self::$type;
	}

	/**
	 * @see Billrun_Calculator::isLineLegitimate
	 */
	protected function isLineLegitimate($line) {
		return isset($line['customer_rate']) && $line['customer_rate'] !== false && !isset($line['price_customer']) && $line['unified_record_time']->sec >= $this->billrun_lower_bound_timestamp; 
	}

	/**
	 * 
	 */
	protected function setCalculatorTag() {
		parent::setCalculatorTag();
		foreach ($this->data as $item) {
			$this->removeBalanceTx($item); // we can safely remove the transactions after the lines have left the current queue
		}
	}

}


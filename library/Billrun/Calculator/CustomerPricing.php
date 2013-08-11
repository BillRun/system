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

	protected $server_id = 1;
	protected $server_count = 1;
	protected $pricingField = 'price_customer';
	static protected $type = "pricing";

	/**
	 *
	 * @var boolean is customer price vatable by default
	 */
	protected $vatable = true;

	/**
	 *
	 * @var string
	 */
	protected $runtime_billrun_key;

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
		$this->runtime_billrun_key = Billrun_Util::getBillrunKey(time());
		$this->billrun_lower_bound_timestamp = is_null($this->months_limit) ? 0 : strtotime($this->months_limit . " months ago");
		// set months limit
		$this->load();
	}

	protected function getLines() {
		$queue = Billrun_Factory::db()->queueCollection();
		$query = self::getBaseQuery();
		$query['type'] = array('$in' => array('ggsn', 'smpp', 'smsc', 'nsn', 'tap3', 'credit'));
		$query['$or'][] = array('account_id' => array('$exists' => false));
		$query['$or'][] = array('account_id' => array('$mod' => array($this->server_count, $this->server_id - 1)));
		$update = self::getBaseUpdate();
		$options = array('sort' => array('unified_record_time' => 1));
		$i = 0;
		$docs = array();
		while ($i < $this->limit && ($doc = $queue->findAndModify($query, $update, array(), $options)) && !$doc->isEmpty()) {
			$docs[] = $doc;
			$i++;
		}
		return $docs;
	}

	/**
	 * execute the calculation process
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
				if (!$this->updateRow($line)) {
					unset($this->lines[$key]);
					continue;
				}
				$this->writeLine($line);
				$this->removeBalanceTx($line);
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
		if (!isset($row['customer_rate']) || $row['customer_rate'] === false || isset($row['price_customer']) || $row['unified_record_time']->sec < $this->billrun_lower_bound_timestamp) { // nothing to price
			return true; // move to next calculator
		}
		$billrun_key = Billrun_Util::getBillrunKey($row['unified_record_time']->sec);

		//TODO  change this to be configurable.
		$pricingData = array();

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
				$subscriber_balance = Billrun_Factory::balance(array('subscriber_id' => $row['subscriber_id'], 'billrun_key' => $billrun_key));
				if (!$subscriber_balance->isValid()) {
					Billrun_Factory::log()->log("couldn't get balance for : " . print_r(array(
							'subscriber_id' => $row['subscriber_id'],
							'billrun_month' => $billrun_key
							), 1), Zend_Log::ALERT);
					return false;
				}
				$pricingData = $this->getLinePricingData($volume, $usage_type, $rate, $subscriber_balance);
				$this->updateSubscriberBalance($subscriber_balance, array($usage_class_prefix . $usage_type => $volume), $pricingData, $row);
			}

			$vatable = (!(isset($rate['vatable']) && !$rate['vatable']) || (!isset($rate['vatable']) && !$this->vatable));
			$billrun_params = array(
				'account_id' => $row['account_id'],
				'billrun_key' => $billrun_key,
			);
			$billrun = Billrun_Factory::billrun($billrun_params);
			$billrun = $this->loadOpenBillrun($billrun);
			$billrun->update($row['subscriber_id'], array($usage_type => $volume), $pricingData, $row, $vatable);
			$billrun->save();
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
	 * Update the subsciber balance for a given usage.
	 * @param type $sub the subscriber. 
	 * @param type $counters the  counters to update
	 * @param type $charge the changre to add of  the usage.
	 */
	protected function updateSubscriberBalance($sub, $counters, &$pricingData, $row) {
		$subRaw = $sub->getRawData();
		$row_id = strval($row['_id']);
		if (array_key_exists($row_id, $subRaw['tx'])) { // we're after a crash
			$pricingData = $subRaw['tx'][$row_id]; // restore the pricingData from before the crash
			return;
		}
		$subRaw['tx'][$row_id] = $pricingData;
		foreach ($counters as $key => $value) {
			$subRaw['balance']['totals'][$key]['usagev'] += $value;
		}
		$subRaw['balance']['cost'] += $pricingData[$this->pricingField];
		$sub->setRawData($subRaw);
		$sub->save(Billrun_Factory::db()->balancesCollection());
	}

	/**
	 * gets an open billrun for subscriber with respect to the input billrun's billrun key
	 * @param type $billrun
	 * @param type $create
	 * @return Billrun_Billrun returned billrun's billrun key is not less than the input billrun key
	 */
	public function loadOpenBillrun($billrun) {
		$account_id = $billrun->getAccountId();
		$billrun_key = $billrun->getBillrunKey();
		if ($billrun->isValid()) {
			if ($billrun->isOpen()) { // found billing is open
				return $billrun;
			} else {
				return $this->loadOpenBillrun(Billrun_Factory::billrun(array('account_id' => $account_id, 'billrun_key' => Billrun_Util::getFollowingBillrunKey($billrun_key))));
			}
		} else if ($billrun_key >= $this->runtime_billrun_key) {
			Billrun_Factory::log("Adding account " . $account_id . " with billrun key " . $billrun_key . " to billrun collection", Zend_Log::INFO);
			return Billrun_Factory::billrun(array('account_id' => $account_id, 'billrun_key' => $billrun_key, 'autoload' => false))->save();
		} else { // billrun key is old
			return $this->loadOpenBillrun(Billrun_Factory::billrun(array('account_id' => $account_id, 'billrun_key' => Billrun_Util::getFollowingBillrunKey($billrun_key))));
		}
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

	static protected function getCalculatorQueueType() {
		return self::$type;
	}

}


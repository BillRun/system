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
		// set months limit
		$this->load();
	}

	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();
		$billrun_lower_bound_date = new MongoDate(is_null($this->months_limit) ? 0 : strtotime($this->months_limit . " months ago", time()));

		return $lines->query()
				->in('type', array('ggsn', 'smpp', 'smsc', 'nsn', 'tap3'))
				->exists('customer_rate')
				->notEq('customer_rate', FALSE)
				->exists('account_id')
				->exists('subscriber_id')
				->notEq('subscriber_id', FALSE)
				->notExists('price_customer')
				->greaterEq('unified_record_time', $billrun_lower_bound_date) // move this check to rate calculation stage?
				->mod('account_id', $this->server_count, $this->server_id - 1)
				->cursor()->sort(array('unified_record_time' => 1))
				->limit($this->limit);
	}

	public function loadOpenBillrun($billrun) {
		$account_id = $billrun->getAccountId();
		$billrun_key = $billrun->getBillrunKey();
		if ($billrun->isValid()) {
			if ($billrun->isOpen()) { // found billing is open
				return $billrun;
			} else {
				return $this->loadOpenBillrun($billrun->load($account_id, Billrun_Util::getFollowingBillrunKey($billrun_key)));
			}
		} else if ($billrun_key >= $this->runtime_billrun_key) {
			return $billrun->create($account_id, $billrun_key);
		} else { // billrun key is old
			return $this->loadOpenBillrun($billrun->load($account_id, Billrun_Util::getFollowingBillrunKey($billrun_key)));
		}
	}

	protected function updateRow($row) {
//		$rate = Billrun_Factory::db()->ratesCollection()->findOne(new Mongodloid_Id($row['customer_rate']));
		$rate = $row->get('customer_rate');
		$billrun_key = Billrun_Util::getBillrunKey($row['unified_record_time']->sec);
		$subscriber_balance = Billrun_Factory::balance(array('subscriber_id' => $row['subscriber_id'], 'billrun_key' => $billrun_key));
		if (!$subscriber_balance->isValid()) {
			Billrun_Factory::log()->log("couldn't get balance for :	'subscriber_id' => {$row['subscriber_id']},	'billrun_month' => {$billrun_key}", Zend_Log::DEBUG);
			return;
		}

		$billrun_params = array(
			'account_id' => $subscriber_balance['account_id'],
			'billrun_key' => $billrun_key,
		);

		//@TODO  change this to be configurable.
		$pricingData = array();

		$usage_type = $row['usaget'];
		$volume = $row['usagev'];
		if ($row['type'] == 'tap3') {
			$usage_class_prefix = "inter_roam_";
		} else {
			$usage_class_prefix = "";
		}

		if (isset($volume)) {
			$pricingData = $this->getLinePricingData($volume, $usage_type, $rate, $subscriber_balance);
			$this->updateSubscriberBalance($subscriber_balance, array($usage_class_prefix . $usage_type => $volume), $pricingData, $row);
			$vatable = (!(isset($rate['vatable']) && !$rate['vatable']) || (!isset($rate['vatable']) && !$this->vatable));
			$billrun = Billrun_Factory::billrun($billrun_params);
			$this->loadOpenBillrun($billrun);
			$billrun->update($subscriber_balance['subscriber_id'], array($usage_type => $volume), $pricingData, $row, $vatable);
		} else {
			//@TODO error?
		}

		$row->setRawData(array_merge($row->getRawData(), $pricingData));
	}

	/**
	 * execute the calculation process
	 */
	public function calc() {
		Billrun_Factory::dispatcher()->trigger('beforePricingData', array('data' => $this->data));
		$lines_coll = Billrun_Factory::db()->linesCollection();
		foreach ($this->lines as $item) {
			Billrun_Factory::dispatcher()->trigger('beforePricingDataRow', array('data' => &$item));
			//Billrun_Factory::log()->log("Calcuating row : ".print_r($item,1),  Zend_Log::DEBUG);
			$item->collection($lines_coll);
			$this->updateRow($item);
			$this->writeLine($item);
			$this->removeBalanceTx($item);
			$this->data[] = $item;
			//$this->updateLinePrice($item); //@TODO  this here to prevent divergance  between the priced lines and the subscriber's balance/billrun if the process fails in the middle.
			Billrun_Factory::dispatcher()->trigger('afterPricingDataRow', array('data' => &$item));
		}
		Billrun_Factory::dispatcher()->trigger('afterPricingData', array('data' => $this->data));
	}

	/**
	 * execute write the calculation output into DB
	 *
	  public function write() {
	  Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteData', array('data' => $this->data));
	  $lines = Billrun_Factory::db()->linesCollection();
	  foreach ($this->data as $item) {
	  $item->save($lines);
	  }
	  Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteData', array('data' => $this->data));
	  }

	  /**
	 * execute write the calculation output into DB
	 *
	  protected function updateLinePrice($line) {
	  Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteLineData', array('data' => $this->data));
	  $line->save();
	  Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteLineData', array('data' => $this->data));
	  } */

	/**
	 * Get pricing data for a given rate / subcriber.
	 * @param int $volume The usage volume (seconds of call, count of SMS, bytes  of data)
	 * @param string $usageType The type  of the usage (call/sms/data)
	 * @param mixed $rate The rate of associated with the usage.
	 * @param mixed $subr the  subscriber that generated the usage.
	 * @return Array the 
	 */
	protected function getLinePricingData($volumeToPrice, $usageType, $rate, $subr) {
		$typedRates = $rate['rates'][$usageType];
		$accessPrice = isset($typedRates['access']) ? $typedRates['access'] : 0;
		$plan = Billrun_Factory::plan(array('data' => $subr['current_plan']));

		if ($plan->isRateInSubPlan($rate, $subr, $usageType)) {
			$volumeToPrice = $volumeToPrice - $plan->usageLeftInPlan($subr['balance'], $usageType);

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

		$price = $accessPrice;
		//Billrun_Factory::log()->log("Rate : ".print_r($typedRates,1),  Zend_Log::DEBUG);
		foreach ($typedRates['rate'] as $key => $currRate) {
			if (0 >= $volumeToPrice) {
				break;
			}//break if no volume left to price.
			$volumeToPriceCurrentRating = ($volumeToPrice - $currRate['to'] < 0) ? $volumeToPrice : $currRate['to']; // get the volume that needed to be priced for the current rating
			$price += floatval((ceil($volumeToPriceCurrentRating / $currRate['interval']) * $currRate['price'])); // actually price the usage volume by the current 
			$volumeToPrice = $volumeToPrice - $volumeToPriceCurrentRating; //decressed the volume that was priced
		}
		$ret[$this->pricingField] = $price;


		return $ret;
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
	 * removes the tx's from the subscriber's balance to save space.
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

}


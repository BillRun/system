<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Price
 *
 * @author eran
 */
class Billrun_Calculator_CustomerPricing extends Billrun_Calculator {

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
				->exists('subscriber_id')
				->notExists('price_customer')
				->notExists('billrun')
				->greaterEq('unified_record_time', $billrun_lower_bound_date) // move this check to rate calculation stage?
				->cursor()->limit($this->limit);
	}

	public function loadOpenBillrun($billrun, $account_id, $billrun_key) {
		$billrun->load($account_id, $billrun_key);
		if ($billrun->isValid()) {
			if ($billrun->isOpen()) { // found billing is open
				return $this;
			} else {
				return $this->loadOpenBillrun($billrun, $account_id, Billrun_Util::getFollowingBillrunKey($billrun_key));
			}
		} else if ($billrun_key >= $this->runtime_billrun_key) {
			return $billrun->create($account_id, $billrun_key);
		} else { // billrun key is old
			return $this->loadOpenBillrun($billrun, $account_id, Billrun_Util::getFollowingBillrunKey($billrun_key));
		}
		return $billrun;
	}

	protected function updateRow($row) {
//		$rate = Billrun_Factory::db()->ratesCollection()->findOne(new Mongodloid_Id($row['customer_rate']));
		$rate = $row['customer_rate'];
		$billrun_key = Billrun_Util::getBillrunKey($row['unified_record_time']->sec);
		$subscriber_balance = Billrun_Model_Subscriber::getBalance($row['subscriber_id'], $billrun_key);
		if (!isset($subscriber_balance) || !$subscriber_balance) {
			Billrun_Factory::log()->log("couldn't get balance for : " . print_r(array(
					'subscriber_id' => $row['subscriber_id'],
					'billrun_month' => Billrun_Util::getBillrunKey($row['unified_record_time']->sec)
					), 1), Zend_Log::DEBUG);
			return;
		}

		$billrun_params = array(
			'account_id' => $subscriber_balance['account_id'],
			'billrun_key' => $billrun_key,
		);
		$billrun = Billrun_Factory::billrun($billrun_params);
		$this->loadOpenBillrun($billrun, $subscriber_balance['account_id'], $billrun_key);

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
			if ($subscriber_balance['subscriber_id'] == '209547') {
				echo 4;
			}
			$pricingData = $this->getLinePricingData($volume, $usage_type, $rate, $subscriber_balance);
			$this->updateSubscriberBalance($subscriber_balance, array($usage_class_prefix . $usage_type => $volume), $pricingData['price_customer']);
			$vatable = (!(isset($rate['vatable']) && !$rate['vatable']) || (!isset($rate['vatable']) && !$this->vatable));
			$billrun->update($subscriber_balance['subscriber_id'], array($usage_class_prefix . $usage_type => $volume), $pricingData, $row, $vatable);
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
			$this->data[] = $item;
			$this->updateLinePrice($item); //@TODO  this here to prevent divergance  between the priced lines and the subscriber's balance/billrun if the process fails in the middle.
			Billrun_Factory::dispatcher()->trigger('afterPricingDataRow', array('data' => &$item));
		}
		Billrun_Factory::dispatcher()->trigger('afterPricingData', array('data' => $this->data));
	}

	/**
	 * execute write the calculation output into DB
	 */
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
	 */
	protected function updateLinePrice($line) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteLineData', array('data' => $this->data));
		$line->save();
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteLineData', array('data' => $this->data));
	}

	/**
	 * Get pricing data for a given rate / subcriber.
	 * @param type $volume The usage volume (seconds of call, count of SMS, bytes  of data)
	 * @param type $usageType The type  of the usage (call/sms/data)
	 * @param type $rate The rate of associated with the usage.
	 * @param type $subr the  subscriber that generated the usage.
	 * @return type
	 */
	protected function getLinePricingData($volumeToPrice, $usageType, $rate, $subr) {
		$typedRates = $rate['rates'][$usageType];
		$accessPrice = isset($typedRates['access']) ? $typedRates['access'] : 0;

		if (Billrun_Model_Plan::isRateInSubPlan($rate, $subr, $usageType)) {
			$volumeToPrice = $volumeToPrice - Billrun_Model_Plan::usageLeftInPlan($subr, $usageType);

			if ($volumeToPrice < 0) {
				$volumeToPrice = 0;
				//@TODO  check  if that actually the action we  want  once  all the usage is in the plan...
				$accessPrice = 0;
			} else if ($volumeToPrice > 0) {
				$ret['over_plan'] = true;
			}
		} else {
			$ret['out_plan'] = true;
		}

		$interval = $typedRates['rate']['interval'] ? $typedRates['rate']['interval'] : 1;
		$ret['price_customer'] = $accessPrice + ( floatval((ceil($volumeToPrice / $interval) ) * $typedRates['rate']['price']) );

		return $ret;
	}

	/**
	 * Update the subsciber balance for a given usage.
	 * @param type $sub the subscriber. 
	 * @param type $counters the  counters to update
	 * @param type $charge the changre to add of  the usage.
	 */
	protected function updateSubscriberBalance($sub, $counters, $charge) {
		$subRaw = $sub->getRawData();
		//Billrun_Factory::log()->log("Raw Subscriber : ".print_r($subRaw,1),  Zend_Log::DEBUG);
		foreach ($counters as $key => $value) {
			$subRaw['balance']['totals'][$key]['usagev'] += $value;
		}
		$subRaw['balance']['cost'] += $charge;
		$sub->setRawData($subRaw);
		$sub->save(Billrun_Factory::db()->balancesCollection());
	}

}
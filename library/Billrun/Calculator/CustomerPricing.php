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

//
//	protected function __construct($options = array()) {
//		parent::__construct($options);
//		
//	}

	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();

		return $lines->query()
				->in('type', array('ggsn', 'smpp', 'smsc', 'nsn', 'tap3'))
				->exists('customer_rate')->notEq('customer_rate', FALSE)->exists('subscriber_id')->notExists('price_customer')->cursor()->limit($this->limit);
	}

	protected function updateRow($row) {
		$rate = Billrun_Factory::db()->ratesCollection()->findOne(new Mongodloid_Id($row['customer_rate']));
		$billrun_key = Billrun_Util::getBillrunKey($row['unified_record_time']->sec);
		$subscriber_balance = Billrun_Model_Subscriber::getBalance($row['subscriber_id'], $billrun_key);

		if (!isset($subscriber_balance) || !$subscriber_balance) {
			Billrun_Factory::log()->log("couldn't get subscriber for : " . print_r(array(
					'subscriber_id' => $row['subscriber_id'],
					'billrun_month' => Billrun_Util::getBillrunKey($row['unified_record_time']->sec)
					), 1), Zend_Log::DEBUG);
			return;
		}
		//@TODO  change this  be be configurable.
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
			$this->updateSubscriberBalance($subscriber_balance, array($usage_class_prefix . $usage_type => $volume), $pricingData['price_customer']);
			$this->updateBillrun($subscriber_balance, array($usage_class_prefix . $usage_type => $volume), $pricingData, $row, $billrun_key);
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
		foreach ($this->lines as $item) {
			Billrun_Factory::dispatcher()->trigger('beforePricingDataRow', array('data' => &$item));
			//Billrun_Factory::log()->log("Calcuating row : ".print_r($item,1),  Zend_Log::DEBUG);			
			$this->updateRow($item);
			$this->data[] = $item;
			$this->updateLinePrice($item); //@TODO  this here to prevent divergance  between the priced lines and the subscriber account if the process fails in the middle.
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
		$lines = Billrun_Factory::db()->linesCollection();
		$line->save($lines);
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

	static public function getOpenBillrun($subscriber, $billrun_key) {
		$billrun_coll = Billrun_Factory::db()->billrunCollection();
		$billrun = $billrun_coll
			->query(array(
				'account_id' => $subscriber['account_id'],
				'billrun_key' => $billrun_key,
			))
			->exists('subscribers.' . $subscriber['subscriber_id'])
			->cursor();
		if ($billrun->count()) {
			if (!$billrun->current()->offsetExists('invoice_id')) { // found billing is open
				return $billrun->current();
			} else {
				self::getOpenBillrun($subscriber, Billrun_Util::getFollowingBillrunKey($billrun_key));
			}
		} else {
			return Billrun_Model_Subscriber::createBillrun($subscriber, $billrun_key);
		}
	}

	protected function updateBillrun($subscriber, $counters, $pricingData, $row, $billrun_key) {
		$billrun = self::getOpenBillrun($subscriber, $billrun_key);
		$billRaw = $billrun->getRawData();
		$subscriberRaw = $billRaw['subscribers'][$subscriber->get('subscriber_id')];
		$rate = Billrun_Factory::db()->ratesCollection()->findOne(new Mongodloid_Id($row['customer_rate']));
		$vatable = (!(isset($rate['vatable']) && !$rate['vatable']) || (!isset($rate['vatable']) && !$this->vatable));

		// update costs
		if (isset($pricingData['over_plan']) && $pricingData['over_plan']) {
			$subscriberRaw['costs']['over_plan'] += $pricingData['price_customer'];
		} else if (isset($pricingData['out_plan']) && $pricingData['out_plan']) {
			if ($vatable) {
				$subscriberRaw['costs']['out_plan_vatable'] += $pricingData['price_customer'];
			} else {
				$subscriberRaw['costs']['out_plan_vat_free'] += $pricingData['price_customer'];
			}
		}

		switch ($row['usaget']) {
			case 'call':
			case 'incoming_call':
				$usage_type = 'call';
				break;
			case 'sms':
				$usage_type = 'sms';
				break;
			case 'data':
				$usage_type = 'data';
				break;
			default:
				$usage_type = 'call';
				break;
		}

		// update lines refs
		$subscriberRaw['lines'][$usage_type]['refs'][] = $row['_id']->getMongoID();

		// update data counters
		if ($usage_type == 'data') {
			$date_key = date("Ymd", $row['unified_record_time']->sec);
			if (isset($subscriberRaw['lines']['data']['counters'][$date_key])) {
				$subscriberRaw['lines']['data']['counters'][$date_key]+=$row['usagev'];
			} else {
				$subscriberRaw['lines']['data']['counters'][$date_key] = $row['usagev'];
			}
		}

		// update breakdown
		if (!isset($pricingData['over_plan']) && !isset($pricingData['out_plan'])) { // in plan
			$breakdown_key = 'flat';
			$zone_key = $billrun_key;
		} else if (isset($pricingData['over_plan']) && $pricingData['over_plan']) {
			$breakdown_key = 'over_plan';
		} else {

			$category = $rate['rates'][$row['usaget']]['category'];
			switch ($category) {
				case "roaming":
					$breakdown_key = "roaming";
					$zone_key = $row['serving_network'];
					break;
				case "special":
					$breakdown_key = "special";
					break;
				default:
					$breakdown_key = "intl";
					break;
			}
		}
		if (!isset($zone_key)) {
			$zone_key = Billrun_Factory::db()->ratesCollection()
					->query('_id', $row['customer_rate'])
					->cursor()->current()->get('key');
		}
		$this->addToBreakdown($subscriberRaw['breakdown'], $breakdown_key, $zone_key, $counters, $pricingData['price_customer'], $vatable);

		$billRaw['subscribers'][$subscriber['subscriber_id']] = $subscriberRaw;
		$billrun->setRawData($billRaw);
		$billrun->save(Billrun_Factory::db()->billrunCollection());
	}

	/**
	 * 
	 * @param type $key
	 * @param type $usage_type
	 * @param type $volume
	 */
	protected function addToBreakdown(&$breakdown_raw, $breakdown_key, $zone_key, $counters, $charge, $vatable) {
		if (!isset($breakdown_raw[$breakdown_key][$zone_key])) {
			$breakdown_raw[$breakdown_key][$zone_key] = Billrun_Model_Subscriber::getEmptyBalance();
		}
		$breakdown_raw[$breakdown_key][$zone_key]['totals'][key($counters)]['usagev']+=current($counters);
		$breakdown_raw[$breakdown_key][$zone_key]['totals'][key($counters)]['cost']+=$charge;
		if ($breakdown_key != 'flat') {
			$breakdown_raw[$breakdown_key][$zone_key]['cost']+=$charge;
		} else {
			
		}
		if (!isset($breakdown_raw[$breakdown_key][$zone_key]['vat'])) {
			$breakdown_raw[$breakdown_key][$zone_key]['vat'] = ($vatable ? Billrun_Factory::config()->getConfigValue('pricing.vat', '1.18') - 1 : 0); //@TODO we assume here that all the lines would be vatable or all vat-free
		}
	}

}
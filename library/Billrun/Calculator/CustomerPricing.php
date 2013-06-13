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

	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();

		return $lines->query()
				->in('type', array('ggsn', 'smpp', 'smsc', 'mmsc', 'nsn', 'tap3'))
				->exists('customer_rate')->notEq('customer_rate', FALSE)->exists('subscriber_id')->notExists('price_customer')->cursor()->limit($this->limit);
	}

	protected function updateRow($row) {
		$rate = Billrun_Factory::db()->ratesCollection()->findOne(new Mongodloid_Id($row['customer_rate']));
		$subscriber = Billrun_Model_Subscriber::get($row['subscriber_id'], Billrun_Util::getNextChargeKey($row['unified_record_time']->sec));

		if (!isset($subscriber) || !$subscriber) {
			Billrun_Factory::log()->log("couldn't get subscriber for : " . print_r(array(
					'subscriber_id' => $row['subscriber_id'],
					'billrun_month' => Billrun_Util::getNextChargeKey($row['unified_record_time']->sec)
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
			$pricingData = $this->getLinePricingData($volume, $usage_type, $rate, $subscriber); //$this->priceLine($volume, $usage_type, $rate, $subscriber);
			$row->setRawData(array_merge($row->getRawData(), $pricingData));
			$this->updateSubscriberBalance($subscriber, array($usage_class_prefix . $usage_type => $volume), $pricingData['price_customer']);
			$this->updateLinePrice($row); //@TODO  this here to prevent divergance  between the priced lines and the subscriber account if the process fails in the middle.
		} else {
			//@TODO error?
		}		
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
			
			Billrun_Factory::dispatcher()->trigger('afterPricingDataRow', array('data' => &$item));
		}
		Billrun_Factory::dispatcher()->trigger('afterPricingData', array('data' => $this->data));
	}

	/**
	 * This does noting  as  the  data i writen after each line update (not  at  the end) as  the  pricing is dependent on live  data.
	 */
	public function write() {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteData', array('data' => $this->data));

		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteData', array('data' => $this->data));
	}

	/**
	 * execute write the calculation output into DB
	 */
	protected function updateLinePrice($line) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteLineData', array('data' => $this->data));
		$line->save( Billrun_Factory::db()->linesCollection());
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
		$plan = Billrun_Factory::plan(array('id' => $subr['current_plan']));
		if ($plan->isRateInSubPlan($rate, $subr, $usageType)) {
			$volumeToPrice = $volumeToPrice - $plan->usageLeftInPlan($subr, $usageType);

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
			$subRaw['balance']['usage_counters'][$key] += $value;
		}
		$subRaw['balance']['current_charge'] += $charge;
		$sub->setRawData($subRaw);
		$sub->save(Billrun_Factory::db()->balancesCollection());
	}

}


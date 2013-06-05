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

	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();

		return $lines->query()
				->in('type', array('ggsn', 'smpp', 'smsc', 'nsn', 'tap3'))
				->exists('customer_rate')->notEq('customer_rate', FALSE)->exists('subscriber_id')->notExists('price_customer')->cursor()->limit($this->limit);
	}

	protected function updateRow($row) {
		$rate = Billrun_Factory::db()->ratesCollection()->findOne(new Mongodloid_Id($row['customer_rate']));
		$subscriber = Billrun_Model_Subscriber::get($row['subscriber_id'], Billrun_Util::getNextChargeKey($row['unified_record_time']->sec));
		if ($row['subscriber_id']=='103008') {
			echo "103008";
		}
		if (!isset($subscriber) || !$subscriber) {
			Billrun_Factory::log()->log("couldn't  get subscriber for : " . print_r(array(
					'subscriber_id' => $row['subscriber_id'],
					'billrun_month' => Billrun_Util::getNextChargeKey($row['unified_record_time']->sec)
					), 1), Zend_Log::DEBUG);
			return;
		}
		//@TODO  change this  be be configurable.

		$volume = null;
		$usage_type_class_prefix = '';

		switch ($row['type']) {
			case 'smsc' :
			case 'smpp' :
				$usage_type = 'sms';
				$volume = 1;
				break;

			case 'nsn' :
				$usage_type = 'call';
				$volume = $row['duration'];
				break;

			case 'ggsn' :
				$usage_type = 'data';
				$volume = $row['fbc_downlink_volume'] + $row['fbc_uplink_volume'];
				break;

			case 'tap3' :
				if (isset($row['usage_type'])) {
					$usage_type = $row['usage_type'];
					$usage_type_class_prefix = "inter_roam_";
					switch ($usage_type) {
						case 'sms' :
						case 'incoming_sms' :
							$volume = 1;
							break;

						case 'call' :
						case 'incoming_call' :
							$volume = $row->get('basicCallInformation.TotalCallEventDuration');
							break;

						case 'data' :
							$volume = $row->get('GprsServiceUsed.DataVolumeIncoming') + $row->get('GprsServiceUsed.DataVolumeOutgoing');
							break;
					}
				}
				break;
		}

		if (isset($volume)) {
			$row['price_customer'] = $this->priceLine($volume, $usage_type, $rate, $subscriber);
			$this->updateSubscriberBalance($subscriber, array($usage_type_class_prefix . $usage_type => $volume), $row['price_customer']);
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
	 * 
	 * @param type $volume
	 * @param type $lineType
	 * @param type $rate
	 * @param type $subr
	 * @return type
	 */
	protected function priceLine($volumeToPrice, $usage_type, $rate, $subr) {
		$typedRates = $rate['rates'][$usage_type];
		$accessPrice = isset($typedRates['access']) ? $typedRates['access'] : 0;

		if (Billrun_Model_Plan::isRateInSubPlan($rate, $subr, $usage_type)) {
			$volumeToPrice = $volumeToPrice - Billrun_Model_Plan::usageLeftInPlan($subr, $usage_type);

			if ($volumeToPrice < 0) {
				$volumeToPrice = 0;
				$accessPrice = 0;
			}
		}

		$interval = $typedRates['rate']['interval'] ? $typedRates['rate']['interval'] : 1;
		$price = $accessPrice + ( floatval((ceil($volumeToPrice / $interval) ) * $typedRates['rate']['price']) );

		return $price;
	}

	/**
	 * 
	 * @param type $sub
	 * @param type $counters
	 * @param type $charge
	 */
	protected function updateSubscriberBalance($sub, $counters, $charge) {
		$subRaw = $sub->getRawData();
		//Billrun_Factory::log()->log("Raw Subscriber : ".print_r($subRaw,1),  Zend_Log::DEBUG);
		foreach ($counters as $key => $value) {
			$subRaw['balance']['usage_counters'][$key] += $value;
		}
		$subRaw['balance']['current_charge'] += $charge;
		$sub->setRawData($subRaw);
		$sub->save(Billrun_Factory::db()->subscribersCollection());
	}

}


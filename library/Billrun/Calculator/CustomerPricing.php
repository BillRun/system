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
				->in('type', array('ggsn','smpp','smsc','nsn'))
				->exists('customer_rate')->exists('subscriber_id')->notExists('price_customer')->cursor()->limit($this->limit);
	}

	protected function updateRow($row) {
		$rate = Billrun_Factory::db()->ratesCollection()->findOne(new Mongodloid_Id($row['customer_rate']));
		$subscriber = Billrun_Factory::db()->subscribersCollection()->query(array(
																				'subscriber_id' => $row['subscriber_id'],
																				'billrun_month' =>  Billrun_Util::getNextChargeKey($row['unified_record_time']->sec)
																			))->cursor()->current();
		if(!isset($subscriber) || !$subscriber ) {
			Billrun_Factory::log()->log("couldn't  get subsciber for : ".print_r(array(
																				'subscriber_id' => $row['subscriber_id'],
																				'billrun_month' =>  Billrun_Util::getNextChargeKey($row['unified_record_time']->sec)
																			),1),  Zend_Log::DEBUG);			
			return;
		}
		//@TODO  change this  be be configurable.
		switch($row['type']) {
			case 'smsc' : 
			case 'smpp' :
					$row['price_customer'] = $this->priceLine(1, 'sms',$rate, $subscriber);
				break;

			case 'nsn' :
				$row['price_customer'] = $this->priceLine($row['duration'] , 'call', $rate, $subscriber);
				break;
			
			case 'ggsn' :
				$row['price_customer'] = $this->priceLine($row['fbc_downlink_volume'] + $row['fbc_uplink_volume'], 'data', $rate, $subscriber);
				break;
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
	
	protected function priceLine($volume, $lineType, $rate, $subr) {
		$typedRates = $rate['rates'][$lineType];
		$volumeToPrice = $volume;
		$accessPrice = $typedRates['access'];
		if(Billrun_Tariff::isRateInSubPlan($rate,$subr)) {
			$volumeToPrice = $volumeToPrice - Billrun_Tariff::usageLeftInPlan($subr, $lineType);
			if($volumeToPrice < 0) {
				$volumeToPrice = 0;
				$accessPrice = 0;
			}
		}
		$interval =  $typedRates['rate']['interval'] ?  $typedRates['rate']['interval'] : 1;
		$price =  $accessPrice + 
					(
						floatval((round($volumeToPrice  /  $interval) ) * 
						$typedRates['rate']['price']) 
					);
		
		$this->updateSubscriberBalance($subr, array($lineType => $volume ), $price);
		
		return $price;
	}

/*
	protected function priceSmsLine($line, $rate, $sub) {
		$volumeToPrice = 1;
		$accessPrice == $rate['access'];
		if(Billrun_Tariff::isRateInSubPlan($rate,$sub)) {
			$volumeToPrice = $volumeToPrice - Billrun_Tariff::usageLeftInPlan($sub,'sms');
			if($volumeToPrice < 0) {
				$volumeToPrice = 0;
				$accessPrice = 0;
			}
		}
		$price = $rate['rate']['price'];
		$this->updateSubscriberBalance($sub, array('sms' => 1 ), $price);
		return $price;
	}
	
	protected function priceDataLine($line, $rate, $sub) {
		//Billrun_Factory::log()->log("got Rating : ".print_r($rate,1),  Zend_Log::DEBUG);	
		$volumeToPrice = $line['duration'];
		$accessPrice == $rate['access'];
		if(Billrun_Tariff::isRateInSubPlan($rate,$sub)) {
			$volumeToPrice = $volumeToPrice - Billrun_Tariff::usageLeftInPlan($sub,'data');
			if($volumeToPrice < 0) {
				$volumeToPrice = 0;
				$accessPrice = 0;
			}
		}
		$price = $accessPrice + floatval((round(($line['fbc_downlink_volume'] + $line['fbc_uplink_volume']) / $rate['rate']['interval']) ) * $rate['rate']['price']);
		$this->updateSubscriberBalance($sub, array('data' => $line['fbc_downlink_volume'] + $line['fbc_uplink_volume'] ), $price);
		return $price;
	}
	
	protected function priceCallLine($line, $rate, $sub) {
		//Billrun_Factory::log()->log("got Rating : ".print_r($rate,1),  Zend_Log::DEBUG);	
		//TODO  add subscriber plan considuration
		$volumeToPrice = $line['duration'];
		$accessPrice == $rate['access'];
		if(Billrun_Tariff::isRateInSubPlan($rate,$sub)) {
			$volumeToPrice = $volumeToPrice - Billrun_Tariff::usageLeftInPlan($sub,'call');
			if($volumeToPrice < 0) {
				$volumeToPrice = 0;
				$accessPrice = 0;
			}
		}
		$price = $accessPrice + floatval((round($line['duration']  / $rate['rate']['interval']) ) * $rate['rate']['price']);
		
		$this->updateSubscriberBalance($sub, array('call' => $line['duration'] ), $price);
		
		return $price;
	}
  */
 
	/**
	 * 
	 * @param type $sub
	 * @param type $counters
	 * @param type $charge
	 */
	protected function updateSubscriberBalance($sub,$counters,$charge) {
		$subRaw = $sub->getRawData();
	//	Billrun_Factory::log()->log("Raw Subscriber : ".print_r($subRaw,1),  Zend_Log::DEBUG);
		foreach($counters as $key => $value) {
			$subRaw['balance']['usage_counters'][$key] += $value;
		}
		$subRaw['balance']['current_charge'] += $charge;
		$sub->setRawData($subRaw);
		$sub->save(Billrun_Factory::db()->subscribersCollection());

	}
}



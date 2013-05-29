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
				->exists('customer_rate')->cursor()->limit($this->limit);
	}

	protected function updateRow($row) {
		$rate = Billrun_Factory::db()->ratesCollection()->findOne(new Mongodloid_Id($row['customer_rate']));
		switch($row['type']) {
			case 'smsc' : 
			case 'smpp' :
					$row['price_customer'] = $this->priceSmsLine($row,$rate,null);
				break;
			
			case 'ggsn' :
				$row['price_customer'] = $this->priceDataLine($row, $rate,null);
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
	
	protected function priceSmsLine($line, $rate, $sub) {
		//TODO  add subscriber plan considuration
		//TODO  add subscriber plan  counters
		$price = $rate['sms']['rate']['price'];
		$this->updateSubscriberBalance($sub, array('sms' => 1 ), $price);
		return $price;
	}
	
	protected function priceDataLine($line, $rate, $sub) {
		//Billrun_Factory::log()->log("got Rating : ".print_r($rate,1),  Zend_Log::DEBUG);	
		//TODO  add subscriber plan considuration
		//TODO  add subscriber plan  counters
		$price = floatval((round(($line['fbc_downlink_volume'] + $line['fbc_uplink_volume']) / $rate['data']['rate']['interval']) ) * $rate['data']['rate']['price']);
		$this->updateSubscriberBalance($sub, array('data' => $line['fbc_downlink_volume'] + $line['fbc_uplink_volume'] ), $price);
		return $price;
	}
	
	protected function priceCallLine($line, $rate, $sub) {
		//Billrun_Factory::log()->log("got Rating : ".print_r($rate,1),  Zend_Log::DEBUG);	
		//TODO  add subscriber plan considuration
		//TODO  add subscriber plan  counters
		$price = floatval((round($line['duration']  / $rate['call']['rate']['interval']) ) * $rate['call']['rate']['price']);
		$this->updateSubscriberBalance($sub, array('calls' => $line['duration'] ), $price);
		
		return $price;
	}
	/**
	 * 
	 * @param type $sub
	 * @param type $counters
	 * @param type $charge
	 */
	protected function updateSubscriberBalance($sub,$counters,$charge) {
		foreach($values as $key => $value) {
			$sub['balance']['counters'][$key] += $value;
		}
		$sub['balance']['current_charge'] = $charge;
		$sub->save(Billrun_Factory::db()->subscribersCollection());

	}
}



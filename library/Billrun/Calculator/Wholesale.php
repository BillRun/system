<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Wholesale
 *
 * @author eran
 */
abstract class Billrun_Calculator_Wholesale extends Billrun_Calculator {

	
	/**
	 * Array holding all the peak off peak times for a given day type, in hours of the day.
	 * @param array $peakTimes
	 */
	protected $peakTimes = array(
									'weekday' => array('start' => 9 , 'end' => 19),
									'weekend' => array('start' => 0 , 'end' => -1),
									'shortday' => array('start' => 9 , 'end' => 13),
									'holyday' => array('start' => 0 , 'end' => -1)
								);
	
	protected $wholesaleRecords = array('11','12','08','09');
	
	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['peak_times'])) {
			$this->peakTimes = $options['peak_times'];
		}
		
		if (isset($options['wholesale_records'])) {
			$this->wholesaleRecords = $options['wholesale_records'];
		}
	}
	
	/**
	 * Get pricing data for a given rate / subcriber.
	 * @param type $volume The usage volume (seconds of call, count of SMS, bytes  of data)
	 * @param type $usageType The type  of the usage (call/sms/data)
	 * @param type $typedRates The rate of associated with the usage.
	 * @return array containing  the pricing  fields to add to the cdr.
	 */
	protected function getLinePricingData($volumeToPrice, $typedRates) {	
		$accessPrice = isset($typedRates['access']) ? $typedRates['access'] : 0;

		$price = $accessPrice;
		//Billrun_Factory::log()->log("Rate : ".print_r($typedRates,1),  Zend_Log::DEBUG);
		$rates = array();
		foreach ($typedRates['rate'] as $key => $currRate) {
			if (0 >= $volumeToPrice) {
				break;
			}//break if no volume left to price.
			$volumeToPriceCurrentRating = ($volumeToPrice - $currRate['to'] < 0) ? $volumeToPrice : $currRate['to']; // get the volume that needed to be priced for the current rating
			$price += floatval((ceil($volumeToPriceCurrentRating / $currRate['interval']) * $currRate['price'])); // actually price the usage volume by the current 
			$rates[] = array('rate'=> $currRate, 'volume' =>  $volumeToPriceCurrentRating);
			$volumeToPrice = $volumeToPrice - $volumeToPriceCurrentRating; //decressed the volume that was priced			
		}
		$ret['rates'] = $rates;
		$ret[$this->pricingField] = $price;
		return $ret;
	}
	
	/**
	 * Get rates by type  and zone  from a given carrier
	 * @param type $carrier
	 * @param type $zoneKey
	 * @param type $usageType
	 * @param type $peak
	 * @return type
	 */
	protected function getCarrierRateForZoneAndType($carrier,$zoneKey,$usageType,$peak = false ) {
		$typedRates = false;
		if( isset($carrier['zones'][$zoneKey])) {
			$typedRates =  $peak && isset($carrier['zones'][$zoneKey][$usageType][$peak]) ?
										$carrier['zones'][$zoneKey][$usageType][$peak] : 
										$carrier['zones'][$zoneKey][$usageType];
		}
		if(!$typedRates['rate'] || !is_array($typedRates['rate'])) {
			
			Billrun_Factory::log()->log("Couldn't find rate for key : $zoneKey",Zend_Log::DEBUG);
			Billrun_Factory::log()->log("With Carrier:". print_r($carrier,1),Zend_Log::DEBUG);
			Billrun_Factory::log()->log("What i did got  was : " . print_r($typedRates,1),Zend_Log::DEBUG);
		}

		return $typedRates;
	}
	
	/**
	 * Check if the cdr line  is incoming line  or outgoing
	 * @param type $row the line to check
	 * @return boolean true if the line  is incoming  false otherwise
	 */
	protected function isLineIncoming($row) {
		$ocg = $row->get('out_circuit_group');
		$ocgn = $row->get('out_circuit_group_name');
		return $ocg == 0 || $ocg == 3060 || $ocg == 3061 || preg_match("/^RCEL/",$ocgn) || $ocg == 152 ;
	}
	
	/**
	 * Check if a given row is in peak time.
	 * @param type $row the line to check if  it is in peak time.
	 * @return true if the line time is in peak time for the given carrier
	 */
	protected function isPeak($row) {
		$dayType = Billrun_HebrewCal::getDayType($row['unified_record_time']->sec);
		$localoffset = date('Z',$row['unified_record_time']->sec);
		$hour = (( ($row['unified_record_time']->sec + $localoffset) / 3600 ) % (24)) ;
		//Billrun_Factory::log()->log($hour,Zend_Log::DEBUG);
		return  ($hour - $this->peakTimes[$dayType]['start']) > 0 && $hour < $this->peakTimes[$dayType]['end'] ;
	}
	
	/**
	 * @see Billrun_Calculator::getCalculatorQueueType
	 */
	protected static function getCalculatorQueueType() {
		return static::MAIN_DB_FIELD;
	}
	
}


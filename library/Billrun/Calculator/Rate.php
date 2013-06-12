<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of RateAndVolume
 *
 * @author eran
 */
abstract class Billrun_Calculator_Rate extends Billrun_Calculator_SaveOnUpdate {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'rate';

	
	/**
	 * The mapping of the fileds in the lines to the 
	 * @var array
	 */
	protected $rateMapping = array();
	
	/**
	 * The rating field to update in the CDR line.
	 * @var string
	 */
	protected $ratingField = 'customer_rate';

	public function __construct($options = array()) {
		parent::__construct($options);
		if(isset($options['calculator']['rate_mapping'])) {
			$this->rateMapping = $options['calculator']['rate_mapping'];
			//Billrun_Factory::log()->log("receive options : ".print_r($this->rateMapping,1),  Zend_Log::DEBUG);
		}
	}
	
	/**
	 * Get a CDR line volume (duration/count/bytes used)
	 * @param $row the line to get  the volume for.
	 * @param the line usage type
	 */
	abstract protected function getLineVolume($row, $usage_type); 

	/**
	 * Get the line usage type (SMS/Call/Data/etc..)
	 * @param $row the CDR line  to get the usage for.
	 */
	abstract protected function getLineUsageType($row);
	
	/**
	 * Get the associate rate object for a given CDR line.
	 * @param $row the CDR line to get the for.
	 * @param $usage_type the CDR line  usage type (SMS/Call/etc..)
	 * @return the Rate object that was loaded  from the DB  or false if the line shouldn't be rated.
	 */
	abstract protected function getLineRate($row, $usage_type);
}


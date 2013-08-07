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
abstract class Billrun_Calculator_Rate extends Billrun_Calculator {

	const DEF_CALC_DB_FIELD = 'customer_rate';
	
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
	protected $ratingField = self::DEF_CALC_DB_FIELD;

	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['calculator']['rate_mapping'])) {
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
	
	/**
	 * Get an array of prefixes for a given number.
	 * @param type $str the number to get  prefixes to.
	 * @return Array the possible prefixes of the number.
	 */
	protected function getPrefixes($str) {
		$prefixes = array();
		for ($i = 0; $i < strlen($str); $i++) {
			$prefixes[] = substr($str, 0, $i + 1);
		}
		return $prefixes;
	}
}

	/**
	 * method to receive the lines the calculator should take care
	 * 
	 * @return Mongodloid_Cursor Mongo cursor for iteration
	 */
	protected function getLines() {
		$queue = Billrun_Factory::db()->queueCollection();
		$query = self::getBaseQuery();
		$query['type'] = static::$type;
		$update = self::getBaseUpdate();
		$i=0;
		$docs = array();
		while ($i<$this->limit && ($doc = $queue->findAndModify($query, $update)) && !$doc->isEmpty()) {
			$docs[] = $doc;
			$i++;
		}
		return $docs;
	}

	static protected function getCalculatorQueueType() {
		return self::$type;
	}

}
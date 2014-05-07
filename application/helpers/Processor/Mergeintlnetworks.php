<?php

/**
 * @category   Application
 * @package    Helpers
 * @subpackage Processor
 * @copyright  Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */
/**
 * Billing processor for import data into rates collection
 *
 * @package    Processor
 * @subpackage ImportZones
 * @since      1.0
 */
//class Processor_Importintnetworkmappings extends Billrun_Processor_Base_Separator {
//
//	/**
//	 * the type of the object
//	 *
//	 * @var string
//	 */
//	static protected $type = 'importintnetworkmappings';
//
//	public function __construct($options) {
//		parent::__construct($options);
//		$header = $this->getLine();
//		$this->parser->setStructure($header);
//	}
//
//	/**
//	 * we do not need to log
//	 * 
//	 * @return boolean true
//	 */
//	public function logDB() {
//		return TRUE;
//	}
//
//	/**
//	 * method to parse the data
//	 */
//	protected function parse() {
//		if (!is_resource($this->fileHandler)) {
//			Billrun_Factory::log()->log("Resource is not configured well", Zend_Log::ERR);
//			return false;
//		}
//
//		while ($line = $this->getLine()) {
//			$this->parseData($line);
//		}
//
//		return true;
//	}
//
//	/**
//	 * method to parse data
//	 * 
//	 * @param array $line data line
//	 * 
//	 * @return array the data array
//	 */
//	protected function parseData($line) {
//
//		$this->parser->setLine($line);
//		Billrun_Factory::dispatcher()->trigger('beforeDataParsing', array(&$line, $this));
//		$parsed_row = $this->parser->parse();
//		$row = array();
//		$row['PLMN'] = $parsed_row['PLMN'];
//		$row['name'] = $parsed_row['mnName'];
//		$row['type']['call'] = $parsed_row['mnAccessType'];
//		$row['type']['callback'] = $parsed_row['mnAccessTypeForCallback'];
//		$row['type']['incoming_call'] = $parsed_row['mnAccessTypeForInCall'];
//		$row['type']['data'] = $parsed_row['accessTypeForData'];
//		$row['type']['sms'] = $parsed_row['mnAccessTypeForSms'];		
//		$row['type']['incoming_sms'] = 'UNRATED';
//		$row['comment'] = $parsed_row['mnComment'];
//
//		Billrun_Factory::dispatcher()->trigger('afterDataParsing', array(&$row, $this));
//		$this->data['data'][] = $row;
//		return $row;
//	}
//
//	protected function store() {
//		if (!isset($this->data['data'])) {
//			// raise error
//			return false;
//		}
//
//		$rates = Billrun_Factory::db()->ratesCollection();
//
//		$this->data['stored_data'] = array();
//
//		foreach ($this->data['data'] as $key => $row) {
//			$entity = new Mongodloid_Entity($row);
//
//			if ($rates->query('PLMN', $row['PLMN'])->count() > 0) {
//				continue;
//			}
//
//			$entity->save($rates);
//			$this->data['stored_data'][] = $row;
//		}
//		return true;
//	}
//
//}


/**
 * @category   Application
 * @package    Helpers
 * @subpackage Processor
 * @copyright  Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

/**
 * Billing processor for merging intl. network rates into rates collection
 *
 * @package    Processor
 * @subpackage MergeRates
 * @since      1.0
 */
class Processor_Mergeintlnetworks extends Billrun_Processor_Base_Separator {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'mergeintl';
//	static protected $nsoftPLanToGolanPlan = array(
//		'SMALL' => array('ZG_HAVILA_SMS', 'ZG_HAVILA_VOICE', 'ZG_HAVILA_MMS'),
//		'LARGE' => array('ZG_HAVILA_SMS', 'ZG_HAVILA_VOICE', 'ZG_HAVILA_MMS', 'ZG_HAVILA_HOOL', 'ZG_NATIONAL'),
//	);
	static protected $mappingFields = array(
		'call' => 'mnAccessType',
		'callback' => 'mnAccessTypeForCallback',
		'incoming_call' => 'mnAccessTypeForInCall',
		'data' => 'accessTypeForData',
		'sms' => 'mnAccessTypeForSms',
//		'incoming_sms' => 'UNRATED',
	);

	public function __construct($options) {
		parent::__construct($options);
		$header = $this->getLine();
		$this->parser->setStructure($header);
	}

	/**
	 * we do not need to log
	 * 
	 * @return boolean true
	 */
	public function logDB() {
		return TRUE;
	}

	/**
	 * method to parse the data
	 */
	protected function parse() {
		if (!is_resource($this->fileHandler)) {
			Billrun_Factory::log()->log("Resource is not configured well", Zend_Log::ERR);
			return false;
		}


		while ($line = $this->getLine()) {
			$this->parseData($line);
		}

		return true;
	}

	/**
	 * method to parse data
	 * 
	 * @param array $line data line
	 * 
	 * @return array the data array
	 */
	protected function parseData($line) {

		$this->parser->setLine($line);
		Billrun_Factory::dispatcher()->trigger('beforeDataParsing', array(&$line, $this));
		$row = $this->parser->parse();
		Billrun_Factory::dispatcher()->trigger('afterDataParsing', array(&$row, $this));
		$this->data['data'][] = $row;
		return $row;
	}

	protected function store() {
		if (!isset($this->data['data'])) {
			// raise error
			return false;
		}

		$rates = Billrun_Factory::db()->ratesCollection();

		$this->data['stored_data'] = array();

		foreach ($this->data['data'] as $key => $row) {
			foreach (self::$mappingFields as $type => $field) {
				if (isset($row[$field])) {
					$rate = $rates->query('key', $row[$field])->cursor()->current();

					if ($rate->getId()) {
						$rate->collection($rates);
						$serving_networks = isset($rate['params']['serving_networks']) ? $rate['params']['serving_networks'] : array();
						$serving_networks[] = $row['PLMN'];
						$rate->set('params.serving_networks', $serving_networks);

						$rate->save($rates);
						$this->data['stored_data'][] = $row;
					} else {
						echo $row[$field] . " zone not found" . PHP_EOL . "<br />";
					}
				} else if ($field == 'UNRATED') {
					echo $row['PLMN'] . " $field not found" . PHP_EOL . "<br />";
				}
			}
		}

		return true;
	}

}

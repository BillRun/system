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
 * @subpackage WholesaleRatesImport
 * @since      1.0
 */
class Processor_Wholesaleoutrates extends Billrun_Processor_Base_Separator {

	/**
	 * The type of the object
	 *
	 * @var string
	 */
	static protected $type = 'wholesaleoutrates';

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
	 * Method to parse the data
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
	 * Method to parse data
	 * 
	 * @param array $line data line
	 * 
	 * @return array the data array
	 */
	protected function parseData($line) {
		$this->parser->setLine($line);
		Billrun_Factory::dispatcher()->trigger('beforeDataParsing', array(&$line, $this));
		$row = $this->parser->parse();
		$row['source'] = static::$type;
		$row['type'] = self::$type;
//		$row['header_stamp'] = $this->data['header']['stamp'];
		$row['file'] = basename($this->filePath);
		$row['process_time'] = date(self::base_dateformat);
		Billrun_Factory::dispatcher()->trigger('afterDataParsing', array(&$row, $this));
		$this->data['data'][] = $row;
		return $row;
	}

	protected function store() {
		if (!isset($this->data['data'])) {
			// raise error
			return false;
		}

		$carriers = Billrun_Factory::db()->carriersCollection();
//		$query = $rates->query();
//		foreach ($query as $row) {
//			print_R($row->get('key')); die;
//		}

		$this->data['stored_data'] = array();

		foreach ($this->data['data'] as $key => $row) {
			if ($row['carrier'] == 'GOLAN') {
				continue;
			}
			$row['carrier'] =  preg_replace("/_OUT$/", "", $row['carrier']);
			$zone = Billrun_Factory::db()->ratesCollection()->query(array('key' => $row['wsaleZoneName']))->cursor()->current();
			// todo check if rate already exists, if so, close row and open new row
//			if ($row['accessTypeName'] == 'AC_ROAM_INCOMING1') {
//				echo "yes";
//			}
			if ($zone->getId()) {
				$entity = $carriers->query(array('key' => $row['carrier']))->cursor()->current();
				if (!$entity->getId()) {
					$entity = $this->createANewCarrier($row);
				}

				$entity['zones'] = array_merge($entity['zones'], $this->getZoneRate($entity, $row));

				$entity->collection($carriers);
				$entity->save($carriers);

				$this->data['stored_data'][] = $row;
			} else {
				echo $row['wsaleZoneName'] . " zone not found" . PHP_EOL . "<br />";
			}
		}

		return true;
	}

	protected function getRate($rateRow) {
		$intervalMultiplier = 1;
		switch ($rateRow['wsaleTclassName']) {
			case 'WTC_V':
			case 'WTC_T':
			case 'GTMOC':
				$rateType = 'call';
				$unit = 'seconds';
				break;
			case 'WTC_D':
				$rateType = 'data';
				$unit = 'bytes';
				$intervalMultiplier = 1024;
				break;
			case 'WTC_S':
				$rateType = 'sms';
				$unit = 'counter';
				break;
			case 'WTC_M':
				$rateType = 'mms';
				$unit = 'counter';
				break;
			default:
				echo("Unknown kind :  {$rateRow['wsaleTclassName']} <br/>\n");
				return array();
		}

		$value = array(
			'unit' => $unit,
			'rate' => array(
				array(
					'to' => (int) 2147483647,
					'price' => (double) $rateRow['sampPrice'],
					'interval' => (int) ( $rateRow['sampDelayInSec'] ? ($rateRow['sampDelayInSec'] ) : 1 ) * $intervalMultiplier,
				)),
		);
		if ($rateType == 'call') { // add access price for calls
			$value['access'] = (double) $rateRow['accessPrice'];
		}
		return array( //added peak/off peak for bezeq carriers
				$rateType => (	preg_match("IL_FIX",$rateRow['wsaleZoneName']) && $rateRow['timePeriod'] != 'ALL' ? 
									array($this->translateTime($rateRow['timePeriod']) => $value) : 
									$value )
			);
	}

	/**
	 * TODO
	 * @param type $rateRow
	 * @return \Mongodloid_Entity
	 */
	protected function createANewCarrier($rateRow) {
		return new Mongodloid_Entity(array(
				'key' => $rateRow['carrier'],
				'name' => $rateRow['carrier'],
				'currency' => 'ILS', //as defined by http://en.wikipedia.org/wiki/ISO_4217			
				'from' => new MongoDate(),
				'to' => new MongoDate(),
				'prefixes' => array(),
				'zones' => array('incoming' => array()),
			));
	}
	
	/**
	 * TODO
	 * @param type $entity
	 * @param type $rateRow
	 * @return type
	 */
	protected function getZoneRate($entity, $rateRow) {
		$currentRates = isset($entity['zones'][$rateRow['wsaleZoneName']]) ? $entity['zones'][$rateRow['wsaleZoneName']] : array();
		foreach($this->getRate($rateRow) as $key => $value) {
			if( $value ) {
				$currentRates[$key] = isset($currentRates[$key]) ? array_merge($currentRates[$key], $value) : $value;
			}
		}
		return array($rateRow['wsaleZoneName'] => $currentRates);
	}
	
	/**
	 * TODO
	 * @param type $timePeriod
	 * @return type
	 */
	protected function translateTime($timePeriod) {
		return $timePeriod == "RED" ? 'peak' : ($timePeriod == "BLUE" ? 'off_peak' : 'all');
	}

}
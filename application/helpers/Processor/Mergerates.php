<?php

/**
 * @category   Application
 * @package    Helpers
 * @subpackage Processor
 * @copyright  Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

/**
 * Billing processor for merge data rates into rates collection
 *
 * @package    Processor
 * @subpackage MergeRates
 * @since      1.0
 */
class Processor_Mergerates extends Billrun_Processor_Base_Separator {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'mergerates';

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

		$this->addZeroRates();

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
		$row['source'] = static::$type;
		$row['type'] = self::$type;
//		$row['header_stamp'] = $this->data['header']['stamp'];
		$row['file'] = basename($this->filePath);
		$row['process_time'] = date(self::base_dateformat);

		if (Billrun_Util::startsWith($row['accessTypeName'], "AC_ROAM_CALLBACK")) { //@TODO change this check when there is a way to detect callback as the usage type
			$row['kind'] = "A";
		}

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
//		$query = $rates->query();
//		foreach ($query as $row) {
//			print_R($row->get('key')); die;
//		}

		$this->data['stored_data'] = array();

		foreach ($this->data['data'] as $key => $row) {
			$intervalMultiplier = 1;
			switch ($row['kind']) {
				case 'A':
					$rateType = 'incoming_call';
					$unit = 'seconds';
					break;
				case 'C':
					$rateType = 'call';
					$record_type = array("01", "11","30");
					$unit = 'seconds';
					break;
				case 'I':
					$rateType = 'data';
					$unit = 'bytes';
					$intervalMultiplier = 1024;
					break;
				case 'M':
					$rateType = 'sms';
					$unit = 'counter';
					break;
				case 'N':
					$rateType = 'mms';
					$unit = 'counter';
					break;
				case 'incoming_sms':
					$rateType = 'incoming_sms';
					$unit = 'counter';
					break;
				default:
					echo("Unknown kind :  {$row['kind']} <br/>\n");
					continue;
			}
			$entity = $rates->query(array('key' => ( $row['zoneOrItem'] != '' ? $row['zoneOrItem'] : 'ALL_DESTINATION')))->cursor()->current();
			// todo check if rate already exists, if so, close row and open new row
//			if ($row['accessTypeName'] == 'AC_ROAM_INCOMING1') {
//				echo "yes";
//			}
			if ($entity->getId()) {
				$this->addZonesByRates($row, $entity);
				$entity->collection($rates);
				$value = array(
					'unit' => $unit,
					'rate' => array(
						array(
							'to' => (int) 2147483647,
							'price' => (double) $row['tinf_sampPrice0'],
							'interval' => (int) ( $row['tinf_sampDelayInSec0'] ? ($row['tinf_sampDelayInSec0'] ) : 1 ) * $intervalMultiplier,
						)),
				);
				if (isset($row['zoneOrItem']) && Billrun_Util::startsWith($row['zoneOrItem'], 'KT_')) {
					$category = "intl";
				} else if (isset($row['accessTypeName']) && Billrun_Util::startsWith($row['accessTypeName'], 'AC_ROAM_')) {
					$category = "roaming";
				}
				if (isset($category)) {
					$value['category'] = $category;
				}
				if ($row['kind'] == 'C') { // add access price for calls
					$value['access'] = (double) $row['tinf_accessPrice0'];
				}
				$entityRates = $entity['rates'];
				$entityRates[$rateType] = $value;
				$entity['rates'] = $entityRates;
				if ($row['zoneOrItem'] != 'UNRATED' && isset($record_type)) {
					$entity->set("params.record_type", $record_type);
				}
				$entity->save($rates);
				$this->data['stored_data'][] = $row;
			} else {
				echo $row['zoneOrItem'] . " zone not found" . PHP_EOL . "<br />";
			}
		}

		return true;
	}

	protected function addZonesByRates(&$row, &$entity) {
		$rates = Billrun_Factory::db()->ratesCollection();
		//foreach ($this->data['data'] as &$row) {
		if (isset($row['accessTypeName']) && $row['accessTypeName'] != 'Regular' && $row['accessTypeName'] != 'Callback') {
			if ($row['zoneOrItem'] == '') {
				Billrun_Factory::log('Found empty zone name. Treating as \'ROAM_ALL_DEST\' zone.', Zend_Log::NOTICE);
			}
			if (Billrun_Factory::db()->ratesCollection()->query(array('key' => $row['accessTypeName']))->cursor()->current()->getId()) {
				return;
			}
			$row['zoneOrItem'] = $row['accessTypeName'];
			$entity['vatable'] = false;
			$entity['key'] = $row['zoneOrItem'];
			unset($entity['_id']);
		}
	}

	protected function addZeroRates() {
		$usage_types = array(
			'C' => array(
				'interval' => 1
			),
			'incoming_sms' => array(
				'interval' => 1
			),
			'A' => array(
				'interval' => 1
			),
			'I' => array(
				'interval' => 10
			),
			'M' => array(
				'interval' => 1
			),
			'N' => array(
				'interval' => 1
			),
		);
		foreach ($usage_types as $usage_type_kind => $usage_type) {
			$row = array();
			$row['zoneOrItem'] = 'UNRATED';
			$row['kind'] = $usage_type_kind;
			$row['tinf_sampPrice0'] = 0;
			$row['tinf_sampDelayInSec0'] = $usage_type['interval'];
			$row['tinf_accessPrice0'] = 0;
			$this->data['data'][] = $row;
		}
	}

}

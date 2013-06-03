<?php

/**
 * @category   Application
 * @package    Helpers
 * @subpackage Processor
 * @copyright  Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
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
		
		$this->addZonesByRates();
		$this->addZeroRate();

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
			switch ($row['kind']) {
				case 'A':
					continue 2; // we will take care later
					$rateType = 'data';
					$unit = 'bytes';
					break;
				case 'C':
					$rateType = 'call';
					$record_type = array("01", "11");
					$unit = 'seconds';
					break;
				case 'I':
					$rateType = 'data';
					$unit = 'bytes';
					break;
				case 'M':
					$rateType = 'sms';
					$unit = 'counter';
					break;
				case 'N':
					$rateType = 'mms';
					$unit = 'counter';
					break;
				default:
					echo("Unknown kind :  {$row['kind']} <br/>\n");
					continue;
			}
			$entity = $rates->query(array('key' => $row['zoneOrItem']))->cursor()->current();
			// todo check if rate already exists, if so, close row and open new row
			if ($entity->getId()) {
				$entity->collection($rates);
				$value = array(
					'unit' => $unit,
					'rate' => array(
						'to' => (int) 2147483647,
						'price' => (double) $row['tinf_sampPrice0'],
						'interval' => (int) $row['tinf_sampDelayInSec0'],
					),
				);
				if ($row['kind'] == 'C') { // add access price for calls
					$value['access'] = (double) $row['tinf_accessPrice0'];
				}
				$entityRates = $entity['rates'];
				$entityRates[$rateType] = $value;
				$entity['rates'] = $entityRates;
				//@TODO Talk to Shani..
				//$entity->set("access_type_name", $row['accessTypeName']);
				//$entity->set("type", $rateType);
				//$entity->set("rates", $value);
				//$entity['rates'][$rateType] = $value;
				if ($row['zoneOrItem'] != 'UNRATED') {
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

	protected function addZonesByRates() {
		$rates = Billrun_Factory::db()->ratesCollection();
		foreach ($this->data['data'] as &$row) {
			switch ($row['zoneOrItem']) {
				
				case "ROAM_ALL_DEST":
				case "\$DEFAULT":
				case "":
				case "ALL_DESTINATION":
					if ($row['zoneOrItem']=='') {
						Billrun_Factory::log('Found empty zone name. Treating as \'ROAM_ALL_DEST\' zone.', Zend_Log::ALERT);
					}
					$row['zoneOrItem'] = $row['accessTypeName'];

					$new_zone = array();
					$new_zone['from'] = new MongoDate(strtotime('2013-01-01T00:00:00+00:00'));
					$new_zone['to'] = new MongoDate(strtotime('+100 years'));
					$new_zone['key'] = $row['zoneOrItem'];
					$entity = new Mongodloid_Entity($new_zone);

					if ($rates->query('key', $new_zone['key'])->count() > 0) {
						continue;
					}
					
					$entity->save($rates, true);
					break;
				default:
					continue 2;
			}
		}
	}

	protected function addZeroRate() {
		$row = array();
		$row['zoneOrItem'] = 'UNRATED';
		$row['kind'] = 'C';
		$row['tinf_sampPrice0'] = 0;
		$row['tinf_sampDelayInSec0'] = 1;
		$row['tinf_accessPrice0'] = 0;
		$this->data['data'][] = $row;
	}

}

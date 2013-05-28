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
					continue 2;
					$rateType = 'data';
					continue; // we will take care later
					break;
				case 'C':
					$rateType = 'call';
					$record_type = array("01", "11");
					$unit = 'seconds';
					break;
				case 'I':
					continue 2;
					$rateType = 'data';
					continue; // we will take care later
					break;
				case 'M':
					continue 2;
					$rateType = 'sms';
					$unit = 'counter';
					break;
				case 'N':
					continue 2;
					break;
			}
			$entity = $rates->query('key', $row['zoneOrItem'])->cursor()->current();
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
				$entity->set("type", $rateType);
				$entity->set("rates", $value);
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

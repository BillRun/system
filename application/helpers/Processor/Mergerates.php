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
					$rateKey = 'data';
					continue; // we will take care later
					break;
				case 'C':
					$rateKey = 'call';
					$unit = 'seconds';
					break;
				case 'I':
					$rateKey = 'data';
					continue; // we will take care later
					break;
				case 'M':
					$rateKey = 'sms';
					$unit = 'counter';
					break;
				case 'N':
					continue;
					break;
			}
			$entity = $rates->query('key', $row['zoneOrItem'])->cursor()->current();
			// todo check if rate already exists, if so, close row and open new row
			if ($entity->getId()) {
				$entity->collection($rates);
				$value = array(
					'unit' => $unit,
					'rate' => array(
						"to" => 2147483647,
						"price" => $row['tinf_sampPrice0'],
						"interval" => $row['tinf_sampDelayInSec0'],
					),
				);
				$entity->set("rates.".$rateKey, $value);
				$entity->save($rates);
				$this->data['stored_data'][] = $row;
			}
		}

		return true;
	}

}

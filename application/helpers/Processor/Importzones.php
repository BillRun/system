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
class Processor_ImportZones extends Billrun_Processor_Base_Separator {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'importzones';

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

		$this->addUnrated();
		$this->addGolan();
		$this->addVAT();
		$this->addCreditRates();
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

		$data = $this->normalize($this->data['data']);
		$this->data['stored_data'] = array();

		foreach ($data as $key => $row) {
			$entity = new Mongodloid_Entity($row);
			if ($rates->query('key', $key)->count() > 0) {
				continue;
			}

			$entity->save($rates, true);
			$this->data['stored_data'][] = $row;
		}

		return true;
	}

	protected function normalize($data) {
		$ret = array();
		$data[] = array('zoneName' => '$DEFAULT'); // does not exist in zone
		foreach ($data as $row) {
			$row['rates'] = array();
			/* if (!isset($row['zoneName']) || $row['zoneName']=='ROAM_ALL_DEST' || $row['zoneName']=='$DEFAULT' || $row['zoneName']=='ALL_DESTINATION') {
			  print_R($row);
			  continue;
			  } */
			$key = $row['zoneName'];
			if ($key == 'UNRATED') {
				$ret[$key]['key'] = $key;
			} else if ($key == 'VAT') {
				$ret[$key]['key'] = $key;
				$ret[$key]['vat'] = 0.18;
				$ret[$key]['from'] = new MongoDate(1338508800);
				$ret[$key]['to'] = new MongoDate(4531055365);
			} else if (Billrun_Util::startsWith($key, "CREDIT")) {
				$ret[$key]['key'] = $key;
				$ret[$key]['from'] = new MongoDate(1338508800);
				$ret[$key]['to'] = new MongoDate(4531055365);
				$ret[$key]['vatable'] = $row['zoneName'] == 'CREDIT_VATABLE';
				$ret[$key]['rates']['credit'] = array(
					'unit' => 'seconds',
					'rate' => array(
						array(
							'to' => 2147483647,
							'price' => 1,
							'interval' => 1,
							'ceil' => false,
						),
					),
					'category' => 'base',
				);
			} else if (!isset($ret[$key])) {
				if ($key == 'GOLAN') {
					$out_circuit_group = array(
						array(
							"from" => "00",
							"to" => "152"
						)
					);
				} else if (Billrun_Util::startsWith($row['zoneName'], "IL_ILD") || Billrun_Util::startsWith($row['zoneName'], "KT")) {
					$out_circuit_group = array(
						array(
							"from" => "2000",
							"to" => "2101"
						)
					);
				} else {
					$out_circuit_group = array(
						array(
							"from" => "0",
							"to" => "1999"
						),
						array(
							"from" => "2102",
							"to" => "99999999"
						),
					);
				}
				$ret[$key] = array(
					'from' => new MongoDate(strtotime('2012-06-01T00:00:00+00:00')),
					'to' => new MongoDate(strtotime('+100 years')),
					'key' => $row['zoneName'],
					'params' => array(
						'prefix' => (isset($row['prefix']) ? array($row['prefix']) : array() ),
						'out_circuit_group' => $out_circuit_group
					),
				);
			} else {
				$ret[$key]['params']['prefix'][] = $row['prefix'];
			}
		}

		foreach ($ret as $value) {
			if ($value['key'] == "IL_FIX" || $value['key'] == "IL_MOBILE" || $value['key'] == "IL_PIKUD_OREF") {
				$params_dup = array();
				$il_prefix = "972";
				foreach ($value['params']['prefix'] as $prefix) {
					if (Billrun_Util::startsWith($prefix, $il_prefix)) {
						$params_dup[] = substr($prefix, strlen($il_prefix));
					}
				}
				$ret[$value['key']]['params']['prefix'] = array_merge($value['params']['prefix'], $params_dup);
			}
		}

		return $ret;
	}

	protected function addUnrated() {
		$row = array();
		$row['zoneName'] = "UNRATED";
		$this->data['data'][] = $row;
	}

	protected function addGolan() {
		$row = array();
		$row['zoneName'] = "GOLAN";
		$this->data['data'][] = $row;
	}

//	protected function addCredit() {
//		$row = array();
//		$row['zoneName'] = 
//		$row['from'] = 
//	}

	protected function addVAT() {
		$row = array();
		$row['from'] = new MongoDate(1338508800);
		$row['to'] = new MongoDate(4531055365);
		$row['zoneName'] = 'VAT';
		$this->data['data'][] = $row;
	}

	protected function addCreditRates() {
		foreach (array('CREDIT_VATABLE', 'CREDIT_VAT_FREE') as $value) {
			$row = array();
			$row['zoneName'] = $value;
			$this->data['data'][] = $row;
		}
	}

}

<?php

/*
 * @package         Tests
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Test case class for the config module
 *
 * @package         Tests
 * @subpackage      Config
 * @since           4.4
 */
class Tests_UpdateRowSetUp {

	/**
	 * json files names in data dir
	 * each file will be added to the relevant collection.
	 * 
	 * @var array
	 */
	protected $collectionToClean = ['discounts','balances','plans', 'services', 'subscribers', 'rates', 'lines','bills','billing_cycle','billrun','counters'];
	protected $importData =		   ['discounts','balances','plans', 'services', 'subscribers', 'rates', 'lines','bills','billing_cycle','billrun','counters'];
	protected $backUpData = array();
	public $config;
	protected $configCollection;
	public $data;
	protected $unitTestName;
	protected $dataPath = '/data/';

	public function __construct($unitTestName = null) {
		$this->unitTestName = $unitTestName;
		if (isset($this->unitTestName)) {
			$this->dataPath = "/{$this->unitTestName}Data/";
		}
	}

	/**
	 * executes set up for update row test
	 */
	public function setColletions() {
		$this->backUpCollection($this->importData);
		$this->cleanCollection($this->collectionToClean);
		$collectionsToSet = $this->importData;
		array_unshift($collectionsToSet, 'config');
		foreach ($collectionsToSet as $file) {
			$dataAsText = file_get_contents(dirname(__FILE__) . $this->dataPath . $file . '.json');
			$parsedData = json_decode($dataAsText, true);
			if ($parsedData === null) {
				echo('Cannot decode <span style="color:#ff3385; font-style: italic;">' . $file . '.json. </span> <br>');
				continue;
			}
			$data = $this->fixData($parsedData['data']);
			$coll = Billrun_Factory::db()->{$parsedData['collection']}();
			$coll->batchInsert($data);
		}
		$this->loadConfig();
	}

	public function restoreColletions() {
		$this->cleanCollection($this->collectionToClean);
		$this->restoreCollection();
	}

	/**
	 * tranform all fields starts with time* into MongoDate object
	 * @param array $jsonAr 
	 */
	protected function fixDates($jsonAr) {
		foreach ($jsonAr as $key => $jsonFile) {
			$jsonAr[$key] = $this->fixArrayDates($jsonFile);
		}
		return $jsonAr;
	}

	/**
	 * tranform all fields starts with time* into MongoDate object
	 * @param array $jsonAr 
	 */
	protected function fixArrayDates($arr) {
		if (!is_array($arr)) {
			return $arr;
		}
		foreach ($arr as $jsonField => $value) {
			if (is_string($value)) {
				$value = explode("*", $value);
				if ((count($value) == 2) && ($value[0] == 'time')) {
					$value = new MongoDate(strtotime($value[1]));
					$arr[$jsonField] = $value;
				}
			} else if (is_array($arr)) {
				$arr[$jsonField] = $this->fixArrayDates($value);
			}
		}
		return $arr;
	}

	/* convert :
	 * DBRefblabla":{
	 * 		"collection":"plan",
	 * 		"ObjectId":"5aeee54905e68c5b1f45f9f4"
	 * 	},
	 * TO  "blabla": DBRef("plans", ObjectId("5aeee54905e68c5b1f45f9f4")),
	 */

	public function fixDbRef($data) {
		foreach ($data as $key => $value) {
		
			if (preg_match("/DBRef/", $key)) {
				$newRef = preg_replace("/^DBRef/", "", $key);
				$data[$newRef] = $data[$key];
				$data[$newRef] = MongoDB::createDBRef($data[$newRef]['collection'], new MongoID($data[$newRef]['ObjectId']));
				unset($data[$key]);
			}
				if (is_array($value) && count($value) > 0) {
				$data[$key] = $this->fixDbRef($value);
			}
		}

		return $data;
	}

	/*
	 * 	converte "OBJID":"blablablablabal"  to   "_id": ObjectId("blablablablabal")
	 */

	public function fixDBobjID($data) {
		if (isset($data['OBJID'])) {
			$data['_id'] = $data['OBJID'];
			$data['_id'] = new MongoID($data['OBJID']);
			unset($data['OBJID']);
		}
		return $data;
	}

	public function fixData($data) {
		foreach ($data as $key => $jsonFile) {
			$data[$key] = $this->fixArrayDates($jsonFile);
		}
		foreach ($data as $key => $jsonFile) {
			$data[$key] = $this->fixDBobjID($jsonFile);
		}
		foreach ($data as $key => $jsonFile) {
			$data[$key] = $this->fixDbRef($jsonFile);
		}
		return $data;
	}

	/**
	 * @param array $colNames array of collectins names to clean
	 */
	protected function cleanCollection($colNames) {
		foreach ($colNames as $colName) {
			$colName = $colName . 'Collection';
			Billrun_Factory::db()->$colName()->remove([null]);
		}
	}

	protected function backUpCollection($colNames) {
		foreach ($colNames as $colName) {
			$colName = $colName . 'Collection';
			$items = iterator_to_array(Billrun_Factory::db()->$colName()->query(array())->getIterator());
			$this->backUpData[$colName] = array();
			foreach ($items as $item) {
				array_push($this->backUpData[$colName], $item->getRawData());
			}
		}
	}

	public function loadConfig() {
		$this->config = Billrun_Factory::db()->configCollection();
		$ret = $this->config->query()
			->cursor()
			->sort(array('_id' => -1))
			->limit(1)
			->current()
			->getRawData();
		$this->data = $ret;
		
	}

	public function setConfig() {
		unset($this->data['_id']);
		$this->config->insert($this->data);
	}
	
	public function changeConfig($key,$value){
		$orignalData = $this->data;
		Billrun_Util::setIn($this->data, $key, $value);
		$this->setConfig();
		$this->data = $orignalData ;
	}


	protected function restoreCollection() {
		foreach ($this->backUpData as $colName => $items) {
			if (count($items) > 0) {
				Billrun_Factory::db()->$colName()->batchInsert($items);
			}
		}
		$this->setConfig();
	}

}

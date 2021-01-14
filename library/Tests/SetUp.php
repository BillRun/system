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
trait Tests_SetUp {
	/**
	 * json files names in data dir
	 * each file will be added to the relevant collection.
	 * 
	 * @var array
	 */

	/**
	 * collection To Clean from DB and store in cash  while the unit test is run
	 * @var array 
	 */
	protected $collectionToClean = ['plans', 'services', 'subscribers', 'rates', 'lines', 'balances'];

	/**
	 * collection names to set for testing
	 * @var array 
	 */
	protected $importData = ['plans', 'services', 'subscribers', 'rates', 'lines', 'balances'];

	/**
	 * collection names to restore 
	 * @var arrray 
	 */
	protected $backUpData = array();

	/**
	 * @var Mongodloid_Collection 
	 */
	public $config;

	/**
	 * name of the runing unit test
	 * @var string 
	 */
	protected $unitTestName;

	/**
	 * path of json files with data for runing unit test
	 * @var string
	 */
	protected $dataPath = '/data/';

	public function construct($unitTestName = null, $dataToLoad = null) {
		$this->unitTestName = $unitTestName;
		if (isset($this->unitTestName)) {
			$this->dataPath = "/{$this->unitTestName}Data/";
		}
		if (isset($dataToLoad)) {
			$this->collectionToClean = array_merge($this->collectionToClean, $dataToLoad);
			$this->importData = array_merge($this->importData, $dataToLoad);
		}
	}

	/**
	 * executes set up for the unit runing unit test 
	 */
	public function setColletions() {
		$this->originalConfig = $this->loadConfig();
		$this->backUpCollection($this->importData);
		$this->cleanCollection($this->collectionToClean);
		$collectionsToSet = $this->importData;
		array_unshift($collectionsToSet, 'config');
		foreach ($collectionsToSet as $file) {
			$dataAsText = file_get_contents(dirname(__FILE__) . $this->dataPath . $file . '.json');
			$parsedData = json_decode($dataAsText, true);
			if ($parsedData === null) {
				echo(' <span style="color:#ff3385; font-style: italic;">' . $file . '.json. </span> <br>');
				continue;
			}
			if (!empty($parsedData['data'])) {
				$data = $this->fixData($parsedData['data']);
				$coll = Billrun_Factory::db()->{$parsedData['collection']}();
				$coll->batchInsert($data);
			}
		}
	}

	public function restoreColletions() {
		$this->cleanCollection($this->collectionToClean);
		$this->restoreCollection();
	}

	/**
	 * tranform all fields starts with time* into Mongodloid_Date object
	 * @param array $jsonAr 
	 */
	protected function fixDates($jsonAr) {
		foreach ($jsonAr as $key => $jsonFile) {
			$jsonAr[$key] = $this->fixArrayDates($jsonFile);
		}
		return $jsonAr;
	}

	/**
	 * tranform all fields starts with time* into Mongodloid_Date object
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
					$value = new Mongodloid_Date(strtotime($value[1]));
					$arr[$jsonField] = $value;
				}
			} else if (is_array($arr)) {
				$arr[$jsonField] = $this->fixArrayDates($value);
			}
		}
		return $arr;
	}

	/* convert :
	 * blabla":{
	 * 		"collection":"plan",
	 * 		"ObjectId":"5aeee54905e68c5b1f45f9f4",
	 * 		"isDbRef" : true
	 * 	},
	 * TO  "blabla": DBRef("plans", ObjectId("5aeee54905e68c5b1f45f9f4")),
	 */

	/**
	 * 
	 * @param array $data
	 * @return array
	 */
	public function fixDbRef($data) {
		foreach ($data as $key => $value) {
			if (is_array($value)) {
				if (!empty($value['isDbRef'])) {
					unset($value['isDbRef']);
					$data[$key] = Billrun_Factory::db()->getCollection($data[$key]['collection'])->createRefByEntity([ '_id'=>$data[$key]['ObjectId']]);
				}
			}
		}
		return $data;
	}

	/*
	 * 	converte "OBJID":"blablablablabal"  to   "_id": ObjectId("blablablablabal")
	 */

	/**
	 * 
	 * @param array $data
	 * @return array
	 */
	public function fixDBobjID($data) {
		if (isset($data['OBJID'])) {
			$data['_id'] = $data['OBJID'];
			$data['_id'] = new Mongodloid_Id($data['OBJID']);
			unset($data['OBJID']);
		}
		return $data;
	}

	/**
	 * call to fix functions for each json object
	 * @param array $data
	 * @return array
	 */
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

	/**
	 * store the collection from DB for restore after the unit test run
	 * @param arrray $colNames
	 */
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

	/**
	 * return the current config from DB
	 * @return Mongo_object
	 */
	public function loadConfig() {
		$this->config = Billrun_Factory::db()->configCollection();
		$ret = $this->config->query()
			->cursor()
			->sort(array('_id' => -1))
			->limit(1)
			->current()
			->getRawData();
		return $ret;
	}

	/**
	 * insert config
	 * @param array $data
	 */
	public function setConfig($data) {
		unset($data['_id']);
		$this->config->insert($data);
	}

	/**
	 * 
	 * @param array $data 
	 * @param string $key key to change its value
	 * @param string | int|array $value new value
	 */
	public function changeConfigKey($data, $key, $value) {
		Billrun_Util::setIn($data, $key, $value);
		$this->setConfig($data);
	}

	/**
	 * after unit test run ,restore the original collection to DB
	 */
	protected function restoreCollection() {
		foreach ($this->backUpData as $colName => $items) {
			if (count($items) > 0) {
				Billrun_Factory::db()->$colName()->batchInsert($items);
			}
		}
		$this->setConfig($this->originalConfig);
	}

}

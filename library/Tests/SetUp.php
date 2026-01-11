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
trait Tests_SetUp
{
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

	protected static $request = null;

	public function construct($unitTestName = null, $dataToLoad = null)
	{
		$this->unitTestName = $unitTestName;
		if (isset($this->unitTestName)) {
			$this->dataPath = "/{$this->unitTestName}Data/";
		}
		if (isset($dataToLoad)) {
			$this->collectionToClean = array_merge($this->collectionToClean, $dataToLoad);
			$this->importData = array_merge($this->importData, $dataToLoad);
		}
	}

	public function  getRequest(){
		if(self::$request == null){
			self::$request = new Yaf_Request_Http;
		}
		return self::$request;
	}


	/**
	 * executes set up for the unit runing unit test 
	 */
	public function setColletions()
	{
		$this->originalConfig = $this->loadConfig();
		$this->backUpCollection($this->importData);
		$this->cleanCollection($this->collectionToClean);
		$collectionsToSet = $this->importData;
		array_unshift($collectionsToSet, 'config');
		foreach ($collectionsToSet as $collectionName) {
			$dataAsText = file_get_contents(dirname(__FILE__) . $this->dataPath . $collectionName . '.json');
			$parsedData = json_decode($dataAsText, true);
			if ($parsedData === null) {
				echo (' <span style="color:#ff3385; font-style: italic;">' . $collectionName . '.json. </span> <br>');
				continue;
			}
			if (!empty($parsedData['data'])) {
				$data = $this->fixData($parsedData['data'],$collectionName);
				$coll = Billrun_Factory::db()->{$parsedData['collection']}();
				$coll->batchInsert($data);
			}
		}
	}

	public function restoreColletions()
	{
		$this->cleanCollection($this->collectionToClean);
		$this->restoreCollection();
	}

	public function skip_tests($tests, $path)
	{
		$this->test_cases_to_skip = $this->getRequest()->get('skip');
		if ($this->test_cases_to_skip !== null && !empty($this->test_cases_to_skip)) {
			$this->test_cases_to_skip = explode(',', $this->test_cases_to_skip);
			foreach ($tests as $case) {
				$test_number = Billrun_Util::getIn($case, $path);
				if (!in_array($test_number, $this->test_cases_to_skip)) {
					$cases[] = $case;
				}
			}
		}
		return !empty($cases) ? $cases : $tests;
	}

	public function autoload_tests($dir)
	{
		$dir = new DirectoryIterator(__DIR__ . '/' . $dir);
		foreach ($dir as $fileinfo) {
			if (!$fileinfo->isDot()) {
				$filename = $fileinfo->getFilename();
				// check if the file has .php extension
				if (pathinfo($filename, PATHINFO_EXTENSION) === 'php') {
					require_once($fileinfo->getPathname());
				}
			}
		}
	}


	public function getTestCases($legacy_tests = [])
	{
		$all_test_cases = [];
    $label = explode(" ", $this->getLabel())[1];
    $first = '';
    $second = '';
    $path = '';
    
		switch ($label) {
				case 'Aggregatore':
            $first = 'test';
            $second = 'test_number';
            $path = APPLICATION_PATH . '/library/Tests/aggregatorTestCases/';
					break;
				case 'Customercalculatortest':
            $first = 'row';
            $second = 'stamp';
            $path = APPLICATION_PATH . '/library/Tests/CustomerCalculator/';
					break;
				case 'Ratetest':
            $first = 'row';
            $second = 'stamp';
            $path = APPLICATION_PATH . '/library/Tests/Rate/';
					break;
				case 'UpdateRow':
            $first = 'row';
            $second = 'stamp';
            $path = APPLICATION_PATH . '/library/Tests/UpdateRow/';
            break;
        case 'Taxmapping':
            $first = 'test';
            $second = 'test_number';
            $path = APPLICATION_PATH . '/library/Tests/taxmappingTestCases/';
					break;
				case 'event':
					$first='row';
					$secound='stamp';
					break;
				default:
					throw new Exception("Unknown label: $label");
			}
	
    // Load all PHP files from the specified path
    if (!empty($path)) {
        foreach (glob($path . "*.php") as $filename) {
            require_once $filename;
        }
    }

    $test_cases_to_skip = !empty($this->getRequest()->get('skip')) ? explode(',', $this->getRequest()->get('skip')) : [];
    $test_cases_to_run = !empty($this->getRequest()->get('tests')) ? explode(',', $this->getRequest()->get('tests')) : [];
	
		// Get all declared classes
		$classes = get_declared_classes();

		// Iterate over the classes
		foreach ($classes as $class) {
			// Check if the class name starts with 'Test_Case_'
			if (strpos($class, 'Test_Case_') === 0) {
				$test_number = filter_var($class, FILTER_SANITIZE_NUMBER_INT);
            
            // Check if the test should be run based on skip and run filters
            if (
                (empty($test_cases_to_skip) && empty($test_cases_to_run)) ||
                (empty($test_cases_to_skip) && in_array($test_number, $test_cases_to_run)) ||
                (!empty($test_cases_to_skip) && !in_array($test_number, $test_cases_to_skip))
            ) {
						// Create an instance of the class
						$instance = new $class();

						// Call the test_case method and store the result
						if (method_exists($instance, 'test_case')) {
							$test_case = $instance->test_case();
							$all_test_cases[] = $test_case;
                }
            }
			}
		}

		// Sort the test cases by test_number
    usort($legacy_tests, function ($a, $b) use ($first, $second) {
        return $a[$first][$second] <=> $b[$first][$second];
		});
    
    usort($all_test_cases, function ($a, $b) use ($first, $second) {
        return $a[$first][$second] <=> $b[$first][$second];
		});

    // Merge legacy tests with all test cases
    return $this->mergeArraysByKey($all_test_cases, $legacy_tests, "$first.$second");
	}
	function mergeArraysByKey($array1, $array2, $path) {
		$mergedArray = [];
	
		foreach ($array1 as $item1) {
			$mergedItem = $item1;
			$test_number1 = Billrun_Util::getIn($item1, $path);
	
			foreach ($array2 as $key => $item2) {
				$test_number2 = Billrun_Util::getIn($item2, $path);
	
				if ($test_number1 == $test_number2) {
					$mergedItem = array_merge($item1, $item2);
					// Remove item from array2 so we don't process it again.
					unset($array2[$key]);
					break;
				}
			}
	
			$mergedArray[] = $mergedItem;
		}
	
		// Add any remaining items from array2.
		foreach ($array2 as $item2) {
			$mergedArray[] = $item2;
		}
	
		return $mergedArray;
	}
	

	/**
	 * tranform all fields starts with time* into Mongodloid_Date object
	 * @param array $jsonAr 
	 */
	protected function fixDates($jsonAr)
	{
		foreach ($jsonAr as $key => $jsonFile) {
			$jsonAr[$key] = $this->fixArrayDates($jsonFile);
		}
		return $jsonAr;
	}

	/**
	 * tranform all fields starts with time* into Mongodloid_Date object
	 * @param array $jsonAr 
	 */
	protected function fixArrayDates($arr)
	{
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
	public function fixDbRef($data)
	{
		foreach ($data as $key => $value) {
			if (is_array($value)) {
				if (!empty($value['isDbRef'])) {
					unset($value['isDbRef']);
					$data[$key] = Billrun_Factory::db()->getCollection($data[$key]['collection'])->createRefByEntity(['_id' => $data[$key]['ObjectId']]);
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
	public function fixDBobjID($data)
	{
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
	public function fixData($data, $collectionName)
	{
		foreach ($data as $key => $jsonFile) {
			$data[$key] = $this->fixArrayDates($jsonFile);
		}
		foreach ($data as $key => $jsonFile) {
			$data[$key] = $this->fixDBobjID($jsonFile);
		}
		foreach ($data as $key => $jsonFile) {
			$data[$key] = $this->fixDbRef($jsonFile);
		}
		if($collectionName == 'config'){
			$data[0]['urt'] = new MongoDB\BSON\UTCDateTime();
		}
		return $data;
	}

	/**
	 * @param array $colNames array of collectins names to clean
	 */
	protected function cleanCollection($colNames)
	{
		foreach ($colNames as $colName) {
			$colName = $colName . 'Collection';
			Billrun_Factory::db()->$colName()->remove([null]);
		}
	}

	/**
	 * store the collection from DB for restore after the unit test run
	 * @param arrray $colNames
	 */
	protected function backUpCollection($colNames)
	{
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
	public function loadConfig()
	{
		$this->config = Billrun_Factory::db()->configCollection();
		$ret = $this->config->query()
			->cursor()
			->sort(array('urt'=> -1, '_id' => -1))
			->limit(1)
			->current()
			->getRawData();
		return $ret;
	}

	/**
	 * insert config
	 * @param array $data
	 */
	public function setConfig($data)
	{
		unset($data['_id']);
		$this->config->insert($data);
	}

	/**
	 * 
	 * @param array $data 
	 * @param string $key key to change its value
	 * @param string | int|array $value new value
	 */
	public function changeConfigKey($data, $key, $value)
	{
		Billrun_Util::setIn($data, $key, $value);
		$this->setConfig($data);
	}

	/**
	 * after unit test run ,restore the original collection to DB
	 */
	protected function restoreCollection()
	{
		foreach ($this->backUpData as $colName => $items) {
			if (count($items) > 0) {
				Billrun_Factory::db()->$colName()->batchInsert($items);
			}
		}
		$this->setConfig($this->originalConfig);
	}



    /**
	*function to set new config value during the test run 
	*@param array $newConfig['key'] config key, can be singel key or path seperate by .
	*@param array $newConfig['value']
	 */
	public function setConfigValue($row = [],$newConfig){

		$config = Billrun_Factory::db()->configCollection();
		$data = $this->config->query()
			->cursor()
			->sort(array('urt'=> -1, '_id' => -1))
			->limit(1)
			->current()
			->getRawData();
		unset($data['_id']);
		Billrun_Util::setIn($data, $newConfig['key'],$newConfig['value']);
		$config->insert($data);
		Billrun_Config::getInstance()->loadDbConfig();
	}

}

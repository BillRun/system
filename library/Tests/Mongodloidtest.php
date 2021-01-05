<?php
 
//tests for Mongodloid_Collection

require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');

define('UNIT_TESTING', 'true');

class Tests_Mongodloid extends UnitTestCase{
	
	use Tests_SetUp;
	
	public $message = '';
	protected $fails;
	protected $tests = array(
		//check success update 
		array('function' => 'checkUpdate', 'collection' => 'lines',
			'params'=> array(
				'options' => array(),
				'values' => array('$set' => array('firstname' => 'or', 'lastname' => 'SHAASHUA')),
				'query' => array('_id' => '5aeee57b05e68c02d035e1f6')
			),
			'expected' => array('result' => array('n'=>1,  'nModified' => 1, 'updatedExisting' => true, 'ok' => 1 ),
				'dbValues'=> array('firstname' => 'or', 'lastname' => 'SHAASHUA', 'stamp'=> 'a305a9e1d842cf9d632010bf39dcb674')
			)
		),
		//check failed update - because already updated values
		array('function' => 'checkUpdate', 'collection' => 'lines',
			'params'=> array(
				'options' => array(),
				'values' => array('$set' => array('firstname' => 'or', 'lastname' => 'SHAASHUA')),
				'query' => array('_id' => '5aeee57b05e68c02d035e1f6')
			),
			'expected' => array('result' => array('n'=>1,'nModified' => 0, 'updatedExisting' => false, 'ok' => 1 ))
		),
		//check failed update - because not found query
		array('function' => 'checkUpdate', 'collection' => 'lines',
			'params'=> array(
				'options' => array(),
				'values' => array('$set' => array('plan' => 'new_plan')),
				'query' => array('_id' => '5aeee57b05e68c02d035e111')
			),
			'expected' => array('result' => array('n'=>0, 'nModified' => 0, 'updatedExisting' => false, 'ok' => 1 ))
		),
		//check success multiple update
		array('function' => 'checkUpdate', 'collection' => 'lines',
			'params'=> array(
				'options' => array('multiple' => true),
				'values' => array('$set' => array('firstname' => 'dana')),
				'query' => array('type' => 'rr')
			),
			'expected' => array('result' => array('n'=>4,  'nModified' => 4, 'updatedExisting' => true, 'ok' => 1 ),
				'dbValues'=> array('firstname' => 'dana', 'type' => 'rr')
				)
		),
		//check success replaceOne
		array('function' => 'checkUpdate', 'collection' => 'lines',
			'params'=> array(
				'options' => array(),
				'values' => array('firstname' => 'or', 'stamp' => 'a1fd85a9a16dcbc1f21d59afc8458cdf'),
				'query' => array('stamp' => 'a1fd85a9a16dcbc1f21d59afc8458cdf')
			),
			'expected' => array('result' => array('n'=>1,  'nModified' => 1, 'updatedExisting' => true, 'ok' => 1 ),
				'dbValues'=> array('firstname' => 'or', 'lastname' => null)
			)
		),
		//get collectionName
		array('function' => 'checkCollectionName', 'collection' => 'lines',
			'expected' => array('result' => 'lines')
		),
		//get collectionName
		array('function' => 'checkCollectionName', 'collection' => 'rates',
			'expected' => array('result' => 'rates')
		),
//		//get collectionName
//		array('function' => 'checkIndexes', 'collection' => 'plans',
//			'expected' => array('result' => 'rates')
//		)
	);


	public function __construct($label = 'Mongodloid layer') {
         parent::__construct("test mongodloid layer");
         $this->construct(basename(__FILE__, '.php'), []);
         $this->setColletions();
         $this->loadDbConfig();
     }
	 
	 public function loadDbConfig() {
         Billrun_Config::getInstance()->loadDbConfig();
     }
	 
	/**
	 * the function is runing all the test cases  
	 * print the test result
	 * and restore the original data 
	 */
	public function TestPerform() {
		foreach ($this->tests as $key => $test) {
			$test_number = $key + 1;
			$this->message .= "<span id={$test_number}>test number : " . $test_number. '</span><br>';
			//run tests functios 
			if (isset($test['function'])) {
				$function = $test['function'];
				$testSuccess = $this->assertTrue($this->$function($test));
				if (!$testSuccess) {
					$this->fails .= "|---|<a href='#{$test_number}'>{$test_number}</a>";
				}
			}
			$this->message .= '<p style="border-top: 1px dashed black;"></p>';
		}
		if ($this->fails) {
			$this->message .= $this->fails;
		}
		print($this->message);
		$this->cleanCollection($this->collectionToClean);
		$this->restoreCollection();
	}
	
	protected function checkUpdate($test){
		$collection = $test['collection'];
		$query = $test['params']['query'];
		if(isset($query['_id'])){
			$query['_id'] =  new MongoId($query['_id']);//for now check mongo
		}
		$options = $test['params']['options'];
		$values = $test['params']['values'];
		$result = Billrun_Factory::db()->{$collection . 'Collection'}()->update($query, $values, $options);
		return $this->checkResult($result, $test['expected']['result'])
			&& (!isset($test['expected']['dbValues']) || $this->checkDb($collection, $query, $test['expected']['dbValues']));
	}
	
	protected function checkCollectionName($test){
		$collection = $test['collection'];
		$result = Billrun_Factory::db()->{$collection . 'Collection'}()->getName();
		$expectedResult = $test['expected']['result'];
		return $result === $expectedResult;
	}
	
//	protected function checkEnsureIndex($test){
//		$collection = $test['collection'];
//		$result = Billrun_Factory::db()->{$collection . 'Collection'}()->getIndexes();
//		$expectedResult = $test['expected']['result'];
//		return $result === $expectedResult;
//	}
	
	//updateEntity
	
	protected function checkDb($collection, $query, $expectedDbValues) {
		foreach ($expectedDbValues as $field => $expectedDbValue){
			$testResults = Billrun_Factory::db()->{$collection . 'Collection'}()->find($query);
			foreach ($testResults as $result){
				if(!isset($result[$field]) && $expectedDbValue === null){
					continue;
				}
				if($result[$field] != $expectedDbValue){
					return false;
				}
			}
		}
		return true;
	}
	
	protected function checkResult($testResults, $expectedResults) {
		foreach ($expectedResults as $field => $expectedResult){
			if($testResults[$field] != $expectedResult){
				return false;
			}
		}
		return true;
	}
}


class Mongodloid_Cursor_Test {
	
}

class Mongodloid_Date_Test {
	
}
class Mongodloid_Db_Test {
	
}

class Mongodloid_Entity_Test{
	
}

class Mongodloid_Id_Test{
	
}

class Mongodloid_Query_Test{
	
}

class Mongodloid_Connection_Test {
	
}

class Mongodloid_Regex_Test {
	
}



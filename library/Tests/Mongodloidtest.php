<?php
 
//tests for Mongodloid_Collection

require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');

define('UNIT_TESTING', 'true');

class Tests_Mongodloid extends UnitTestCase{
	
	use Tests_SetUp;
	
	public $message = '';
	protected $fails;
	protected $tests = array(
		// check success update 
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
		//check collectionName - lines
		array('function' => 'checkCollectionName', 'collection' => 'lines',
			'expected' => array('result' => 'lines')
		),
		//check collectionName - rates
		array('function' => 'checkCollectionName', 'collection' => 'rates',
			'expected' => array('result' => 'rates')
		),
		//check findOne array result
		array('function' => 'checkFindOne', 'collection' => 'subscribers',
			'params'=> array(
				'want_array' =>true,
				'id' => '5b34a76f05e68c6ae62d4a33'
			),
		),
		//check findOne entity result
		array('function' => 'checkFindOne', 'collection' => 'subscribers',
			'params'=> array(
				'want_array' =>false,
				'id' => '5b34a76f05e68c6ae62d4a33'
			),
		),
//		//TODO:: need to restore also indexes after finisfh run unit test
//		//check dropIndexes
//		array('function' => 'checkDropIndexes', 'collection' => 'balances',
//			'expected' => array('result' => array('ok' => 1))),
//		//check getIndexes
//		array('function' => 'checkGetIndexes', 'collection' => 'balances',
//			'params'=> array(
//				'fields' => array('key'=>1, 'from'=> 1, 'to'=> 1),
//				'params' => array('unique'=> true, 'background'=> true),
//			),
//			'expected' => array('indexes' => array(array('name'=> '_id_')))
//		),
//		//check EnsureIndex
//		array('function' => 'checkEnsureIndex', 'collection' => 'balances',
//			'params'=> array(
//				'fields' => array('aid'=> 1, 'sid'=> 1, 'from'=> 1, 'to'=> 1, 'priority'=> 1),
//				'params' => array('unique'=> true, 'background'=> true),
//			),
//			'expected' => array('indexes' => array(array('name'=> '_id_'), array('name'=> 'aid_1_sid_1_from_1_to_1_priority_1', 'unique'=> true, 'background'=> true)),
//				'result'=> 'aid_1_sid_1_from_1_to_1_priority_1'
//			)
//		),
//		//check ensureUniqueIndex
//		array('function' => 'checkEnsureUniqueIndex', 'collection' => 'balances',
//			'params'=> array(
//				'fields' => array('aid'=> 1, 'sid'=> 1),
//				'params' => array('background'=> true),
//			),
//			'expected' => array('indexes' => array(array('name'=> '_id_'), array('name'=> 'aid_1_sid_1_from_1_to_1_priority_1', 'unique'=> true, 'background'=> true), array('unique'=> true)),
//				'result'=> 'aid_1_sid_1'
//			)
//		),
//		//check getIndexedFields
//		array('function' => 'checkGetIndexedFields', 'collection' => 'balances',
//			'expected' => array('fields' => array('_id', 'aid', 'sid', 'from', 'to', 'priority', 'aid', 'sid'))
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
	
//////////////////////////Mongodloid_Collection tests/////////////////////////////////
	
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
	
	protected function checkFindOne($test){
		$collection = $test['collection'];
		$id = new MongoId($test['params']['id']);//todo:: for now check mongo 
		$want_array = $test['params']['want_array'];
		$result = Billrun_Factory::db()->{$collection . 'Collection'}()->findOne($id, $want_array);
		if($want_array && !(is_array($result))){
			return false;
		}else if (!$want_array && !($result instanceof Mongodloid_Entity)){
			return false;
		}
		return true;
	}

//	protected function checkDropIndexes($test){
//		$collection = $test['collection'];
//		$result = Billrun_Factory::db()->{$collection . 'Collection'}()->dropIndexes();
//		$indexesAfter = Billrun_Factory::db()->{$collection . 'Collection'}()->getIndexes();
//		return count($indexesAfter) === 1 && $this->checkResult($result, $test['expected']['result']);
//	}
//	
//	protected function checkGetIndexes($test){
//		$collection = $test['collection'];
//		$indexes = Billrun_Factory::db()->{$collection . 'Collection'}()->getIndexes();
//		foreach ($indexes as $key => $index){
//			if(!$this->checkResult($index, $test['expected']['indexes'][$key])){
//				return false;
//			}
//		}
//		return true;
//	}
//	
//	protected function checkEnsureIndex($test){
//		$collection = $test['collection'];
//		$fields = $test['params']['fields'];
//		$params = $test['params']['params'];
//		$result = Billrun_Factory::db()->{$collection . 'Collection'}()->ensureIndex($fields, $params);
//		$indexes = Billrun_Factory::db()->{$collection . 'Collection'}()->getIndexes();
//		foreach ($indexes as $key => $index){
//			if(!$this->checkResult($index, $test['expected']['indexes'][$key])){
//				return false;
//			}
//		}
//		return $result === $test['expected']['result'];
//	}
//	
//	protected function checkEnsureUniqueIndex($test){
//		$collection = $test['collection'];
//		$fields = $test['params']['fields'];
//		$params = $test['params']['params'];
//		$result = Billrun_Factory::db()->{$collection . 'Collection'}()->ensureUniqueIndex($fields, $params);
//		$indexes = Billrun_Factory::db()->{$collection . 'Collection'}()->getIndexes();
//		foreach ($indexes as $key => $index){
//			if(!$this->checkResult($index, $test['expected']['indexes'][$key])){
//				return false;
//			}
//		}
//		return $result === $test['expected']['result'];
//	}
//	
//	protected function checkGetIndexedFields($test) {
//		$collection = $test['collection'];
//		$result = Billrun_Factory::db()->{$collection . 'Collection'}()->getIndexedFields();
//		return $result == $test['expected']['fields'];
//	}


	//todo:: check updateEntity
	
	////////////////////////////////////////////////////////////////////////////////////////
	
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



<?php
 
//tests for Mongodloid_Collection

require_once(APPLICATION_PATH . '/vendor/simpletest/simpletest/autorun.php');

define('UNIT_TESTING', 'true');

class Tests_Mongodloid extends UnitTestCase{
	
	use Tests_SetUp;
	
	public $message = '';
	protected $fail = ' <span style="color:#ff3385; font-size: 80%;"> failed </span><br>';
    protected $pass = ' <span style="color:#00cc99; font-size: 80%;"> passed </span><br>';
	protected $fails;
	protected $tests = array(
		//1 check success update 
		array('function' => 'checkUpdate', 'description' => 'check success update', 'collection' => 'lines',
			'params'=> array(
				'options' => array(),
				'values' => array('$set' => array('firstname' => 'or', 'lastname' => 'SHAASHUA')),
				'query' => array('_id' => '5aeee57b05e68c02d035e1f6')
			),
			'expected' => array('result' => array('n'=>1,  'nModified' => 1, 'updatedExisting' => true, 'ok' => 1 ),
				'dbValues'=> array('firstname' => 'or', 'lastname' => 'SHAASHUA', 'stamp'=> 'a305a9e1d842cf9d632010bf39dcb674')
			)
		),
		//2 check failed update - because already updated values
		array('function' => 'checkUpdate', 'description' => 'check failed update - because already updated values', 'collection' => 'lines',
			'params'=> array(
				'options' => array(),
				'values' => array('$set' => array('firstname' => 'or', 'lastname' => 'SHAASHUA')),
				'query' => array('_id' => '5aeee57b05e68c02d035e1f6')
			),
			'expected' => array('result' => array('n'=>1,'nModified' => 0, 'ok' => 1 ))
		),
		//3 check failed update - because not found query
		array('function' => 'checkUpdate', 'description' => 'check failed update - because not found query', 'collection' => 'lines',
			'params'=> array(
				'options' => array(),
				'values' => array('$set' => array('plan' => 'new_plan')),
				'query' => array('_id' => '5aeee57b05e68c02d035e111')
			),
			'expected' => array('result' => array('n'=>0, 'nModified' => 0, 'ok' => 1 ))
		),
		//4 check success multiple update
		array('function' => 'checkUpdate', 'description' => 'check success multiple update', 'collection' => 'lines',
			'params'=> array(
				'options' => array('multiple' => true),
				'values' => array('$set' => array('firstname' => 'dana')),
				'query' => array('type' => 'rr')
			),
			'expected' => array('result' => array('n'=>4,  'nModified' => 4, 'updatedExisting' => true, 'ok' => 1 ),
				'dbValues'=> array('firstname' => 'dana', 'type' => 'rr')
				)
		),
		//5 check success replaceOne
		array('function' => 'checkUpdate', 'description' => 'check success replaceOne', 'collection' => 'lines',
			'params'=> array(
				'options' => array(),
				'values' => array('firstname' => 'or', 'stamp' => 'a1fd85a9a16dcbc1f21d59afc8458cdf'),
				'query' => array('stamp' => 'a1fd85a9a16dcbc1f21d59afc8458cdf')
			),
			'expected' => array('result' => array('n'=>1,  'nModified' => 1, 'updatedExisting' => true, 'ok' => 1 ),
				'dbValues'=> array('firstname' => 'or', 'lastname' => null)
			)
		),
		//6 check collectionName - lines
		array('function' => 'checkCollectionName', 'description' => 'check collectionName', 'collection' => 'lines',
			'expected' => array('result' => 'lines')
		),
		//7 check collectionName - rates
		array('function' => 'checkCollectionName', 'description' => 'check collectionName', 'collection' => 'rates',
			'expected' => array('result' => 'rates')
		),
		//8 check findOne array result
		array('function' => 'checkFindOne', 'description' => 'check findOne - array result', 'collection' => 'subscribers',
			'params'=> array(
				'want_array' =>true,
				'id' => '5b34a76f05e68c6ae62d4a33'
			),
		),
		//9 check findOne entity result
		array('function' => 'checkFindOne', 'description' => 'check findOne - entity result', 'collection' => 'subscribers',
			'params'=> array(
				'want_array' =>false,
				'id' => '5b34a76f05e68c6ae62d4a33'
			),
		),
		//10 check count result 
		array('function' => 'checkCount',  'description' => 'check count', 'collection' => 'subscribers',
			'expected' => array('result' => 150)
		),
		//11 check sucess removeId
		array('function' => 'checkRemoveId', 'description' => 'check sucess removeId', 'collection' => 'subscribers',
			'params'=> array(
				'query' => array('_id' => '5aeee18f05e68c1a29770a9f')
			),
			'expected' => array('result' => array('n'=>1, 'ok' => 1 ))
		),
		//12 check fail removeId - because id not found
		array('function' => 'checkRemoveId', 'description' => 'check fail removeId - because id not found', 'collection' => 'subscribers',
			'params'=> array(
				'query' => array('_id' => '5aeee18f05e68c1a29770000')
			),
			'expected' => array('result' => array('n'=>0, 'ok' => 1 ))
		),
		//13 check sucess remove - just one
		array('function' => 'checkRemove', 'description' => 'check sucess remove - justOne', 'collection' => 'subscribers',
			'params'=> array(
				'query' => array('aid' => 27),
				'options' => array('justOne' => true),
			),
			'expected' => array('result' => array('n'=>1, 'ok' => 1 ))
		),
		//13 check sucess remove - multiplay
		array('function' => 'checkRemove', 'description' => 'check sucess remove - multiplay', 'collection' => 'subscribers',
			'params'=> array(
				'query' => array('aid' => 35),
				'options' => array(),
			),
			'expected' => array('result' => array('n'=>5, 'ok' => 1 ))
		),
		//14 check remove - not fount 
		array('function' => 'checkRemove', 'description' => 'check remove - not fount', 'collection' => 'subscribers',
			'params'=> array(
				'query' => array('aid' => 35),
				'options' => array(),
			),
			'expected' => array('result' => array('n'=>0, 'ok' => 1 ))
		),
		//15 check find operator-> limit 3 -> count fountOnly = true
		array('function' => 'checkFind', 'description' => 'check find operator-> limit 3 -> count fountOnly = true', 'collection' => 'subscribers',
			'params'=> array(
				'query' => array('aid' => array('$gt' => 35), 'type' => 'account'),
				'fields' => array('aid', '_id'),
			),
			'operationAfterFind' => array('limit' =>3, 'count' => true),
			'expected' => array('result' => 3)
		),
		//16 check find operator-> limit 3 -> count fountOnly = false
		array('function' => 'checkFind', 'description' => 'check find operator-> limit 3 -> count fountOnly = false', 'collection' => 'subscribers',
			'params'=> array(
				'query' => array('aid' => array('$gt' => 35), 'type' => 'account'),
				'fields' => array('aid', '_id'),
			),
			'operationAfterFind' => array('limit' =>3, 'count' => false),
			'expected' => array('result' => 49)
		),
		//17 check find operator-> count fountOnly = true
		array('function' => 'checkFind', 'description' => 'check find operator-> count fountOnly = true', 'collection' => 'subscribers',
			'params'=> array(
				'query' => array('aid' => array('$gt' => 35), 'type' => 'account'),
				'fields' => array('aid', '_id'),
			),
			'operationAfterFind' => array('count' => true),
			'expected' => array('result' => 49)
		),
		//18 check find operator- cursor sort by aid : -1 => limit 1 => getNext
		array('function' => 'checkFind', 'description' => 'check find operator- cursor sort by aid : 1 => limit 1 => getNext', 'collection' => 'subscribers',
			'params'=> array(
				'query' => array('aid' => array('$gt' => 35), 'type' => 'account'),
				'fields' => array('aid' =>1 , '_id'=>1),
			),
			'operationAfterFind' => array('sort' => array('aid' => -1), 'limit' => 1 , 'getNext' => null),
			'expected' => array('result' => array('aid' => 187501))
		),
		//19 check find operator- cursor sort by aid : 1 => skip 2 => next
		array('function' => 'checkFind', 'description' => 'check find operator- cursor sort by aid : 1 => skip 2 => next', 'collection' => 'subscribers',
			'params'=> array(
				'query' => array('aid' => array('$gt' => 35), 'type' => 'account'),
				'fields' => array('aid' =>1 , '_id'=>1),
			),
			'operationAfterFind' => array('sort' => array('aid' => 1), 'skip' => 2 , 'next' => null),
			'expected' => array('result' => array('aid' => 44))
		),
		//20 check exists
		array('function' => 'checkExists', 'description' => 'check if a certain entity exists in the collection', 'collection' => 'subscribers',
			'params'=> array(
				'query' => array('plan' => 'PLAN_F'),
			),
			'expected' => array('result' => true)
		),
		//21 check exists
		array('function' => 'checkExists', 'description' => 'check if a certain entity exists in the collection', 'collection' => 'subscribers',
			'params'=> array(
				'query' => array('plan' => 'PLAN_G'),
			),
			'expected' => array('result' => false)
		),
		//22 check aggregate operator- cursor current
		array('function' => 'checkAggregate', 'description' => 'check aggregate operator- cursor current', 'collection' => 'lines',
			'params'=> array(
				'query' => array(
					array(
						'$match' => array(
							"aid" => array('$in' => array(40))
						)
					),
					array(
						'$group' => array(
							'_id' => array('aid'=>'$aid'),
							'sum' => array('$sum' => '$usagev'),
						)
					)
				)
			),
			'operationAfterFind' => array('current' => null),
			'expected' => array('result' => array('sum' => 150))
		),
		//23 check aggregate operator- cursor setRawReturn TRUE => current
		array('function' => 'checkAggregate', 'description' => 'check aggregate operator- cursor setRawReturn TRUE => current', 'collection' => 'lines',
			'params'=> array(
				'query' => array(
					array(
						'$match' => array(
							"aid" => array('$in' => array(9, 40))
						)
					),
					array(
						'$group' => array(
							'_id' => array('aid'=>'$aid'),
							'sum' => array('$sum' => '$usagev'),
						)
					),
					array(
						'$sort' => array(
							'_id.aid' => 1
						)
					)
				)
			),
			'operationAfterFind' => array('setRawReturn'=> TRUE , 'current' => null),
			'expected' => array('result' => array('sum' => 100))
		),
//		//TODO:: need to restore also indexes after finisfh run unit test
//		//check dropIndexes
//		array('function' => 'checkDropIndexes', 'collection' => 'balances',
//			'expected' => array('result' => array('ok' => 1))),
//		//check getIndexes
//		array('function' => 'checkGetIndexes', 'collection' => 'balances',
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
			$this->message .= "test description : " . $test['description'];
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
			if(class_exists('MongoId')){
				$query['_id'] =  new MongoId($query['_id']);//for now check mongo
			}else{
				$query['_id'] =  new Mongodloid_Id($query['_id']);
			}
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
		return $this->basicCompare($result, $expectedResult, 'collection name');
	}
	
	protected function checkFindOne($test){
		$collection = $test['collection'];
		$id = new Mongodloid_Id($test['params']['id']);//todo:: for now check mongo 
		$want_array = $test['params']['want_array'];
		$result = Billrun_Factory::db()->{$collection . 'Collection'}()->findOne($id, $want_array);
		$expectedResultType = $want_array ? 'array' : 'entity';
		$resultType = $result instanceof Mongodloid_Entity ? 'entity' : (is_array($result) ? 'array' : '');
		return $this->basicCompare($resultType, $expectedResultType, 'result type');
	}

	protected function checkDropIndexes($test){
		$collection = $test['collection'];
		$result = Billrun_Factory::db()->{$collection . 'Collection'}()->dropIndexes();
		$indexesAfter = Billrun_Factory::db()->{$collection . 'Collection'}()->getIndexes();
		return $this->basicCompare(count($indexesAfter), 1, 'indexes after drop') && $this->checkResult($result, $test['expected']['result']);
	}
	
	protected function checkGetIndexes($test){
		$collection = $test['collection'];
		$indexes = Billrun_Factory::db()->{$collection . 'Collection'}()->getIndexes();
		foreach ($indexes as $key => $index){
			if(!$this->checkResult($index, $test['expected']['indexes'][$key])){
				return false;
			}
		}
		return true;
	}
	
	protected function checkEnsureIndex($test){
		$collection = $test['collection'];
		$fields = $test['params']['fields'];
		$params = $test['params']['params'];
		$result = Billrun_Factory::db()->{$collection . 'Collection'}()->ensureIndex($fields, $params);
		$indexes = Billrun_Factory::db()->{$collection . 'Collection'}()->getIndexes();
		foreach ($indexes as $key => $index){
			if(!$this->checkResult($index, $test['expected']['indexes'][$key])){
				return false;
			}
		}
		return $this->basicCompare($result, $test['expected']['result'], 'index name');
	}
	
	protected function checkEnsureUniqueIndex($test){
		$collection = $test['collection'];
		$fields = $test['params']['fields'];
		$params = $test['params']['params'];
		$result = Billrun_Factory::db()->{$collection . 'Collection'}()->ensureUniqueIndex($fields, $params);
		$indexes = Billrun_Factory::db()->{$collection . 'Collection'}()->getIndexes();
		foreach ($indexes as $key => $index){
			if(!$this->checkResult($index, $test['expected']['indexes'][$key])){
				return false;
			}
		}
		return $this->basicCompare($result, $test['expected']['result'], 'index name');
	}
	
	protected function checkGetIndexedFields($test) {//
		$collection = $test['collection'];
		$fields = Billrun_Factory::db()->{$collection . 'Collection'}()->getIndexedFields();
		return $this->basicCompare($fields, $test['expected']['fields'], 'the indexes fields');
	}
	
	protected function checkCount($test) {
		$collection = $test['collection'];
		$result = Billrun_Factory::db()->{$collection . 'Collection'}()->count();
		
		return $this->basicCompare($result, $test['expected']['result'], 'the number of documents in '. $collection. ' collection');
	}

	protected function checkRemoveId($test){
		$collection = $test['collection'];
		$query = $test['params']['query'];
		if(isset($query['_id'])){
			$query =  new Mongodloid_Id($query['_id']);
		}
		$result = Billrun_Factory::db()->{$collection . 'Collection'}()->removeId($query);
		return $this->checkResult($result, $test['expected']['result']);
	}
	
	protected function checkRemove($test){
		$collection = $test['collection'];
		$query = $test['params']['query'];
		$options = $test['params']['options'];
		if(isset($query['_id'])){
			$query['_id'] =  new Mongodloid_Id($query['_id']);
		}
		$result = Billrun_Factory::db()->{$collection . 'Collection'}()->remove($query, $options);
		return $this->checkResult($result, $test['expected']['result']);
	}
	
	protected function checkFind($test) {
		$collection = $test['collection'];
		$query = $test['params']['query'];
		$fields = $test['params']['fields'];
		if(isset($query['_id'])){
			if(class_exists('MongoId')){
				$query['_id'] =  new MongoId($query['_id']);//for now check mongo
			}else{
				$query['_id'] =  new Mongodloid_Id($query['_id']);
			}
		}
		$result = Billrun_Factory::db()->{$collection . 'Collection'}()->find($query, $fields);
		foreach ($test['operationAfterFind'] as $operation => $param){
			if(isset($param)){
				$result = $result->$operation($param);
			}else{
				$result = $result->$operation();
			}
		}
		if(!is_array($result)){
			return $this->basicCompare($result, $test['expected']['result'], 'result');
		}
		return $this->checkResult($result, $test['expected']['result']);
	}
	
	protected function checkExists($test) {
		$collection = $test['collection'];
		$query = $test['params']['query'];
		if(isset($query['_id'])){
			if(class_exists('MongoId')){
				$query['_id'] =  new MongoId($query['_id']);//for now check mongo
			}else{
				$query['_id'] =  new Mongodloid_Id($query['_id']);
			}
		}
		$result = Billrun_Factory::db()->{$collection . 'Collection'}()->exists($query);
		return $this->basicCompare((int)$result, (int)$test['expected']['result'], 'exists');	
	}
	
	protected function checkAggregate($test) {
		$collection = $test['collection'];
		$query = $test['params']['query'];
		$result = Billrun_Factory::db()->{$collection . 'Collection'}()->aggregate($query);
		foreach ($test['operationAfterFind'] as $operation => $param){
			if(isset($param)){
				$result = $result->$operation($param);
			}else{
				$result = $result->$operation();
			}
		}
		if(!is_array($result) && !($result instanceof Mongodloid_Entity)){
			return $this->basicCompare($result, $test['expected']['result'], 'result');
		}
		return $this->checkResult($result, $test['expected']['result']);
	}

	//todo:: check updateEntity
	
	////////////////////////////////////////////////////////////////////////////////////////
	
	protected function checkDb($collection, $query, $expectedDbValues) {
		$this->message .= '<br><b>compare db values</b><br>';
		$expectedMessage = '<p style="font: 14px arial; color: rgb(0, 0, 80);"> ' . '<b> Expected: </b>';
		$resultMessage = '<br><b> Result: </b> <br>';
		$ret = true;
		foreach ($expectedDbValues as $field => $expectedDbValue){
			$testResults = Billrun_Factory::db()->{$collection . 'Collection'}()->find($query);
			foreach ($testResults as $result){
				if(!isset($result[$field]) && $expectedDbValue === null){
					continue;
				}
				$expectedMessage .= '<br> ' . '— '. $field. ': ' . $expectedDbValue . '<br>';
				$resultMessage .= '<br> ' . '— '. $field. ': ' . $result[$field] ;
				if($result[$field] != $expectedDbValue){
					$resultMessage .= $this->fail . '<br>';
					$ret = false;
				}else{
					$resultMessage .= $this->pass . '<br>';
				}
			}
		}
		$this->message .= $expectedMessage . $resultMessage . '</p>';
		return $ret;
	}
	
	protected function checkResult($testResults, $expectedResults) {
		$this->message .= '<br><b>compare result</b><br>';
		$expectedMessage = '<p style="font: 14px arial; color: rgb(0, 0, 80);"> ' . '<b> Expected: </b>';
		$resultMessage = '<br><b> Result: </b> <br>';
		$ret = true;
		if($testResults instanceof Mongodloid_Entity){
			$testResults = $testResults->getRawData();
		}
		foreach ($expectedResults as $field => $expectedResult){ 
			$expectedMessage .= '<br> ' . '— '. $field. ': ' . $expectedResult . '<br>';
			$resultMessage .= '<br> ' . '— '. $field. ': ' . $testResults[$field] ;
			if($testResults[$field] != $expectedResult){
				$resultMessage .= $this->fail . '<br>';
				$ret = false;
			}else{
				$resultMessage .= $this->pass . '<br>';
			}
		}
		$this->message .= $expectedMessage . $resultMessage . '</p>';
		return $ret;
	}
	
	protected function basicCompare($testResult, $expectedResult , $title) {
		$ret = ($expectedResult === $testResult);
		$expectedResult = is_array($expectedResult) ? implode (", ", $expectedResult) : $expectedResult;
		$testResult = is_array($testResult) ? implode (", ", $testResult): $testResult;
		$this->message .= '<p style="font: 14px arial; color: rgb(0, 0, 80);"> ' . '<b> Expected: </b><br> ' .$title . ' : ' . $expectedResult;
        $this->message .= '<br><b> Result: </b> <br> ' . $title . ' : ' . $testResult;
		$this->message .= ($ret ? $this->pass : $this->fail);
		return $ret;
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



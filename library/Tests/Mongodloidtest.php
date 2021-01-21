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
		//14 check sucess remove - multiplay
		array('function' => 'checkRemove', 'description' => 'check sucess remove - multiplay', 'collection' => 'subscribers',
			'params'=> array(
				'query' => array('aid' => 35),
				'options' => array(),
			),
			'expected' => array('result' => array('n'=>5, 'ok' => 1 ))
		),
		//15 check remove - not fount 
		array('function' => 'checkRemove', 'description' => 'check remove - not fount', 'collection' => 'subscribers',
			'params'=> array(
				'query' => array('aid' => 35),
				'options' => array(),
			),
			'expected' => array('result' => array('n'=>0, 'ok' => 1 ))
		),
		//16 check find operator-> limit 3 -> count fountOnly = true
		array('function' => 'checkFind', 'description' => 'check find operator-> limit 3 -> count fountOnly = true', 'collection' => 'subscribers',
			'params'=> array(
				'query' => array('aid' => array('$gt' => 35), 'type' => 'account'),
				'fields' => array('aid', '_id'),
			),
			'operationAfterFind' => array('limit' =>3, 'count' => true),
			'expected' => array('result' => 3)
		),
		//17 check find operator-> limit 3 -> count fountOnly = false
		array('function' => 'checkFind', 'description' => 'check find operator-> limit 3 -> count fountOnly = false', 'collection' => 'subscribers',
			'params'=> array(
				'query' => array('aid' => array('$gt' => 35), 'type' => 'account'),
				'fields' => array('aid', '_id'),
			),
			'operationAfterFind' => array('limit' =>3, 'count' => false),
			'expected' => array('result' => 49)
		),
		//18 check find operator-> count fountOnly = true
		array('function' => 'checkFind', 'description' => 'check find operator-> count fountOnly = true', 'collection' => 'subscribers',
			'params'=> array(
				'query' => array('aid' => array('$gt' => 35), 'type' => 'account'),
				'fields' => array('aid', '_id'),
			),
			'operationAfterFind' => array('count' => true),
			'expected' => array('result' => 49)
		),
		//19 check find operator- cursor sort by aid : -1 => limit 1 => getNext
		array('function' => 'checkFind', 'description' => 'check find operator- cursor sort by aid : 1 => limit 1 => getNext', 'collection' => 'subscribers',
			'params'=> array(
				'query' => array('aid' => array('$gt' => 35), 'type' => 'account'),
				'fields' => array('aid' =>1 , '_id'=>1),
			),
			'operationAfterFind' => array('sort' => array('aid' => -1), 'limit' => 1 , 'getNext' => null),
			'expected' => array('result' => array('aid' => 187501))
		),
		//20 check find operator- cursor sort by aid : 1 => skip 2 => next
		array('function' => 'checkFind', 'description' => 'check find operator- cursor sort by aid : 1 => skip 2 => next', 'collection' => 'subscribers',
			'params'=> array(
				'query' => array('aid' => array('$gt' => 35), 'type' => 'account'),
				'fields' => array('aid' =>1 , '_id'=>1),
			),
			'operationAfterFind' => array('sort' => array('aid' => 1), 'skip' => 2 , 'next' => null),
			'expected' => array('result' => array('aid' => 44))
		),
		//21 check exists
		array('function' => 'checkExists', 'description' => 'check if a certain entity exists in the collection', 'collection' => 'subscribers',
			'params'=> array(
				'query' => array('plan' => 'PLAN_F'),
			),
			'expected' => array('result' => true)
		),
		//22 check exists
		array('function' => 'checkExists', 'description' => 'check if a certain entity exists in the collection', 'collection' => 'subscribers',
			'params'=> array(
				'query' => array('plan' => 'PLAN_G'),
			),
			'expected' => array('result' => false)
		),
		//23 check aggregate operator- cursor current
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
		//24 check aggregate operator- cursor setRawReturn TRUE => current
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
		//25 check save operator- without id
		array('function' => 'checkSave', 'description' => 'check save operator- without id', 'collection' => 'subscribers',
			'values'=> array(
				'aid' => 13852,
				'sid' => 0
			),
			'expected' => array('result' => true, 'dbValues'=> array('aid' => '13852'))
		),
		//26 check save operator- with id
		array('function' => 'checkSave', 'description' => 'check save operator- with id', 'collection' => 'subscribers',
			'values'=> array(
				'_id' => '5b34a78d05e77c6ae62d4a77',
				'aid' => 13853,
				'sid' => 0
			),
			'expected' => array('result' => true, 'dbValues'=> array('aid' => '13853', '_id' => '5b34a78d05e77c6ae62d4a77'))
		),
		//27 check findAndModify operator - retEntity= true
		array('function' => 'checkFindAndModify', 'description' => 'check findAndModify operator - retEntity= true', 'collection' => 'balances',
			'params'=> array(
				'query' => array(
					'sid' => array('$gt' => 10)
				),
				'update' => array('$set' => array('aidGt' => true)),
				'fields' => array(),
				'options' => array(),
				'retEntity' => true
			)
		),
		//28 check findAndModify operator - retEntity= false
		array('function' => 'checkFindAndModify', 'description' => 'check findAndModify operator - retEntity= false', 'collection' => 'balances',
			'params'=> array(
				'query' => array(
					'sid' => 10
				),
				'update' => array('$set' => array('findAndModdify' => true)),
				'fields' => array(),
				'options' => array(),
				'retEntity' => false
			)
		),
		//29 check batchInsert operator
		array('function' => 'checkBatchInsert', 'description' => 'check batchInsert operator', 'collection' => 'subscribers',
			'params'=> array(
				'documents' => array(
					array(
						'aid' => 10000,
						'sid' => 10001
					), array(
						'aid' => 10000,
						'sid' => 10002
					), array(
						'aid' => 10000,
						'sid' => 10003
					), 
					
				),
				'options' => array(),
			),
			'expected' => array('result' => array('ok' => 1.0, 'nInserted' => 3))
		),
		//30 check insert operator
		array('function' => 'checkInsert', 'description' => 'check insert operator', 'collection' => 'subscribers',
			'params'=> array(
				'document' => array(
					'aid' => 10004,
					'sid' => 10005	
				),
				'options' => array(),
			),
			'expected' => array('result' => array('ok' => 1.0))
		),
		//31 check createAutoIncForEntity operator - check min_id
		array('function' => 'checkAutoIncForEntity', 'description' => 'check createAutoIncForEntity operator - check min_id', 'collection' => 'billrun',
			'params'=> array(
				'values' => array(
					'_id' => '5aeee57b463d4f12759b7634',
					'aid' => 10006,
					'sid' => 10007	
				),
				'field' => 'invoice_id',
				'min_id' => 5,
			),
			'expected' => array('result' => 5)
		),
		//32 check createAutoIncForEntity operator - check increment
		array('function' => 'checkAutoIncForEntity', 'description' => 'check createAutoIncForEntity operator - check increment', 'collection' => 'billrun',
			'params'=> array(
				'values' => array(
					'_id' => '5aeee57b463d4f12759b7680',
					'aid' => 10008,
					'sid' => 10009	
				),
				'field' => 'invoice_id',
				'min_id' => 5,
			),
			'expected' => array('result' => 6)
		),
		//33 check createAutoIncForEntity operator - check value exist
		array('function' => 'checkAutoIncForEntity', 'description' => 'check createAutoIncForEntity operator - check value exist', 'collection' => 'billrun',
			'params'=> array(
				'values' => array(
					'_id' => '5aeee57b463d4f12759b7681',
					'aid' => 10010,
					'sid' => 10011,
					'invoice_id' => 20
				),
				'field' => 'invoice_id',
				'min_id' => 5,
			),
			'expected' => array('result' => 20)
		),
		//34 check distinct operator - without query
		array('function' => 'checkDistinct', 'description' => 'check distinct operator- without query', 'collection' => 'lines',
			'params'=> array(
				'item' => 'source',
				'query' => array()
			),
			'expected' => array('result' => array('realtime', 'billrun'))
		),
		//35 check distinct operator
		array('function' => 'checkDistinct', 'description' => 'check distinct operator', 'collection' => 'lines',
			'params'=> array(
				'item' => 'billrun',
				'query' => array('billrun'=> array('$lte' =>'201807'))
			),
			'expected' => array('result' => array('201806', '201807'))
		),
		//36 check distinct operator - use date query
		array('function' => 'checkDistinct', 'description' => 'check distinct operator- use date query', 'collection' => 'lines',
			'params'=> array(
				'item' => 'billrun',
				'query' => array('urt' => array('$gte' => '2018-05-13T07:10:10Z'))
			),
			'expected' => array('result' => array('201806', '201807', '202003'))
		),
		
//		//TODO:: need to restore also indexes after finish run unit test
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
		
		//37 check Create MongodloidDate With String
		array('function' => 'checkCreateMongodloidDateWithString', 'description' => 'check Create MongodloidDate With String',
			'params'=> array(
				'sec' => '1234567890',
				'usec' => '123456'
			),
			'expected' => array('result' => array('sec' => 1234567890,'usec' => 123000))
		),
		//38 check Create MongodloidDate With BsonDate
		array('function' => 'checkCreateMongodloidDateWithBsonDate', 'description' => 'check Create MongodloidDate With BsonDate',
			'params'=> array(
				'bsonDate' => 1234567890123,
			),
			'expected' => array('result' => array('sec' => 1234567890,'usec' => 123000))
		),
		//39 check MongodloidDate Convert To Bson And Create With String
		array('function' => 'checkMongodloidDateConvertToBsonAndCreateWithString', 'description' => 'check MongodloidDate Convert To Bson And Create With String',
			'params'=> array(
				'sec' => '1234567890',
				'usec' => '123456'
			),
			'expected' => array('result' => array('date' => '1234567890123'))
		),
		//40 check MongodloidDate Convert To Bson And Create With Bson Date
		array('function' => 'checkMongodloidDateConvertToBsonAndCreateWithBsonDate', 'description' => 'check MongodloidDate Convert To Bson And Create With Bson Date',
			'params'=> array(
				'bsonDate' => 1234567890123,
			),
			'expected' => array('result' => array('date' => '1234567890123'))
		),
		//41 check Create MongodloidId With String
		array('function' => 'checkCreateMongodloidIdWithString', 'description' => 'check Create MongodloidId With String',
			'params'=> array(
				'id' => '54203e08d51d4a1f868b456e',
			)
		),
		//42 check Create MongodloidId With Invalid String
		array('function' => 'checkCreateMongodloidIdWithInvalidString', 'description' => 'check Create MongodloidId With Invalid String'),
		//41 check Create MongodloidId With ObjectId
		array('function' => 'checkCreateMongodloidIdWithObjectId', 'description' => 'check Create MongodloidId With ObjectId',
			'params'=> array(
				'id' => '54203e08d51d4a1f868b456e',
			)
		),
		//43 check Create MongodloidId Without Parameter
		array('function' => 'checkCreateMongodloidIdWithoutParameter', 'description' => 'check Create MongodloidId Without Parameter'),
		
		//44 check Mongodloid Regex Convert To Bson
		array('function' => 'checkMongodloidRegexConvertToBson', 'description' => 'check Mongodloid Regex Convert To Bson',
			'params'=> array(
				'regex' => '/abc/i',
			),
			'expected' => array('result' => array('regex' => 'abc', 'flags' => 'i'))
		),
		//45 check Mongodloid Regex Convert To Bson And Create With Bson Type
		array('function' => 'checkMongodloidRegexConvertToBsonAndCreateWithBsonType', 'description' => 'check Mongodloid Regex Convert To Bson And Create With Bson Type',
			'params'=> array(
				'pattern' => 'abc',
				'flags' => 'i'
			),
			'expected' => array('result' => array('regex' => 'abc', 'flags' => 'i'))
		),
		//46 check MongodloidRef Create 
		array('function' => 'checkMongodloidRefCreate', 'description' => 'check MongodloidRef Create',
			'params'=> array(
				'ref' => 'foo',
			)
		),
		//47 check MongodloidRef Create with database
		array('function' => 'checkMongodloidRefCreate', 'description' => 'check MongodloidRef Create with database',
			'params'=> array(
				'ref' => 'foo',
				'database' => 'database',
			)
		),
		
	);


	public function __construct($label = 'Mongodloid layer') {
         parent::__construct("test mongodloid layer");
         $this->construct(basename(__FILE__, '.php'), ['counters']);
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
		$this->changeValues($query);
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
		$this->changeValues($query);
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
		$this->changeValues($query);
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
	
	protected function checkSave($test) {
		$collection = $test['collection'];
		$values = $test['values'];
		$this->changeValues($values);
		$result = Billrun_Factory::db()->{$collection . 'Collection'}()->save(new Mongodloid_Entity($values));
		return $this->basicCompare($result, $test['expected']['result'], 'result') && 
			$this->checkDb($collection, $values, $test['expected']['dbValues']);

	}
	protected function checkFindAndModify($test){
		$collection = $test['collection'];
		$query = $test['params']['query'];
		$fields = $test['params']['fields'];
		$update = $test['params']['update'];
		$options = $test['params']['options'];
		$retEntity = $test['params']['retEntity'];
		$result = Billrun_Factory::db()->{$collection . 'Collection'}()->findAndModify($query, $update,$fields, $options, $retEntity);
		$expectedResultType = $retEntity ? 'entity' : 'array';
		$resultType = $result instanceof Mongodloid_Entity ? 'entity' : (is_array($result) ? 'array' : '');
		return $this->basicCompare($resultType, $expectedResultType, 'result type');
	}
	
	protected function checkBatchInsert($test){
		$collection = $test['collection'];
		$documents = $test['params']['documents'];
		$options = $test['params']['options'];
		$result = Billrun_Factory::db()->{$collection . 'Collection'}()->batchInsert($documents, $options);
		$res = true;
		foreach ($documents as $doc){
			$res = $this->checkDb($collection, $doc, $doc);
		}
		return $this->checkResult($result, $test['expected']['result']) && $res;
	}
	
	protected function checkInsert($test){
		$collection = $test['collection'];
		$document = $test['params']['document'];
		$options = $test['params']['options'];
		$result = Billrun_Factory::db()->{$collection . 'Collection'}()->insert($document, $options);
		return $this->checkResult($result, $test['expected']['result']) && 
			$this->checkDb($collection, $document, $document);
	}
	
	protected function checkAutoIncForEntity($test) {
		$collection = $test['collection'];
		$values = $test['params']['values'];
		$this->changeValues($values);
		$field = $test['params']['field'];
		$min_id = $test['params']['min_id'];
		$result = Billrun_Factory::db()->{$collection . 'Collection'}()->createAutoIncForEntity(new Mongodloid_Entity($values), $field, $min_id);
		return $this->basicCompare($result, $test['expected']['result'], $field);
	}
	
	protected function checkDistinct($test) {
		$collection = $test['collection'];
		$item = $test['params']['item'];
		$query = $test['params']['query'];
		$this->changeValues($query);
		$result = Billrun_Factory::db()->{$collection . 'Collection'}()->distinct($item, $query);
		return $this->basicCompare($result, $test['expected']['result'], $item);
	}

	
//////////////////////////Mongodloid_Date tests//////////////////////////////////////////
	
	
	protected function checkCreateMongodloidDateWithString($test)
    {
		$class = $this->getMongodloidDateClass();
        $date = new $class($test['params']['sec'], $test['params']['usec']);
		return $this->basicCompare($date->sec, $test['expected']['result']['sec'], 'sec') &&
			$this->basicCompare($date->usec, $test['expected']['result']['usec'], 'usec');
    }
	
	protected function checkCreateMongodloidDateWithBsonDate($test)
    {

		$class = $this->getMongodloidDateClass();
        $bsonDate = new \MongoDB\BSON\UTCDateTime($test['params']['bsonDate']);
        $date = new $class($bsonDate);
		return $this->basicCompare($date->sec, $test['expected']['result']['sec'], 'sec') &&
			$this->basicCompare($date->usec, $test['expected']['result']['usec'], 'usec');
    }
	
	protected function getMongodloidDateClass() {
		if(@class_exists('MongoDate')){
			$class = 'MongoDate';
		}else{
			$class = 'Mongodloid_Date';
		}
		return $class;
	}
	
    public function checkMongodloidDateConvertToBsonAndCreateWithString($test)
    {
		
		$class = $this->getMongodloidDateClass();
        $date = new $class($test['params']['sec'], $test['params']['usec']);
        $dateTime = $date->toDateTime();

        $bsonDate = $date->toBSONType();
        $bsonDateTime = $bsonDate->toDateTime();

        // Compare timestamps to avoid issues with DateTime
        $timestamp = $dateTime->format('U') . '.' . $dateTime->format('U');
        $bsonTimestamp = $bsonDateTime->format('U') . '.' . $bsonDateTime->format('U');
		
		return $this->basicCompare((string) $bsonDate, $test['expected']['result']['date'], 'date') &&
			$this->basicCompare($bsonTimestamp, $timestamp, 'timestamp') &&
			$this->basicCompare(get_class($bsonDate), 'MongoDB\BSON\UTCDateTime', 'instance');
    }
	
	public function checkMongodloidDateConvertToBsonAndCreateWithBsonDate($test)
    {
		
		$class = $this->getMongodloidDateClass();
        $bsonDate = new \MongoDB\BSON\UTCDateTime($test['params']['bsonDate']);
        $date = new $class($bsonDate);
        $dateTime = $date->toDateTime();

        $newBsonDate = $date->toBSONType();
        $bsonDateTime = $newBsonDate->toDateTime();

        // Compare timestamps to avoid issues with DateTime
        $timestamp = $dateTime->format('U') . '.' . $dateTime->format('U');
        $bsonTimestamp = $bsonDateTime->format('U') . '.' . $bsonDateTime->format('U');
		
		return $this->basicCompare((string) $newBsonDate, $test['expected']['result']['date'], 'date') &&
			$this->basicCompare($bsonTimestamp, $timestamp, 'timestamp') &&
			$this->basicCompare(get_class($newBsonDate), 'MongoDB\BSON\UTCDateTime', 'instance');
    }

//////////////////////////Mongodloid_Id tests//////////////////////////////////////////
	
	protected function getMongodloidIdClass() {
		if(@class_exists('MongoId')){
			$class = 'MongoId';
		}else{
			$class = 'Mongodloid_Id';
		}
		return $class;
	}

	protected function checkCreateMongodloidIdWithString($test)
    {
        $original = $test['params']['id'];
		$class = $this->getMongodloidIdClass();
        $id = new $class($original);
		return $this->basicCompare((string) $id, $original, 'id');
    }

    protected function checkCreateMongodloidIdWithInvalidString()
    {
		$class = $this->getMongodloidIdClass();
		try{
			new $class('invalid');
			return false;
		} catch (Exception $e){
			return true; 
		}
    }

    protected function checkCreateMongodloidIdWithObjectId($test)
    {
		$class = $this->getMongodloidIdClass();
        $original = $test['params']['id'];
        $objectId = new  MongoDB\BSON\ObjectID($original);
        $id = new $class($objectId);
		return $this->basicCompare((string) $id, $original, 'id');
    }
	
	protected function checkCreateMongodloidIdWithoutParameter()
    {
		$class = $this->getMongodloidIdClass();
        $id = new $class();
        $stringId = (string) $id;
        $serialized = serialize($id);
        $unserialized = unserialize($serialized);
        $json = json_encode($id);
		return $this->basicCompare(strlen($stringId), 24,  'id length') &&
			$this->basicCompare($stringId, $id->{'$id'}, 'id') &&
			$this->basicCompare($serialized, sprintf('C:'. strlen($class).':"' . $class . '":24:{%s}', $stringId), 'serialize') &&
			$this->basicCompare((string) $unserialized, $stringId, 'unserialize') &&
				$this->basicCompare(get_class($unserialized) , $class, 'instance') &&
				$this->basicCompare($json, sprintf('{"$id":"%s"}', $stringId), 'json');
    }



//////////////////////////Mongodloid_Regex tests//////////////////////////////////////////


	protected function getMongodloidRegexClass() {
		if(@class_exists('MongoRegex')){
			$class = 'MongoRegex';
		}else{
			$class = 'Mongodloid_Regex';
		}
		return $class;
	}
	

    protected function checkMongodloidRegexConvertToBson($test)
    {
        $class = $this->getMongodloidRegexClass();
		$regexString = $test['params']['regex'];
		$regex = new $class($regexString);

		$bsonRegex = $regex->toBSONType();
		$instance = $bsonRegex instanceof MongoDB\BSON\Regex;
		return $this->basicCompare($bsonRegex->getPattern(), $test['expected']['result']['regex'], 'regex') &&
			$this->basicCompare($bsonRegex->getFlags(), $test['expected']['result']['flags'], 'flags') &&
			$this->basicCompare((string) $regex, $regexString,  'regex string') && 
			$this->basicCompare($instance, true, 'instanceof MongoDB\BSON\Regex');

    }
	
	protected function checkMongodloidRegexConvertToBsonAndCreateWithBsonType($test)
    {
        $class = $this->getMongodloidRegexClass();
		$pattern = $test['params']['pattern'];
		$flags = $test['params']['flags'];
		$bsonRegex = new \MongoDB\BSON\Regex($pattern, $flags);
		$regex = new $class($bsonRegex);

		$newBsonRegex = $regex->toBSONType();
		$instance = $newBsonRegex instanceof MongoDB\BSON\Regex;
		return $this->basicCompare($newBsonRegex->getPattern(), $test['expected']['result']['regex'], 'regex') &&
			$this->basicCompare($newBsonRegex->getFlags(), $test['expected']['result']['flags'], 'flags') &&
			$this->basicCompare((string) $newBsonRegex, (string) $regex,  'regex string') && 
			$this->basicCompare($instance, true, 'instanceof MongoDB\BSON\Regex');

    }





//////////////////////////Mongodloid_Ref tests//////////////////////////////////////////

	protected function getMongodloidRefClass() {
		if(@class_exists('MongoDBRef')){
			$class = 'MongoDBRef';
		}else{
			$class = 'Mongodloid_Ref';
		}
		return $class;
	} 
	
	public function checkMongodloidRefCreate($test)
    {
		$idClass = $this->getMongodloidIdClass();
        $id = new $idClass();
		$refClass = $this->getMongodloidRefClass();
		$refParam = $test['params']['ref'];
		$database = $test['params']['database'] ?? null;
		if(!isset($database)){
			$ref = $refClass::create($refParam, $id);
			return $this->checkResult($ref, ['$ref' => $refParam, '$id' => $id]);
		}else{
			$ref = $refClass::create($refParam, $id, $database);
			return $this->checkResult($ref, ['$ref' => $refParam, '$id' => $id, '$db' => $database]);
		}
    }


	///////////////////////////////////////////////////////////////////////////////////
	
	
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
	
	protected function changeValues(&$values) {
		if(isset($values['_id'])){
			if(@class_exists('MongoId')){
				$values['_id'] =  new MongoId($values['_id']);//for now check mongo
			}
			else{
				$values['_id'] =  new Mongodloid_Id($values['_id']);
			}
		}
		$datesFields =['urt', 'time'];
		foreach ($datesFields as $field){
			if(isset($values[$field])){
				if(@class_exists('MongoDate')){
					if(is_array($values[$field])){
						$key = key($values[$field]);
						$values[$field][$key] =  new MongoDate(strtotime($values[$field][$key]));//for now check mongo
					}else{
						$values[$field] =  new MongoDate(strtotime($values[$field]));//for now check mongo
					}
				}
				else{
					if(is_array($values[$field])){
						$key = key($values[$field]);
						$values[$field][$key] =  new Mongodloid_Date(strtotime($values[$field][$key]));
					}else{
						$values[$field] =  new Mongodloid_Date(strtotime($values[$field]));
					}
				}
			}
		}
	}
}




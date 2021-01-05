<?php
 
//tests for Mongodloid_Collection

require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');

define('UNIT_TESTING', 'true');

class Tests_Mongodloid extends UnitTestCase{
	
	use Tests_SetUp;
	
	public $message = '';
	protected $fails;
	protected $tests = array(
		array('function' => 'checkSuccessUpdateOperation', 
			'params'=> array(
				'options' => array(),
				'values' => array('$set' => array('firstname' => 'or', 'lastname' => 'SHAASHUA')),
				'query' => array('_id' => '5aeee57b05e68c02d035e1f6')
			),
			'expected' => array('result' => array('nModified' => 1, 'updatedExisting' => true, 'ok' => 1 ))
		));
	
	
	public function __construct($label = 'Mongodloid layer') {
         parent::__construct("test mongodloid layer");
         $this->plansCol = Billrun_Factory::db()->plansCollection();
         $this->linesCol = Billrun_Factory::db()->linesCollection();
         $this->servicesCol = Billrun_Factory::db()->servicesCollection();
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
		$this->cleanCollection($this->collectionToClean);
		$this->restoreCollection();
		print($this->message);
	}
	
	protected function checkSuccessUpdateOperation($test){
		$query = $test['params']['query'];
		if($query['_id']){
			$query['_id'] =  new MongoId($query['_id']);//for now check mongo
		}
		$options = $test['params']['options'];
		$values = $test['params']['values'];
		$result = $this->linesCol->update($query, $values, $options);
		return $this->checkResult($result, $test['expected']['result']);
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



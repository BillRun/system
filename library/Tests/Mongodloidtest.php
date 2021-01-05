<?php
 
//tests for Mongodloid_Collection

require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');

define('UNIT_TESTING', 'true');

class Tests_Mongodloid {
	protected $linesCol;
	use Tests_SetUp;
	
	public function __construct($label = 'Mongodloid layer') {
         parent::__construct("test mongodloid layer");
         $this->plansCol = Billrun_Factory::db()->plansCollection();
         $this->linesCol = Billrun_Factory::db()->linesCollection();
         $this->servicesCol = Billrun_Factory::db()->servicesCollection();
         $this->construct(basename(__FILE__, '.php'), []);
         $this->setColletions();
         $this->loadDbConfig();
		 $this->mongodloidCollectionTest = new Mongodloid_Collection_Test();
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
		$this->mongodloidCollectionTest->TestPerform();
		
		$this->restoreColletions();
	}
	
	
	
}
abstract class Mongodloid_Tests extends UnitTestCase{
	public $message = '';
	protected $fails;
	/**
	 * the function is runing all the test cases  
	 * print the test result
	 * and restore the original data 
	 */
	public function TestPerform() {
		$this->message .= "<span>test mongodloid " .$this->getMongodloidTestType() . ' type </span><br>';
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
		print_r($this->message);
	}
	
	abstract protected function getMongodloidTestType();
}
class Mongodloid_Collection_Test extends Mongodloid_Tests{
	
	protected $tests = array(
		array('function' => 'checkSuccessUpdateOperation',
			'params'=> array(
				'options' => array(),
				'values' => array('$set' => array('firstname' => 'or','lastname' => 'shaashua')),
				'query' => array('_id' => '5e4eb5c9cd4acc7dee06752f')
			),
			'expected' => array()
		));
	protected function checkSuccessUpdateOperation($test){
		$this->linesCol = Billrun_Factory::db()->linesCollection();
		$query = $test['params']['query'];
		$options = $test['params']['options'];
		$values = $test['params']['values'];
		$result = $this->linesCol->update($query, $options, $values);
		return true;
	}
	
	protected function getMongodloidTestType(){
		return 'collection';
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



<?php
 
//tests for Mongodloid_Collection

require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');

define('UNIT_TESTING', 'true');

class Tests_Mongodloid extends UnitTestCase {
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
	 
	 
	
}
class Mongodloid_Tests{
	/**
	 * the function is runing all the test cases  
	 * print the test result
	 * and restore the original data 
	 */
	public function TestPerform() {

	}
}
class Mongodloid_Collection_Test {
	protected $tests = array(
		array('function' => 'checkUpdateOperation',
			'params'=> array(
				'options' => array(),
				'values' => array(),
				'query' =>array()
			),
			'expected' => array()
		));
	protected function checkUpdateOperation(){
		
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



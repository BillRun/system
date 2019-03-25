<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
/**
 * test the Billrun_Plan::getMonthsDiff function
 *
 * @author yossi
 */
require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');

define('UNIT_TESTING', 'true');

class Tests_Monthsdifftest extends UnitTestCase {
	use Tests_SetUp;
	
	protected $fail = ' <span style="color:#ff3385; font-size: 80%;"> failed </span></br>';
	protected $pass = ' <span style="color:#00cc99; font-size: 80%;"> passed </span></br>';
	public $message;
	public $epsilon = 0.000001;
	protected $tests = array(
		array('test number' => 1, 'from' => "2019-02-11", 'to' => "2019-02-12", 'expected_result' => '0.07142857142'),
		array('test number' => 2, 'from' => "2017-11-01", 'to' => "2018-11-01", 'expected_result' => '12.03333333333'),
		array('test number' => 3, 'from' => "2017-11-01", 'to' => "2017-11-01", 'expected_result' => '0.033333'),
		array('test number' => 4, 'from' => "2019-03-01", 'to' => "2019-03-28", 'expected_result' => '0.90322580645'),
		array('test number' => 5, 'from' => "2019-03-01", 'to' => "2019-03-10", 'expected_result' => '0.32258064516'),
		array('test number' => 6, 'from' => "2019-01-01", 'to' => "2019-12-31", 'expected_result' => '12'),
		array('test number' => 7, 'from' => "2019-01-01", 'to' => "2119-01-01", 'expected_result' => '1200.03225806451'),
		array('test number' => 8, 'from' => "2018-12-31", 'to' => "2019-01-01", 'expected_result' => '0.064516129'),
		array('test number' => 9, 'from' => "2019-01-31", 'to' => "2019-02-01", 'expected_result' => '0.067972351'),
		array('test number' => 10, 'from' => "2019-02-1", 'to' => "2020-02-29", 'expected_result' => '13'),
		array('test number' => 11, 'from' => "2019-02-1", 'to' => "2020-02-28", 'expected_result' => '12.96551724'),
	);

	public function __construct($label = false) {
		parent::__construct("test MonthsDifftest");
	}

	/**
	 * the function is runing all the test cases  
	 * print the test result
	 * 
	 */
	public function TestPerform() {
		$nbsp='&nbsp &nbsp &nbsp ';
		foreach ($this->tests as $row) {
			$returnval = $this->compare($row);
			$this->assertTrue($returnval[0]);
			$this->message .= '<p style="border-top: 1px dashed black;"></p>';
			$this->message .= 'test number : ' . $row['test number'].'<br>';
			$this->message .= 'from : ' .$row['from'].'<br>';
			$this->message .= 'to'.$nbsp.':' .$row['to'];
			$this->message .= '<p style="font: 14px arial; color: rgb(0, 0, 80);"> ' . '<b> Expected: </b></br> ' . '— diff : ' . $row['expected_result'] ;
			$this->message .= '</br><b> Result: </b> <br>';
			$this->message.='— diff : ' .$returnval[2];
		}
		print_r($this->message);
	}
   /**
    * 
    * @param array $row the test case
    * @return array with test result(bool) ,diff,and message
    */
	public function compare($row) {
		$passed = TRUE;
		$diff = $this->runT($row['from'], $row['to']);
		if (Billrun_Util::isEqual($diff, $row['expected_result'], $this->epsilon)) {
			
			$message =  $diff . $this->pass;
		} else {
			$passed = false;
			$message = $diff . $this->fail;
		}
		return [$passed,$diff,$message];
	}

	/**
	 * 
	 * @param string $from
	 * @param string $to
	 * @return number(int/float)
	 */
	protected function runT($from, $to) {
		return Billrun_Plan::getMonthsDiff($from, $to);
	}

}

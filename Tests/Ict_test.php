<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
/**
 * All Billing calculators for  billing lines with uf.
 *
 * @package  calculators
 * @since    0.5
 */
require_once(APPLICATION_PATH . '/library/Tests/Itc_test_cases.php');
require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');

define('UNIT_TESTING', 'true');

class Tests_Icttest extends UnitTestCase {

	use Tests_SetUp;

	protected  $fails;
	protected $message ='';
	protected $fail = ' <span style="color:#ff3385; font-size: 80%;"> failed </span> <br>';
	protected $pass = ' <span style="color:#00cc99; font-size: 80%;"> passed </span> <br>';
	protected $rows = [];

	public function __construct($label = false) {
		//for PHP<7.3
		if (!function_exists('array_key_first')) {

			function array_key_first(array $arr) {
				foreach ($arr as $key => $unused) {
					return $key;
				}
				return NULL;
			}

		}
		parent::__construct("test Itc");
		date_default_timezone_set('Asia/Jerusalem');
		$this->TestsC = new Itc_test_cases();
		$this->Tests = $this->TestsC->tests();
		$this->configCol = Billrun_Factory::db()->configCollection();
		$this->construct(basename(__FILE__, '.php'), ['queue']);
		$this->setColletions();
		$this->loadDbConfig();
	}

	public function loadDbConfig() {
		Billrun_Config::getInstance()->loadDbConfig();
	}

	/**
	 * test runer
	 */
	public function testUpdateRow() {
		foreach ($this->Tests as $key => $row) {
			$data = $this->process($row);
			$id = array_key_first($row['data']);
			$this->message .= "<span id={$row['test_num']}>test number : " . $row['test_num'] . '</span><br>';
			$testFail = $this->assertTrue($this->compareExpected($key, $row['expected'], $data[$id]));
			if (!$testFail) {
				$this->fails .= "| <a href='#{$row['test_num']}'>{$row['test_num']}</a> | ";
			}
			$this->message .= '<p style="border-top: 1px dashed black;"></p>';
		}
		if ($this->fails) {
			$this->message .= 'links to fail tests : <br>' . $this->fails;
		}
		print_r($this->message);
		$this->restoreColletions();
	}

	/**
	 * process the given line 
	 * @param type $row
	 * @return row data 
	 */
	protected function process($row) {
		$id = array_key_first($row['data']);
		$options = array(
			'type' => $row['data'][$id]["type"]
		);
		$data['data'] = $row['data'];
		$fileType = Billrun_Factory::config()->getFileTypeSettings($options['type'], true);
		$fileType['type'] = $row['data'][$id]["type"];
		$usage = new Billrun_Processor_Usage($fileType);
		$newRow = $usage->getBillRunLine($data['data'][$id]['uf']);
		$data['data'][$id] = $newRow;
		$queueLine = $newRow;
		$queueLine['calc_name'] = false;
		$queueLine['calc_time'] = false;
		$queueLine['in_queue_since'] = new MongoDate(1608660082);
		$usage->setQueueRow($queueLine);
		$queueCalculators = new Billrun_Helpers_QueueCalculators($options);
		$queueCalculators->run($usage, $data);
		return $data['data'];
	}

	/**
	 * compare between expected and actual result
	 * @param type $key
	 * @param type $expected
	 * @param type $data
	 * @return boolean
	 */
	protected function compareExpected($key, $expected, $data) {
		$result = true;
		foreach ($expected as $expectedKey => $expectedLine) {
			foreach ($expectedLine as $k => $v) {
				$this->message .= '<b>test filed</b> : ' . $k . ' </br>	Expected : ' . $v . '</br>';
				$this->message .= '	Result : </br>';
				$nested = false;
				if (strpos($k, '.')) {
					$DataField = Billrun_Util::getIn($data, $k);
					$nestedKey = explode('.', $k);
					$k = end($nestedKey);
					$nested = true;
				}
				$DataField = $nested ? $DataField : $data[$k];
				if (!$nested) {
					if (empty(array_key_exists($k, $data))) {
						$this->message .= ' 	-- the result key isnt exists' . $this->fail;
						$result = false;
					}
				}

				if (empty($DataField)) {
					$this->message .= '-- the result is empty' . $this->fail;
					$result = false;
				}
				if ($DataField != $v) {
					$this->message .= '	-- the result is diffrents from expected : ' . $DataField . $this->fail;
					$result = false;
				}
				if ($DataField == $v) {
					$this->message .= '	-- the result is equel to expected : ' . $DataField . $this->pass;
				}
			}
		}
		return $result;
	}

}

/*DONT DELETE *****  its a way for proccess with insert to the DB(Maybe it will be consumed in the future)*/

//		        $options = Billrun_Factory::config()->getFileTypeSettings('Preprice_Dynamic', true);
//			$options = Billrun_Factory::config()->getFileTypeSettings('Preprice_Dynamic', true);
//			$parserFields = $options['parser']['structure'];
//			foreach ($parserFields as $field) {
//				if (isset($field['checked']) && $field['checked'] === false) {
//					if (strpos($field['name'], '.') !== false) {
//						$splittedArray = explode('.', $field['name']);
//						$lastValue = array_pop($splittedArray);
//						Billrun_Util::unsetIn($data['data'][$id]['uf'], $splittedArray, $lastValue);
//					} else {
//						unset($data['data'][$id]['uf'][$field['name']]);
//					}
//				}
//			}
//			//$options['parser'] = 'none';
//			$options['type'] = 'Preprice_Dynamic';
//			$processor = Billrun_Processor::getInstance($options);
//			if ($processor) {
//				$processor->addDataRow($data['data'][$id]);
//				$processor->process($options);
//				$data = $processor->getData()['data'];
//				$data = current($processor->getAllLines());
//			}

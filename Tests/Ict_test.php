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

	protected $fails;
	protected $message = '';
	public $application_path = APPLICATION_PATH;
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
		$request = new Yaf_Request_Http;
		$this->useExistingConfig = $request->get('useExistingConfig');
		date_default_timezone_set('Asia/Jerusalem');
		$this->TestsC = new Itc_test_cases();
		$this->Tests = $this->TestsC->tests();
		$this->configCol = Billrun_Factory::db()->configCollection();
		$this->linesCol = Billrun_Factory::db()->linesCollection();
		$this->construct(basename(__FILE__, '.php'), ['queue', 'lines']);
		$this->setColletions($this->useExistingConfig);
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
			$this->test_num = $row['test_num'];
			$this->addCaseToLog();
			$data = $this->process($row);
			$this->message .= "<span id={$row['test_num']}>test number : " . $row['test_num'] . '</span><br>';
			$lines = Billrun_Factory::db()->linesCollection()->query()->cursor();
			$data = [];
			foreach ($lines as $line) {
				if ($line->getRawData()) {
					$data[] = $line->getRawData();
				}
			}
			Billrun_Factory::db()->linesCollection()->remove(["type" => "ICT"]);
			Billrun_Factory::db()->logCollection()->remove(["stamp" => $this->stamp]);
			$testFail = $this->assertTrue($this->compareExpected($key, $row['expected'], $data));
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
		$options = array(
			'type' => $row['file_type']
		);

		$fileType = Billrun_Factory::config()->getFileTypeSettings($options['type'], true);
		$processor = Billrun_Processor::getInstance($options);
		if ($processor) {
			$processor->process_files($options);
		}
		exec("cp $this->application_path/library/Tests/Ict_testData/backup/$this->test_num  $this->application_path/library/Tests/Ict_testData/files/$this->test_num");
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
		$sort = function ($a, $b) {
			if ($a['usaget'] != $b['usaget']) {
				return $a['usaget'] < $b['usaget'];
			}
			return 0;
		};
		usort($expected, $sort);
		usort($data, $sort);
		if (count($expected) > count($data)) {
			$this->message .= "Not all lines were created Expected to" . count($expected) . "lines , create only " . count($data) . $this->fail;
			return false;
		}
		$i = 0;
		foreach ($data as $data_) {
			$this->message .= "*************************** line usaget {$expected[$i] ['usaget']}  ***************************" . '</br>';
			foreach ($expected[$i] as $k => $v) {
				$this->message .= '<b>test filed</b> : ' . $k . ' </br>	Expected : ' . $v . '</br>';
				$this->message .= '	Result : </br>';
				$nested = false;
				if (strpos($k, '.')) {
					$DataField = Billrun_Util::getIn($data_, $k);
					$nestedKey = explode('.', $k);
					$k = end($nestedKey);
					$nested = true;
				}
				$DataField = $nested ? $DataField : $data_[$k];
				if (!$nested) {
					if (empty(array_key_exists($k, $data_))) {
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
			$i++;
		}
		return $result;
	}

	public function addCaseToLog() {
		$log = [
			"file_name" => $this->test_num,
			"stamp" => (string) random_int(1000000, 9999999999),
			"fetching_host" => gethostname(),
			"fetching_time" => new MongoDate(strtotime('2021-03-01')),
			"retrieved_from" => "ICT",
			"source" => "ICT",
			"backed_to" => [
				$this->application_path . "/billrun/library/Tests/Ict_testData/files"
			],
			"path" => $this->application_path . "/library/Tests/Ict_testData/files/$this->test_num",
			"received_hostname" => gethostname(),
			"received_time" => new MongoDate(strtotime('2021-03-01')),
			"type" => "input_processor"
		];
		$this->stamp = $log["stamp"];
		Billrun_Factory::db()->logCollection()->insert($log);
	}

}


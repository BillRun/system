<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
/**
 * Billing calculator for  pricing  billing lines with customer price.
 *
 * @package  calculator
 * @since    0.5
 */
require_once(APPLICATION_PATH . '/vendor/simpletest/simpletest/autorun.php');

define('UNIT_TESTING', 'true');

class Tests_Eventtest extends UnitTestCase {

	use Tests_SetUp;

	protected $message = "<h1>event unit test </h1></br>";
	protected $ratesCol;
	protected $plansCol;
	protected $linesCol;
	protected $calculator;
	protected $servicesToUse = ["SERVICE1", "SERVICE2"];
	protected $fail = ' <span style="color:#ff3385; font-size: 80%;"> failed </span> <br>';
	protected $pass = ' <span style="color:#00cc99; font-size: 80%;"> passed </span> <br>';

	
	


	public function __construct($label = false) {
		parent::__construct("Test event");
		$this->autoload_tests('eventTestCases');
		date_default_timezone_set('Asia/Jerusalem');
		$this->ratesCol = Billrun_Factory::db()->ratesCollection();
		$this->plansCol = Billrun_Factory::db()->plansCollection();
		$this->linesCol = Billrun_Factory::db()->linesCollection();
		$this->eventsCol = Billrun_Factory::db()->eventsCollection();
		$this->calculator = Billrun_Calculator::getInstance(array('type' => 'customerPricing', 'autoload' => false));
		$this->construct(basename(__FILE__, '.php'), ['lines', 'balances', 'events']);
		$this->setColletions();
		$this->loadDbConfig();
	}

	public function loadDbConfig() {
		Billrun_Config::getInstance()->loadDbConfig();
	}

	public function testUpdateRow() {
		//running test
		$this->tests =  $this->getTestCases($this->tests);
        if (empty($this->test_cases_to_run)) {
            $this->tests = $this->skip_tests($this->tests, 'test_number');
          }
		$this->rows = $this->tests;
		foreach ($this->rows as $key => $row) {
			$this->message .= "Test stamp : {$row['row']['stamp']}<br>";
			$fixrow = $this->fixRow($row['row'], $key);
			$this->linesCol->insert($fixrow);
			$updatedRow = $this->runT($fixrow['stamp']);
			if (isset($row['functions'])) {
				$function = $row['functions'];
				if (!is_array($function)) {
					$function = array($row['functions']);
				}
				foreach ($function as $func) {
					$testFail = $this->assertTrue($this->$func($key, $updatedRow, $row));
				}
			}


			$this->message .= '<p style="border-top: 1px dashed black;"></p>';
		}
		print_r($this->message);
	    $this->restoreColletions();
	}

	protected function runT($stamp) {
		$entity = $this->linesCol->query(array('stamp' => $stamp))->cursor()->current();
		$ret = $this->calculator->updateRow($entity);
		$this->calculator->writeLine($entity, '123');
		$this->calculator->removeBalanceTx($entity);
		$entityAfter = $entity->getRawData();
		return ($entityAfter);
	}
	
	/**
	 * check if specific event is created 
	 * @param type $key
	 * @param type $updatedRow
	 * @param type $row
	 * @return boolean
	 */
	public function isEventCreated($key, $updatedRow, $row) {
		$passed = true;
		foreach ($row['expected']['event_code'] as $k => $v) {
			/* will create  - pass create /fail  not create 
			  will not creatre - fail create / pass not create */
			$query = ['extra_params.sid' => $row['row']['sid'], 'event_code' => $k];
			if ($this->getEvent($query) && !$v['ShouldCreate']) {
				$passed = False;
				$this->message .= "— event code $k creaated  $this->fail";
			} elseif (!$this->getEvent($query) && !$v['ShouldCreate']) {
				$this->message .= "— event code $k isn't creaate  $this->pass";
			} elseif ($this->getEvent($query) && $v['ShouldCreate']) {
				$this->message .= "— event code $k  creaated  $this->pass";
			} elseif (!$this->getEvent($query) && $v['ShouldCreate']) {
				$passed = False;
				$this->message .= "— event code $k isn't creaated  $this->fail";
			}
		}
		return $passed;
	}
	
	/**
	 * check how many events create per specific event
	 * @param type $key
	 * @param type $updatedRow
	 * @param type $row
	 * @return boolean
	 */
	public function NumOfEvents($key, $updatedRow, $row) {
		$passed = true;
		foreach ($row['expected']['event_code'] as $k => $v) {
			$query = ['extra_params.sid' => $row['row']['sid'], 'event_code' => $k];
			if (count($this->getEvent($query)) != $v['num']) {
				$passed = False;
				$this->message .= "— event code $k creaated  " . count($this->getEvent($query)) . "times ,instead of {$v['num']}  times" . $this->fail;
			} else {
				$this->message .= "— event code $k creaated  " . count($this->getEvent($query)) . "  times " . $this->pass;
			}
		}
		return $passed;
	}
		
	/**
	 *  
	 * @param type $query
	 * @return events or false if no events were found according to the query
	 */
	public function getEvent($query) {

		$Eventes = [];
		$Eventes = $this->eventsCol->query($query)->cursor();
		foreach ($Eventes as $event) {
			$returnEvents[] = $event->getRawData();
		}
		return !empty($returnEvents) ? $returnEvents : false;
	}

	protected function fixRow($row, $key) {
		if (!array_key_exists('urt', $row)) {
			$row['urt'] = new MongoDate(time() + $key);
		} else {
			$row['urt'] = new MongoDate(strtotime($row['urt']));
		}
		if (!isset($row['aid'])) {
			$row['aid'] = 1234;
		}
		if (!isset($row['sid'])) {
			$row['sid'] = 1234;
		}
		if (!isset($row['type'])) {
			$row['type'] = 'mytype';
		}
		if (!isset($row['usaget'])) {
			$row['usaget'] = 'call';
		}
		if (isset($row['services_data'])) {
			foreach ($row['services_data'] as $key => $service) {
				if (!is_array($service)) {
					$row['services_data'][$key] = array(
						'name' => $service,
						'service_id' => 0,
					);
				}
				if (isset($service['from'])) {
					$row['services_data'][$key]['from'] = new MongoDate(strtotime($service['from']));
				}
				if (isset($service['to'])) {
					$row['services_data'][$key]['to'] = new MongoDate(strtotime($service['to']));
				}
			}
		}

		if (isset($row['arate_key'])) {
			$row['rates'] = array($row['arate_key'] => 'retail');
		}
		$keys = [];
		foreach ($row['rates'] as $rate_key => $tariff_category) {
			$rate = $this->ratesCol->query(array('key' => $rate_key))->cursor()->current();
			$keys[] = array(
				'rate' => MongoDBRef::create('rates', (new MongoId((string) $rate['_id']))),
				'tariff_category' => $tariff_category,
			);
		}
		$row['rates'] = $keys;
		$plan = $this->plansCol->query(array('name' => $row['plan']))->cursor()->current();
		$row['plan_ref'] = MongoDBRef::create('plans', (new MongoId((string) $plan['_id'])));
		return $row;
	}

}

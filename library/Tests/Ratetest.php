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

require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');

define('UNIT_TESTING', 'true');

class Tests_Ratetest extends UnitTestCase {
	
	protected $ratesCol;
	protected $plansCol;
	protected $linesCol;
	protected $calculator;
	protected $config;
	protected $servicesToUse = ["SERVICE1", "SERVICE2"];
	protected $fail = ' <span style="color:#ff3385; font-size: 80%;"> failed </span> <br>';
	protected $pass = ' <span style="color:#00cc99; font-size: 80%;"> passed </span> <br>';
	protected $rows = [
		array('row' => array('stamp' => 'm1', 'aid' => 8880, 'sid' => 800, 'type' => 'Preprice_Dynamic','plan' => 'NEW-PLAN-A2','uf'=>['sid' => 800,'usage'=>'call','rate'=>'CALL'], 'rate'=>'CALL','usaget' => 'call', 'usagev' => 10,),
			'expected' => array('rates' => array('CALL' => 'retail'))),
	];

	public function __construct($label = false) {
	    Billrun_Config::getInstance()->loadDbConfig();
		parent::__construct("test Rate");
		
	}

	public function testUpdateRow() {
		$this->ratesCol = Billrun_Factory::db()->ratesCollection();
		$this->plansCol = Billrun_Factory::db()->plansCollection();
		$this->linesCol = Billrun_Factory::db()->linesCollection();
		$this->calculator = Billrun_Calculator::getInstance(array('type' => 'Rate_Usage', 'autoload' => false));
		$init = new Tests_UpdateRowSetUp();
		$init->setColletions();

		foreach ($this->rows as $key => $row) {
            Billrun_Config::getInstance()->loadDbConfig();
			$fixrow = $this->fixRow($row['row'], $key);
			$this->linesCol->insert($fixrow);
			$updatedRow = $this->runT($fixrow['stamp']);
			$result = $this->compareExpected($key, $updatedRow, $row);
			$this->assertTrue($result[0]);
			print ($result[1]);
			print('<p style="border-top: 1px dashed black;"></p>');
		}
		$init->restoreColletions();
	}

	protected function runT($stamp) {
		$entity = $this->linesCol->query(array('stamp' => $stamp))->cursor()->current();
		$ret = $this->calculator->updateRow($entity);
		$entityAfter = $entity->getRawData();
		
		return ($entityAfter);
	}

	protected function compareExpected($key, $returnRow, $row) {
		
		$passed = True;
		$epsilon = 0.000001;
		
		$message = '<p style="font: 14px arial; color: rgb(0, 0, 80);"> ';
		$message .= '<b> Result: </b> <br>';
		$message .= ' </p>';
		return [$passed, $message];
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


		$plan = $this->plansCol->query(array('name' => $row['plan']))->cursor()->current();
		$row['plan_ref'] = MongoDBRef::create('plans', (new MongoId((string) $plan['_id'])));
		return $row;
	}

}

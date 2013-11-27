<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * 
 *
 * @package  calculator
 * @since    0.5
 */
class RPCCheckerController extends Yaf_Controller_Abstract {

	protected $cases = array();

	public function indexAction() {
		$this->initCases();
		$this->checkCases();
		die();
	}

	protected function initCases() {
		$this->cases = array(
			array(
				'account_id' => 4555208,
				'date' => '2013-10-24 23:59:59',
				'subscribers' => array(
					'275778' => array(
						'curr_plan' => 'LARGE',
						'next_plan' => 'NULL'
					),
					'275779' => array(
						'curr_plan' => 'LARGE',
						'next_plan' => 'NULL'
					),
					'275780' => array(
						'curr_plan' => 'LARGE',
						'next_plan' => 'NULL'
					),
				),
			),
			array(
				'account_id' => 14426,
				'date' => '2013-10-24 23:59:59',
				'subscribers' => array(
					'285336' => array(
						'curr_plan' => 'LARGE',
						'next_plan' => 'NULL'
					),
				),
			),
			array(
				'account_id' => 8819919,
				'date' => '2013-10-24 23:59:59',
				'subscribers' => array(
					'353861' => array(
						'curr_plan' => 'LARGE',
						'next_plan' => 'LARGE'
					),
				),
			),
			array(
				'account_id' => 6780631,
				'date' => '2013-10-24 23:59:59',
				'subscribers' => array(
					'261230' => array(
						'curr_plan' => 'LARGE',
						'next_plan' => 'LARGE'
					),
				),
			),
			array(
				'account_id' => 539316,
				'date' => '2013-10-24 23:59:59',
				'subscribers' => array(
					'385960' => array(
						'curr_plan' => 'SMALL',
						'next_plan' => 'SMALL'
					),
				),
			),
			array(
				'account_id' => 3582697,
				'date' => '2013-10-24 23:59:59',
				'subscribers' => array(
					'496954' => array(
						'curr_plan' => 'LARGE',
						'next_plan' => 'LARGE'
					),
					'496953' => array(
						'curr_plan' => 'LARGE',
						'next_plan' => 'LARGE'
					),
				),
			),
			array(
				'account_id' => 3515736,
				'date' => '2013-10-24 23:59:59',
				'subscribers' => array(
					'428931' => array(
						'curr_plan' => 'SMALL',
						'next_plan' => 'BIRTHDAY'
					),
				),
			),
			array(
				'account_id' => 23658,
				'date' => '2013-10-24 23:59:59',
				'subscribers' => array(
					'485035' => array(
						'curr_plan' => 'SMALL',
						'next_plan' => 'SMALL'
					),
				),
			),
			array(
				'account_id' => 6676268,
				'date' => '2013-10-24 23:59:59',
				'subscribers' => array(
					'348861' => array(
						'curr_plan' => 'SMALL',
						'next_plan' => 'SMALL'
					),
					'348864' => array(
						'curr_plan' => 'SMALL',
						'next_plan' => 'SMALL'
					),
					'348858' => array(
						'curr_plan' => 'NULL',
						'next_plan' => 'NULL'
					),
				),
			),
			array(
				'account_id' => 193,
				'date' => '2013-10-24 23:59:59',
				'subscribers' => array(
					'91249' => array(
						'curr_plan' => 'NULL',
						'next_plan' => 'NULL'
					),
					'454672' => array(
						'curr_plan' => 'LARGE',
						'next_plan' => 'LARGE'
					),
					'91248' => array(
						'curr_plan' => 'LARGE',
						'next_plan' => 'LARGE'
					),
				),
			),
			array(
				'account_id' => 6055249,
				'date' => '2013-10-30 23:59:59',
				'subscribers' => array(
					'498730' => array(
						'curr_plan' => 'LARGE',
						'next_plan' => 'LARGE'
					),
					'499532' => array(
						'curr_plan' => 'LARGE',
						'next_plan' => 'LARGE'
					),
					'499679' => array(
						'curr_plan' => 'SMALL',
						'next_plan' => 'BIRTHDAY'
					),
				),
			),
			array(
				'account_id' => 28110,
				'date' => '2013-10-24 23:59:59',
				'subscribers' => array(
					'116962' => array(
						'curr_plan' => 'SMALL',
						'next_plan' => 'SMALL'
					),
					'116963' => array(
						'curr_plan' => 'SMALL',
						'next_plan' => 'SMALL'
					),
					'116964' => array(
						'curr_plan' => 'NULL',
						'next_plan' => 'NULL'
					),
					'142344' => array(
						'curr_plan' => 'NULL',
						'next_plan' => 'NULL'
					),
					'185975' => array(
						'curr_plan' => 'LARGE',
						'next_plan' => 'LARGE'
					),
					'185980' => array(
						'curr_plan' => 'SMALL',
						'next_plan' => 'SMALL'
					),
				),
			),
			array(
				'account_id' => 6055249,
				'date' => '2013-10-24 23:59:59',
				'subscribers' => array(),
			),
			array(
				'account_id' => 8294532,
				'date' => '2013-10-24 23:59:59',
				'subscribers' => array(),
			),
			array(
				'account_id' => 384664,
				'date' => '2013-10-24 23:59:59',
				'subscribers' => array(
					'492367' => array(
						'curr_plan' => 'NULL',
						'next_plan' => 'NULL',
					),
					'352700' => array(
						'curr_plan' => 'NULL',
						'next_plan' => 'NULL',
					),
					'398808' => array(
						'curr_plan' => 'LARGE',
						'next_plan' => 'LARGE'
					),
					'398781' => array(
						'curr_plan' => 'LARGE',
						'next_plan' => 'SMALL'
					),
					'352699' => array(
						'curr_plan' => 'BIRTHDAY',
						'next_plan' => 'SMALL'
					),
				),
			),
		);
	}

	protected function checkCases() {
		$subscriber = Billrun_Factory::subscriber();
		foreach ($this->cases as $case) {
			$data = $subscriber->getList(0, 1, $case['date'], $case['account_id']);
			if (!$this->checkOutput($case, $data)) {
				echo 'Wrong output for ' . $case['account_id'] . ', ' . $case['date'] . ".</br>Expected output:</br>" . json_encode($case) . "</br></br>";
			}
		}
		echo "Finished";
	}

	protected function checkOutput($case, $data) {
		if (!empty($case['subscribers'])) {
			if (count($case['subscribers']) != count($data[$case['account_id']])) {
				return false;
			}
			foreach ($data[$case['account_id']] as $subscriber) {
				if (!array_key_exists($subscriber->sid, $case['subscribers'])) {
					return false;
				}
				if ($subscriber->plan != $case['subscribers'][$subscriber->sid]['curr_plan']) {
					return false;
				}
				if ($subscriber->getNextPlanName() != $case['subscribers'][$subscriber->sid]['next_plan']) {
					return false;
				}
			}
		} else if (!empty($data)) {
			return false;
		}
		return true;
	}

	public function byimsitestAction() {
		$working_dir = "/home/shani/Documents/S.D.O.C/BillRun/Files/Docs/Tests/";
		$row = 1;
		if (($handle = fopen($working_dir . "billing_crm_diff_with_sid_and_imsi-1.csv", "r")) !== FALSE) {
			while (($data = fgetcsv($handle, 0, "\t")) !== FALSE) {
				error_log($row);
				if ($row++ == 1) {
					continue;
				}
				$this->subscriber = Billrun_Factory::subscriber();
				$params['time'] = "2013-10-24 23:59:59";
				$params['IMSI'] = $data[5];
				$params_arr[] = array('time' => $params['time'], 'DATETIME' => $params['time'], 'IMSI' => $params['IMSI']);
				$details = $this->subscriber->load($params);
				$data['normal_plan'] = $details->plan;
				
				$newCsvData[$data[4]] = $data;
			}
			fclose($handle);


			$list = Subscriber_Golan::requestList($params_arr);
			foreach ($list as $arr) {
				$newCsvData[$arr['subscriber_id']]['bulk_plan'] = $arr['plan'];
			}

			$handle = fopen('/tmp/result.csv', 'w');
			foreach ($newCsvData as $line) {
				fputcsv($handle, $line);
			}
			fclose($handle);
		}
	}

}

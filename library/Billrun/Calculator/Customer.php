<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
/**
 * Billing customer calculator class for ilds records
 *
 * @package  calculator
 * @since    1.0
 */
require_once __DIR__ . '/../../../application/golan/' . 'subscriber.php';

class Billrun_Calculator_Customer extends Billrun_Calculator {
	
	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = "Customer";
	
	/**
	 * method to receive the lines the calculator should take care
	 * 
	 * @return Mongodloid_Cursor Mongo cursor for iteration
	 */
	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();

		return $lines->query(array(
					'source' => 'nrtrde',
					'$or' => array(
						array('account_id' => array('$exists' => false)),
						array('subscriber_id' => array('$exists' => false))
					),
					'callEventStartTimeStamp' => array('$gt' => '20130929022502'),
		))->cursor()->limit('500');
	}

	/**
	 * @param int $subscriber_id the subscriber id to update
	 * @param Mongodloid_Entity $line the billing line to update
	 *
	 * @return boolean true on success else false
	 */
	protected function updateRow($row) {
		$imsi = null;
		$phone_number = null;
		
		if ($row['source'] == 'api' && $row['type'] == 'refund') {
			$time = date("YmtHis", $row->get('unified_record_time')->sec);
			
			if($row->get('NDC_SN')) {
				$phone_number = $row->get('NDC_SN');
				$time = $row->get('call_start_dt');
			}
			else {
				$imsi = $row->get('imsi');
				$time = $row->get('callEventStartTimeStamp');
			}
			
		} else {	
			if($row->get('NDC_SN')) {
				$phone_number = $row->get('caller_phone_no');
				$time = $row->get('call_start_dt');
			}
			else {
				$imsi = $row->get('imsi');
				$time = $row->get('callEventStartTimeStamp');
			}
		}

		// load subscriber
		$subscriber = golan_subscriber::get($time, $phone_number, $imsi);
		
		if (!$subscriber) {
			if (!isset($row['subscriber_not_found']) || (isset($row['subscriber_not_found']) && $row['subscriber_not_found'] == false)) {
				$msg = "Failed  when sending event to subscriber_plan_by_date.rpc.php". PHP_EOL . "subscriber not found, phone_number: ". $phone_number . "imsi: ". $imsi;
				$this->sendEmailOnFailure($msg);
				
				// update all rows with same subscriber detials - subscriber_not_found:true
				$status = true;
				$result = $this->update_same_subscriber($status, $imsi, $phone_number);
			}
			
			Billrun_Factory::log()->log("subscriber not found. phone:" . $phone_number . " imsi: ". $imsi. " time: " . $time, Zend_Log::INFO);
			return false;
		}
		
		if (isset($row['subscriber_not_found']) && $row['subscriber_not_found'] == true) {
			// update all rows with same subscriber detials - subscriber_not_found:false
			$status = false;
			$result = $this->update_same_subscriber($status, $imsi, $phone_number);
		}
		
		$current = $row->getRawData();
		
		$subscriber_id = $subscriber['subscriber_id'];
		if(empty($imsi)) {
			$subscriber_id = $subscriber['id'];
		}
		
		if (!isset($subscriber_id) || !isset($subscriber['account_id'])) {
			if (!isset($row['subscriber_not_found']) || (isset($row['subscriber_not_found']) && $row['subscriber_not_found'] == false)) {
				$msg = "Did not receive one of necessary params - phone_number: ". $phone_number . "imsi: ". $imsi. PHP_EOL;
				$this->sendEmailOnFailure($msgi);
				
				// update all rows with this imsi - imsi_not_found:true
				$status = true;
				$result = $this->update_same_subscriber($status, $imsi, $phone_number);
			}
			
			Billrun_Factory::log()->log("subscriber_id or account_id not found. phone:" . $phone_number . " imsi: ". $imsi. " time: " . $time, Zend_Log::WARN);
			return false;
		}
		
		$added_values = array('subscriber_id' => $subscriber_id, 'account_id' => $subscriber['account_id']);
		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);
		
		print_r($newData);
		
		return true;
	}

	/**
	 * Execute the calculation process
	 */
	public function calc() {
		foreach ($this->data as $item) {
			// update billing line with billrun stamp
			if (!$this->updateRow($item)) {
				Billrun_Factory::log()->log("phone number:" .$item->get('caller_phone_no'). " cannot update billing line", Zend_Log::INFO);
				continue;
			}
		}
	}
	
	protected function update_same_subscriber($status, $imsi, $phone_number) {
		$lines = Billrun_Factory::db()->linesCollection();
		
		if (!empty($imsi)) {
			$val = $imsi;
			$key = 'imsi';
		}
		else {
			$val = $phone_number;
			$key = 'caller_phone_no';
		}
		
		$rows = array();
		$results = $lines->query(array(
						'source' => 'nrtrde',
						$key => $val
					));
		
		if(empty($results)) {
			exit;
		}
		
		foreach ($results as $result) {
			$added_values = array('subscriber_not_found' => $status);
			$result_a = $result->getRawData();
			$newData = array_merge($result_a, $added_values);
			$result->setRawData($newData);
			$result->save($lines);
		}
	}
	
	protected function sendEmailOnFailure($msg) {
		Billrun_Factory::log()->log($msg, Zend_Log::ALERT);
		return Billrun_Util::sendMail("Failed Fraud Alert, subscriber not found" . date(Billrun_Base::base_dateformat), $msg, Billrun_Factory::config()->getConfigValue('fraudAlert.failed_alerts.recipients', array()) );
	}
	
	/**
	 * Execute write down the calculation output
	 */
	public function write() {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteData', array('data' => $this->data));
		$lines = Billrun_Factory::db()->linesCollection();
		foreach ($this->data as $item) {
			$item->save($lines);
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteData', array('data' => $this->data));
	}
}
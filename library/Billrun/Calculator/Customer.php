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
	public $subscriberSettings = array();
	
	public function __construct($options = array()) {
		$this->subscriberSettings = self::config()->getConfigValue('customer', array());
		
		if (!isset($this->subscriberSettings['calculator']['subscriber_identification_translation']) 
			|| !isset($this->subscriberSettings['calculator']['subscriber']['time_feild_name'])
			|| !isset($this->subscriberSettings['calculator']['subscriber']['subscriber_id_feild_name'])) {
			return false;
		}
		
		$time = $this->subscriberSettings['calculator']['subscriber']['time_feild_name'];
	}
	
	
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
		if ($row['source'] == 'api' && $row['type'] == 'refund') {
			$time = date("YmtHis", $row->get('unified_record_time')->sec);
		}

		$customer_identification = array();
		$customer_identification[$this->subscriberSettings['calculator']['subscriber_identification_translation']] = $row->get($this->subscriberSettings['calculator']['subscriber_identification_translation']);
		
		// load subscriber
		$subscriber = golan_subscriber::get($customer_identification ,$time);
		
		if (!$subscriber) {
			if (!isset($row['subscriber_not_found']) || (isset($row['subscriber_not_found']) && $row['subscriber_not_found'] == false)) {
				$msg = "Failed  when sending event to subscriber_plan_by_date.rpc.php". PHP_EOL . "subscriber not found, ". key($customer_identification) ." : ".$customer_identification[key($customer_identification)];
				$this->sendEmailOnFailure($msg);
				
				// subscriber_not_found:true, update all rows with same subscriber detials
				$status = true;
				$result = $this->update_same_subscriber($status, $customer_identification, $subscriber);
			}
			
			Billrun_Factory::log()->log("subscriber not found ". key($customer_identification) ." : ".$customer_identification[key($customer_identification)] . $time, Zend_Log::INFO);
			return false;
		}
		
		if (!isset($subscriber[$this->subscriberSettings['calculator']['subscriber_id']]) || !isset($subscriber['account_id'])) {
			if (!isset($row['subscriber_not_found']) || (isset($row['subscriber_not_found']) && $row['subscriber_not_found'] == false)) {
				$msg = "Did not receive one of necessary params ". print_r($subscriber). PHP_EOL .key($customer_identification) ." : ".$customer_identification[key($customer_identification)];
				$this->sendEmailOnFailure($msg);
				
				// subscriber_not_found:true, update all rows with same subscriber detials
				$status = true;
				$result = $this->update_same_subscriber($status, $imsi, $phone_number);
			}
			
			Billrun_Factory::log()->log("subscriber_id or account_id not found. phone: " . key($customer_identification) ." : ".$customer_identification[key($customer_identification)] . $time, Zend_Log::WARN);
			return false;
		}
		
		if (isset($row['subscriber_not_found']) && $row['subscriber_not_found'] == true) {
			// subscriber_not_found:false, update all rows with same subscriber detials
			$status = false;
			$result = $this->update_same_subscriber($status, $customer_identification, $subscriber);
		}
		
		$current = $row->getRawData();
		$subscriber_id = $subscriber[$this->subscriberSettings['calculator']['subscriber_id']];
		$added_values = array('subscriber_id' => $subscriber_id, 'account_id' => $subscriber['account_id']);
		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);
		
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
	
	protected function update_same_subscriber($status, $customer_identification, $subscriber) {
		$lines = Billrun_Factory::db()->linesCollection();
		
		$rows = array();
		$results = $lines->query(array(
						'source' => 'nrtrde',
						key($customer_identification) => $customer_identification[key($customer_identification)]
					));
		
		if(empty($results)) {
			exit;
		}
		
		foreach ($results as $result) {
			$added_values = array();
			$added_values['subscriber_not_found'] = $status;
			
			if (isset($subscriber['account_id']) && !empty($subscriber['account_id']) && isset($subscriber['subscriber_id']) && !empty($subscriber['subscriber_id'])) {
				$added_values['account_id'] = $subscriber['account_id'];
				$added_values['subscriber_id'] = $subscriber['subscriber_id'];
			}
			
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
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
	protected $subscriberSettings = array();
	static protected $time;

	public function __construct($options = array()) {
		parent::__construct($options);
		$this->subscriberSettings = Billrun_Factory::config()->getConfigValue('customer', array());

		if (!isset($this->subscriberSettings['calculator']['subscriber']['identification_translation']) || !isset($this->subscriberSettings['calculator']['subscriber']['time_field_name']) || !isset($this->subscriberSettings['calculator']['subscriber']['subscriber_id_feild_name'])) {
			return false;
		}

		self::$time = $this->subscriberSettings['calculator']['subscriber']['time_field_name'];
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
					)//'callEventStartTimeStamp' => array('$gt' => '20130929022502'),
		)); //->cursor()->limit('500');
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
		$customer_identification['key'][$this->subscriberSettings['calculator']['subscriber']['identification_translation']] = $row->get($this->subscriberSettings['calculator']['subscriber']['identification_translation']);
		$customer_identification['time'][self::$time] = $row->get(self::$time);

		// load subscriber
		$subscriber = golan_subscriber::get($customer_identification);

		if (!$subscriber) {
			if (!isset($row['subscriber_not_found']) || (isset($row['subscriber_not_found']) && $row['subscriber_not_found'] == false)) {
				$msg = "Failed  when sending event to subscriber_plan_by_date.rpc.php - sent: " . PHP_EOL . print_r($customer_identification, true) . PHP_EOL . " returned: NULL";
				$this->sendEmailOnFailure($msg);
				$this->sendSmsOnFailure("Failed  when sending event to subscriber-plan-by-date.rpc.php, null returned, see email for more details");

				// subscriber_not_found:true, update all rows with same subscriber detials
				$status = true;
				$result = $this->update_same_subscriber($status, $customer_identification, $subscriber);
			}

			Billrun_Factory::log()->log("No subscriber returned" . print_r($customer_identification, true), Zend_Log::INFO);
			return false;
		}

		if (empty($subscriber[$this->subscriberSettings['calculator']['subscriber']['subscriber_id_feild_name']]) || empty($subscriber['account_id'])) {
			if (!isset($row['subscriber_not_found']) || (isset($row['subscriber_not_found']) && $row['subscriber_not_found'] == false)) {
				$msg = "Error on returned result - sent: " . print_r($customer_identification, true) . PHP_EOL . " returned: " . print_r($subscriber, true);
				$this->sendEmailOnFailure($msg);
				$this->sendSmsOnFailure("Error on returned result from subscriber-plan-by-date.rpc.php, see email for more details");

				// subscriber_not_found:true, update all rows with same subscriber detials
				$status = true;
				$result = $this->update_same_subscriber($status, $customer_identification, $subscriber);
			}

			Billrun_Factory::log()->log("Error on returned result - sent: " . print_r($customer_identification, true) . PHP_EOL . " returned: " . print_r($subscriber, true), Zend_Log::WARN);
			return false;
		}

		if (isset($row['subscriber_not_found']) && $row['subscriber_not_found'] == true) {
			// subscriber_not_found:false, update all rows with same subscriber detials
			$status = false;
			$result = $this->update_same_subscriber($status, $customer_identification, $subscriber);
		}

		$current = $row->getRawData();
		$subscriber_id = $subscriber[$this->subscriberSettings['calculator']['subscriber']['subscriber_id_feild_name']];
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
				Billrun_Factory::log()->log("cannot update billing line", Zend_Log::INFO);
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

		if (empty($results)) {
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
		$recipients = Billrun_Factory::config()->getConfigValue('fraudAlert.failed_alerts.recipients', array());
		$subject = "Failed Fraud Alert, subscriber not found" . date(Billrun_Base::base_dateformat);
		return Billrun_Util::sendMail($subject, $msg, $recipients);
	}

	protected function sendSmsOnFailure($msg) {
		$recipients = Billrun_Factory::config()->getConfigValue('smsAlerts.processing.recipients', array());
		return Billrun_Util::sendSms($msg, $recipients);
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

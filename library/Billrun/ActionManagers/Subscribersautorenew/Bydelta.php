<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a class that uses the update by delta module for the subscribers auto renew collection.
 *
 */
class Billrun_ActionManagers_Subscribersautorenew_Bydelta extends Billrun_ActionManagers_Subscribersautorenew_Action {

	/**
	 * Field to hold the data to be checked for delta in the DB.
	 * @var type Array
	 */
	protected $expected = array();

	/**
	 * The SID of the recurring to update.
	 * @var integer
	 */
	protected $sid;

	/**
	 */
	public function __construct() {
		$this->collection = Billrun_Factory::db()->subscribers_auto_renew_servicesCollection();
	}

	/**
	 * Build the default record for the subscribers auto renew collection.
	 * @return array The default record in the subscribers auto renew service collection.
	 */
	protected function getDefaultRecord() {
		$defaultRecord = array();
		$defaultRecord['interval'] = 'month';
		// TODO: Default is now set.
		$defaultRecord['sid'] = $this->sid;
		$defaultRecord['from'] = date(Billrun_Base::base_datetimeformat);
		return $defaultRecord;
	}

	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$deltaUpdater = new Billrun_UpdateByDelta_Subscribersautorenew();

		// Check only the to field, enabling update of future records.
		$query = array('to' => array('$gt' => new Mongodloid_Date()));
		$query['sid'] = $this->sid;

		$defaultRecord = $this->getDefaultRecord();

		$deltaUpdater->execute($query, $this->expected, $defaultRecord);

		$outputResult = array(
			'status' => 1,
			'desc' => "Success updating auto renew by delta",
			'details' => $this->expected
		);
		return $outputResult;
	}

	/**
	 * Validate that expected is an actual array of records.
	 */
	protected function validateExpected(&$expected) {
		if (!is_array($expected)) {
			return 23;
		}

		foreach ($expected as &$record) {
			if (!is_array($record)) {
				return 23;
			}

			if (!isset($record['interval'])) {
				continue;
			}
			$norm = $this->normalizeInterval($record['interval']);
			if ($norm === false) {
				return 41;
			}

			$record['interval'] = $norm;
		}

		return 0;
	}

	protected function parseDateFieldMongoTime($field, $time) {
		if ($field === "to") {
			return strtotime("23:59:59", $time);
		} else if ($field === "from") {
			return strtotime("00:00:00", $time);
		}

		return $time;
	}

	/**
	 * Parse the date fields of a record
	 * @param array $record - Reference to the array to parse the date fields of
	 * @return boolean true if successful.
	 */
	protected function parseDateFields(&$record) {
		$dateFields = Billrun_Factory::config()->getConfigValue('autorenew.date_fields');
		foreach ($dateFields as $field) {
			if (!isset($record[$field]) || ($record[$field] == null)) {
				continue;
			}

			$time = strtotime($record[$field]);

			// This fails if the date is in the wrong format
			if ($time === false) {
				$errorCode = 24;
				$this->reportError($errorCode, Zend_Log::ALERT, array($record[$field]));
				return false;
			}

			$record[$field] = new Mongodloid_Date($this->parseDateFieldMongoTime($field, $time));
		}

		return true;
	}

	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 */
	public function parse($input) {
		$jsonData = null;
		$expected = $input->get('expected');
		if (empty($expected) || (!($jsonData = json_decode($expected, true)))) {
			$errorCode = 21;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		// If expected is not an array of records.
		$validateError = $this->validateExpected($jsonData);
		if ($validateError != 0) {
			$this->reportError($validateError, Zend_Log::NOTICE);
			return false;
		}

		$sid = $input->get('sid');
		if (empty($sid)) {
			$errorCode = 20;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		$isEmpty = true;
		foreach ($jsonData as &$record) {
			// TODO: This will fail ALL records, if just ONE is invalid!!!
			// I believe that this IS the correct logic, but pay attention, it
			// might be different from what the customer wants.
			if (!$this->parseDateFields($record)) {
				return false;
			}

			if (isset($record['to']) && isset($record['from'])) {
				$record['migrated'] = true;
			}

			if (!empty($record)) {
				$isEmpty = false;
			}
		}

		if ($isEmpty) {
			$jsonData = array();
		}

		$this->expected = $jsonData;
		$this->sid = Billrun_Util::toNumber($sid);

		if ($this->sid === false) {
			$errorCode = 22;
			$this->reportError($errorCode, Zend_Log::ERR, array($this->sid));
			return false;
		}

		if (empty($this->expected)) {
			Billrun_Factory::log("Received empty array, meaning all records will be closed.", Zend_Log::WARN);
		}

		return true;
	}

}

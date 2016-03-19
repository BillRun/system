<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a class that uses the update by delta module for the subscribers auto renew collection.
 *
 * @author Tom Feigin
 */
class Billrun_ActionManagers_Subscribersautorenew_Bydelta extends Billrun_ActionManagers_Subscribersautorenew_Action{
	use Billrun_Traits_Api_AdditionalInput;
	
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
	
	const DEFAULT_ERROR = "Success updating auto renew by delta";
	
	/**
	 */
	public function __construct() {
		parent::__construct(array('error' => self::DEFAULT_ERROR));
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
		$defaultRecord['from'] = date(Billrun_Base::base_dateformat);
		return $defaultRecord;
	}
	
	/**
	 * Report autorenew update action to the lines collection
	 */
	protected function reportInLines() {
		$reportLine = $this->additional;
		$reportLine["sid"] = $this->sid;
		$reportLine['urt'] = new MongoDate();
		$reportLine['process_time'] = Billrun_Util::generateCurrentTime();
		$reportLine['source'] = 'api';
		$reportLine['type'] = 'bydelta';
		$reportLine['usaget'] = 'bydelta';
		
		// Report lines.
		$reportedLine = $reportLine;
		$reportedLine['information'] = $this->expected;
		$reportedLine['lcount'] = count($this->expected);
		$reportedLine['stamp'] = Billrun_Util::generateArrayStamp($reportedLine);
			
		$linesCollection = Billrun_Factory::db()->linesCollection();
		$linesCollection->insert($reportedLine); 	
		
		$archiveCollection = Billrun_Factory::db()->archiveCollection();
		
		// Report archive
		foreach ($this->expected as $line) {
			$archiveLine = array_merge($this->additional, $reportLine, $line);
			$archiveLine['u_s'] = $reportedLine['stamp'];
			$archiveCollection->insert($archiveLine);
		}
	}
	
	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$deltaUpdater = new Billrun_UpdateByDelta_Subscribersautorenew();
			
		// Check only the to field, enabling update of future records.
		$query = array('to' => array('$gt' => new MongoDate()));
		$query['sid'] = $this->sid;
		
		$defaultRecord = $this->getDefaultRecord();
		
		$success = $deltaUpdater->execute($query, $this->expected, $defaultRecord);

		if($deltaUpdater->getErrorCode() != 0) {
			$this->error = $deltaUpdater->getError();
			$this->errorCode = $deltaUpdater->getErrorCode();
			$success = false;
		}
		
		$this->reportInLines();
		
		$outputResult = array(
			'status'      => $this->errorCode == 0 ? 1 : 0,
			'error_code'  => $this->errorCode,
			'desc'        => $this->error,
			'details'     => $this->expected
		);
		return $outputResult;	
	}
	
	/**
	 * Validate that expected is an actual array of records.
	 */
	protected function validateExpected(&$expected) {
		if(!is_array($expected)) {
			return Billrun_Factory::config()->getConfigValue("autorenew_error_base") + 23;
		}
		
		foreach ($expected as &$record) {
			if(!is_array($record)) {
				return Billrun_Factory::config()->getConfigValue("autorenew_error_base") + 23;
			}
			
			if(!isset($record['interval'])) {
				continue;
			}
			$norm = $this->normalizeInterval($record['interval']);
			if($norm === false) {
				return Billrun_Factory::config()->getConfigValue("autorenew_error_base") + 41;
			}

			$record['interval'] = $norm;
		}
		
		return 0;
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
			if($time === false) {
				$errorCode = Billrun_Factory::config()->getConfigValue("autorenew_error_base") + 24;
				$this->reportError($errorCode, Zend_Log::ALERT, array($record[$field]));
				return false;
			}
			$mongoTime = new MongoDate($time);
			$record[$field] = $mongoTime;
		}
		
		return true;
	}
	
	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 */
	public function parse($input) {
		// handle the additional data.
		$this->handleAdditional($input);
		
		return $this->parseExpected($input);
	}
	
	/**
	 * Parse the expectedn records.
	 * @param type $input
	 * @return boolean
	 */
	public function parseExpected($input) {
		$jsonData = null;
		$expected = $input->get('expected');
		if(empty($expected) || (!($jsonData = json_decode($expected, true)))) {
			$errorCode = Billrun_Factory::config()->getConfigValue("autorenew_error_base") + 21;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		// If expected is not an array of records.
		$validateError = $this->validateExpected($jsonData);
		if($validateError != 0) {
			$this->reportError($validateError, Zend_Log::NOTICE);
			return false;
		}
		
		$sid = $input->get('sid');
		if(empty($sid)) {
			$errorCode = Billrun_Factory::config()->getConfigValue("autorenew_error_base") + 20;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		$isEmpty = true;
		foreach ($jsonData as &$record) {
			// TODO: This will fail ALL records, if just ONE is invalid!!!
			// I believe that this IS the correct logic, but pay attention, it
			// might be different from what the customer wants.
			if(!$this->parseDateFields($record)) {
				return false;
			}
			
			if(isset($record['to']) && isset($record['from'])) {
				$record['migrated'] = true; 
			}
			
			if(!empty($record)) {
				$isEmpty = false;
			}
		}

		if($isEmpty) {
			$jsonData = array();
		}
		
		$this->expected = $jsonData;
		$this->sid = Billrun_Util::toNumber($sid);
		
		if($this->sid === false) {
			$errorCode = Billrun_Factory::config()->getConfigValue("autorenew_error_base") + 22;
			$this->reportError($errorCode, Zend_Log::ERR, array($this->sid));
			return false;
		}
		
		if(empty($this->expected)) {
			Billrun_Factory::log("Received empty array, meaning all records will be closed.", Zend_Log::WARN);
		}
		
		return true;
	}
}

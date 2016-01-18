<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a class that uses the update by delta module for the subscribers auto renew collection.
 *
 * @author Tom Feigin
 */
class Billrun_ActionManagers_Subscribersautorenew_Bydelta extends Billrun_ActionManagers_Subscribersautorenew_Action{
	
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
		$defaultRecord['operation'] = 'inc';
		$defaultRecord['sid'] = $this->sid;
		$defaultRecord['from'] = date(DATE_ISO8601, time());
		return $defaultRecord;
	}
	
	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$deltaUpdater = new Billrun_UpdateByDelta_Subscribersautorenew();
			
		$query = Billrun_Util::getDateBoundQuery();
		$query['sid'] = $this->sid;
		
		$defaultRecord = $this->getDefaultRecord();
		
		$success = $deltaUpdater->execute($query, $this->expected, $defaultRecord);

		if($deltaUpdater->getErrorCode() != 0) {
			$this->error = $deltaUpdater->getError();
			$this->errorCode = $deltaUpdater->getErrorCode();
			$success = false;
		}
		
		$outputResult = array(
			'status'      => $this->errorCode == 0 ? 1 : 0,
			'error_code'  => $this->errorCode,
			'desc'        => $this->error,
			'details'     => $this->expected
		);
		return $outputResult;	
	}
	
	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 */
	public function parse($input) {
		$jsonData = null;
		$expected = $input->get('expected');
		if(empty($expected) || (!($jsonData = json_decode($expected, true)))) {
			$errorCode = Billrun_Factory::config()->getConfigValue("autorenew_error_base") + 21;
			$this->reportError($errorCode, Zend_Log::NOTICE);
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
			if (isset($record['from']) && $record['from'] != null) {
				$record['from'] = new MongoDate($record['from']);
			}
			
			if (isset($record['to']) && $record['to'] != null) {
				$record['to'] = new MongoDate($record['to']);
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

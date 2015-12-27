<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a parser to be used by the balances action.
 *
 * @author Tom Feigin
 */
class Billrun_ActionManagers_Balances_Query extends Billrun_ActionManagers_Balances_Action{
	
	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $balancesQuery = array();
	
	/**
	 * Query for projecting the balance.
	 * @var type 
	 */
	protected $balancesProjection = array();
		
	/**
	 */
	public function __construct() {
		parent::__construct(array('error'=>"Success querying balances"));
	}
	
	/**
	 * Query the balances collection to receive data in a range.
	 */
	protected function queryRangeBalances() {
		try {
			$cursor = $this->collection->query($this->balancesQuery)->cursor();
			$returnData = array();
			
			// Going through the lines
			foreach ($cursor as $line) {
				$rawItem = $line->getRawData();
				$returnData[] = Billrun_Util::convertRecordMongoDatetimeFields($rawItem);
			}
		} catch (\Exception $e) {
			$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base") + 30;
			$error = 'failed querying DB got error : ' . $e->getCode() . ' : ' . $e->getMessage();
			$this->reportError($errorCode, Zend_Log::ALERT);
			return null;
		}	
		
		return $returnData;
	}
	
	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$returnData = 
			$this->queryRangeBalances();
		
		// Check if the return data is invalid.
		if(!$returnData) {
			$returnData = array();
			$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base") + 34;
			$this->reportError($errorCode);
		}
		
		$outputResult = array(
			'status'      => $this->errorCode == 0 ? 1 : 0,
			'desc'        => $this->error,
			'error_code'  => $this->errorCode,
			'details'     => $returnData
		);
		return $outputResult;
	}

	/**
	 * Parse the to and from parameters if exists. If not execute handling logic.
	 * @param type $input - The received input.
	 */
	protected function parseDateParameters($input) {
		// Check if there is a to field.
		$to = $input->get('to');
		$from = $input->get('from');
		if($to && $from) {
			$dateParameters = array('to' => array('$lte' => $to), 'from' => array('$gte' => $from));
			$this->setDateParameters($dateParameters, $this->balancesQuery);
		} else {
			$timeNow = new MongoDate();
			$dateParameters = array('to' => array('$gte' => $timeNow), 'from' => array('$lte' => $timeNow));
			// Get all active balances.
			$this->setDateParameters($dateParameters, $this->balancesQuery);
		}
	}
	
	/**
	 * Set date parameters to a query.
	 * are not null.
	 * @param array $dateParameters - Array of date parameters 
	 * including to and from to set to the query.
	 * @param type $query - Query to set the date in.
	 * @todo this function should move to a more generic location.
	 */
	protected function setDateParameters($dateParameters, $query) {
		// Go through the date parameters.
		foreach ($dateParameters as $fieldName => $fieldValue) {
			list($condition, $value) = each($fieldValue);
			$query[$fieldName] =
				array($condition => $value);
		}
	}
	
	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 */
	public function parse($input) {
		$sid = (int) $input->get('sid');
		if(empty($sid)) {
			$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base") + 31;
			$error = "Balances Query received no sid!";
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}
		
		$this->balancesQuery = 
			array('sid'	=> $sid);
		
		$this->parseDateParameters($input);
				
		// Set the prepaid filter data.
		if(!$this->createFieldFilterQuery($input)) {
			return false;
		}
		
		return true;
	}
	
	/**
	 * Create the query to filter only the required fields from the record.
	 * @param type $input
	 */
	protected function createFieldFilterQuery($input) {
		$prepaidQuery = $this->getPrepaidQuery($input);
		
		// Check if received both external_id and name.
		if(count($prepaidQuery) > 1) {
			$error ="Received both external id and name in balances query, specify one or none.";
			$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base") + 32;
			$this->reportError($errorCode, Zend_Log::ERR);
			return false;
		}
		// If empty it means that there is no filtering to be done.
		else if(empty($prepaidQuery)) {
			return true;
		}
		
		// Set to and from if exists.
		if(isset($this->balancesQuery['to']) && isset($this->balancesQuery['from'])) {
			$this->setDateParameters($this->balancesQuery['to'], $this->balancesQuery['from'], $prepaidQuery);
		}
		
		if(!$this->setPrepaidDataToQuery($prepaidQuery)) {
			return false;
		}
		
		return true;
	}
	
	/**
	 * Get the mongo query to run on the prepaid collection.
	 * @param type $input
	 * @return type
	 */
	protected function getPrepaidQuery($input) {
		$prepaidQuery = array();
		
		$accountName = $input->get('pp_includes_name');
		if(!empty($accountName)) {
			$prepaidQuery['name'] = $accountName;
		}
		$accountExtrenalId = $input->get('pp_includes_external_id');
		if(!empty($accountExtrenalId)) {
			$prepaidQuery['external_id '] = $accountExtrenalId;
		}
		
		return $prepaidQuery;
	}
	
	protected function setPrepaidDataToQuery($prepaidQuery) {
		// Get the prepaid record.
		$prepaidCollection = Billrun_Factory::db()->prepaidincludesCollection();
		
		// TODO: Use the prepaid DB/API proxy.
		$prepaidRecord = $prepaidCollection->query($prepaidQuery)->cursor()->current();
		if(!$prepaidRecord || $prepaidRecord->isEmpty()) {
			$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base") + 33;
			$error = "Failed to get prepaid record";
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}
		
		// TODO: Check if they are set? Better to have a prepaid record object with this functionallity.
		$chargingBy = $prepaidRecord['charging_by'];
		$chargingByUsegt = $prepaidRecord['charging_by_usaget'];

		$this->balancesQuery['charging_by'] = $chargingBy;
		$this->balancesQuery['charging_by_usaget'] = $chargingByUsegt;
		
		return true;
	}
}

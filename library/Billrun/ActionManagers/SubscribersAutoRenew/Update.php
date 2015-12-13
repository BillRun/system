<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a parser to be used by the subscribers action.
 *
 * @author tom
 */
class Billrun_ActionManagers_SubscribersAutoRenew_Update extends Billrun_ActionManagers_APIAction{
	// TODO: Create a generic update action class. 
	// TODO: This class shares some logic with the cards and balances update action. 
	// TODO: The setUpdateRecord function is shared. 
	// TODO: This is to be implemented using 'trait'
	
	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $recordToSet = array();
	protected $updateQuery = null;
	protected $query = array();
	
	/**
	 */
	public function __construct() {
		parent::__construct(array('error' => "Success upserting auto renew"));
		$this->collection = Billrun_Factory::db()->subscribers_auto_renew_servicesCollection();
	}
		
	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$success = true;

		$updatedDocument = null;
		
		$options = array(
			'upsert' => true,
			'new' => true,
			'w' => 1,
			);
				
		try {
			$updatedDocument = $this->collection->update($this->query, $this->updateQuery, $options);
			
		} catch (\Exception $e) {
			$error = 'failed storing in the DB got error : ' . $e->getCode() . ' : ' . $e->getMessage();
			$this->reportError($error, Zend_Log::ALERT);
			Billrun_Factory::log('failed saving request :' . print_r($this->recordToSet, 1), Zend_Log::ALERT);
			$success = false;
		}

		if(!$updatedDocument) {
			$success = false;
			$this->reportError("No auto renew records found to update");
		}
		$outputResult = 
			array('status'  => ($success) ? (1) : (0),
				  'desc'    => $this->error,
				  'details' => ($updatedDocument) ? $updatedDocument : 'No results');
		return $outputResult;
	}
	
	/**
	 * Set the values for the update record to be set.
	 * @param httpRequest $input - The input received from the user.
	 * @return true if successful false otherwise.
	 */
	protected function setUpdateRecord($input) {
		$jsonUpdateData = null;
		$update = $input->get('upsert');
		if(empty($update) || (!($jsonUpdateData = json_decode($update, true)))) {
			$error = "Update action does not have an upsert field!";
			$this->reportError($error, Zend_Log::ALERT);
			return false;
		}
		
		if(!isset($jsonUpdateData['to'])) {
			$error = "The 'to' field is mendatory";
			$this->reportError($error, Zend_Log::ALERT);
			return false;
		}
				
		if(!$this->fillWithChargingPlanValues()) {
			return false;
		}
		
		if(!$this->fillWithSubscriberValues()) {
			return false;
		}

		$this->populateUpdateQuery($jsonUpdateData);

		return true;
	}
	
	/**
	 * Populate the update query
	 * @param type $jsonUpdateData
	 */
	protected function populateUpdateQuery($jsonUpdateData) {
		// TODO INTERVAL IS ALWAYS MONTH
		$this->updateQuery['interval'] = 'month';
		
		$this->updateQuery['to'] = $jsonUpdateData['to'];
				$this->updateQuery['operation'] = $jsonUpdateData['operation'];
		$this->updateQuery['done'] = 0;
		$this->updateQuery['remain'] = 
			$this->countMonths(strtotime($this->updateQuery['from']), strtotime($this->updateQuery['to']));
		
		// Check if we are at the end of the month.
		if(date('d') == date('t')) {
			$this->updateQuery['eom'] = 1;
		} else {
			$this->updateQuery['eom'] = 0;
		}
		
		$this->updateQuery['creation_time'] = MongoDate();
		$this->updateQuery['from'] = $this->updateQuery['creation_time'];
		$this->updateQuery['last_renew_date'] = $this->updateQuery['creation_time'];
	}
	
	protected function fillWithSubscriberValues() {
		$this->updateQuery['sid'] = $this->query['sid'];
		$subCollection = Billrun_Factory::db()->subscribersCollection();
		$subQuery = Billrun_Util::getDateBoundQuery();
		$subQuery['sid'] = $this->query['sid'];
		$subRecord = $subCollection->query($subQuery, array('aid'));
		
		if(!$subRecord) {
			$error = "Subscriber not found for " . $subQuery['sid'];
			$this->reportError($error, Zend_Log::ALERT);
			return false;
		}
		
		$this->updateQuery['aid'] = $subQuery['aid'];
		
		return true;
	}
	
	protected function fillWithChargingPlanValues() {
		// Get the charging plan.
		$plansCollection = Billrun_Factory::db()->plansCollection();
		$chargingPlanQuery = Billrun_Util::getDateBoundQuery();
		$chargingPlanQuery['type'] = 'charging';
		$chargingPlanQuery['name'] = $this->query['charging_plan'];
		
		$planRecord = $plansCollection->query($chargingPlanQuery)->cursor()->current();
		if(!$planRecord) {
			$error = "Charging plan not found!";
			$this->reportError($error, Zend_Log::ALERT);
			return false;
		}
		$this->updateQuery['charging_plan_name'] = $planRecord['name'];
		$this->updateQuery['charging_plan_external_id'] = $planRecord['external_id'];
		
		return true;
	}
	
	protected function countMonths($d1, $d2) {
		$min_date = min($d1, $d2);
		$max_date = max($d1, $d2);
		$i = 0;

		while (($min_date = strtotime("+1 MONTH", $min_date)) <= $max_date) {
			$i++;
		}
		
		return $i;
	}
	
	/**
	 * Set all the query fields in the record with values.
	 * @param array $queryData - Data received.
	 * @return boolean true if success to set fields
	 */
	protected function setQueryFields($queryData) {
		$queryFields =  Billrun_Factory::config()->getConfigValue('autorenew.query_fields');
		
		// Get only the values to be set in the update record.
		// TODO: If no update fields are specified the record's to and from values will still be updated!
		foreach ($queryFields as $field) {
			// ATTENTION: This check will not allow updating to empty values which might be legitimate.
			if(!isset($queryData[$field]) || empty($queryData[$field])) {
				$error = "Query is missing " . $field;
				$this->reportError($error, Zend_Log::ALERT);
				return false;
			}
			
			$this->query[$field] = $queryData[$field];
		}
		
		return true;
	}
	
	/**
	 * Set the values for the query record to be set.
	 * @param httpRequest $input - The input received from the user.
	 * @return true if successful false otherwise.
	 */
	protected function setQueryRecord($input) {
		$jsonQueryData = null;
		$query = $input->get('query');
		if(empty($query) || (!($jsonQueryData = json_decode($query, true)))) {
			$error = "Update action does not have a query field!";
			$this->reportError($error, Zend_Log::ALERT);
			return false;
		}
		
		// If there were errors.
		if($this->setQueryFields($jsonQueryData) === FALSE) {
			$error = "Subscribers update received invalid query values in fields";
			$this->reportError($error, Zend_Log::ALERT);
			return false;
		}
		
		return true;
	}
	
	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 * @todo Create a generic update class that implemnts this basic parse logic.
	 */
	public function parse($input) {
		if(!$this->setQueryRecord($input)) {
			return false;
		}
		
		if(!$this->setUpdateRecord($input)){
			return false;
		}
		
		return true;
	}

}

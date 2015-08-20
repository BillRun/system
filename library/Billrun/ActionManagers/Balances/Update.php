<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a parser to be used by the balances action.
 *
 * @author tom
 */
class Billrun_ActionManagers_Balances_Update extends Billrun_ActionManagers_Balances_Manager{
	
	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $recordToSet = array();
	
	protected $query = array();
	protected $keepHistory = true;
	protected $keepBalances = true;
	
	/**
	 */
	public function __construct() {
		parent::__construct();
	}
	
	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$success = true;
		$updatedDocument = null;
		try {
			// TODO: How do i keep history?
			// TODO: Does removing 'balances' means from the subscribers collection?
			$cursor = $this->collection->query($this->options)->cursor();
			foreach ($cursor as $record) {
				foreach ($this->recordToSet as $key => $value) {
					$record->collection($this->collection);
					if(!$record->set($key, $value)) {
						$success = false;
						break 2;
					}
				}
			}		
		} catch (\Exception $e) {
			Billrun_Factory::log('failed to store into DB got error : ' . $e->getCode() . ' : ' . $e->getMessage(), Zend_Log::ALERT);
			Billrun_Factory::log('failed saving request :' . print_r($this->recordToSet, 1), Zend_Log::ALERT);
			$success = false;
		}

		$outputResult = 
			array('status'  => ($success) ? (1) : (0),
				  'desc'    => ($success) ? ('success') : ('Failed updating balance'),
				  'details' => ($updatedDocument) ? json_encode($updatedDocument) : 'null');
		return $outputResult;
	}

	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 */
	public function parse($input) {
		$jsonUpdateData = null;
		$update = $input->get('upsert');
		if(empty($update) || (!($jsonUpdateData = json_decode($update, true)))) {
			Billrun_Factory::log("Update action does not have an update field!", Zend_Log::ALERT);
			return false;
		}
		
		$jsonQueryData = null;
		$query = $input->get('query');
		if(empty($query) || (!($jsonQueryData = json_decode($query, true)))) {
			Billrun_Factory::log("Update action does not have a query field!", Zend_Log::ALERT);
			return false;
		}
		
		$this->query = $this->getUpdateFilter($jsonQueryData);
		// This is a critical error!
		if($this->query===null){
			Billrun_Factory::log("Balances Update: Received more than one filter field!", Zend_Log::ERR);
		}
		// No filter found.
		else if(empty($this->query)) {
			Billrun_Factory::log("Balances Update: Did not receive a filter field!", Zend_Log::ERR);
			return false;
		}
		
		// TODO: Do i need to validate that all these fields are set?
		$this->recordToSet = 
			array('value'			=> $jsonUpdateData['value'],
				  'recurring'		=> $jsonUpdateData['recurring'],
				  'expiration_date'	=> $jsonUpdateData['expiration_date'],
				   // TOOD: In documentation it says "operation default is inc" so can this field be empty for inc to be used?
				  'operation'		=> $jsonUpdateData['operation']);
		
		return true;
	}
	
	/**
	 * Get the query to use to update mongo.
	 * 
	 * @param type $jsonQueryData - The update JSON input.
	 * @return type Query to run to update mongo
	 */
	protected function getUpdateFilter($jsonQueryData) {
		$filter = array();
		
		$filterFields = 
			array('id', 
				  'charging_plan', 
				  'charging_plan_intenal_id', 
				  'name', 
				  'account_intenal_id', 
				  'reccuring', 
				  'secret');
		
		// Check which field is set.
		foreach ($filterFields as $fieldName) {
			// Check if the field is set.
			if(!isset($jsonQueryData[$fieldName])) {
				continue;
			}
			
			// Check if filter is already set.
			// If it is, this is an error. We do not want that someone will try
			// to update by secret code, but somehow manages to send a query with 
			// charging_plan, so that we will update by charging plan and not code.
			// To be sure, when receiving more than one filter field, return error!
			if(!empty($filter)) {
				return NULL;
			}

			$filter = array($fieldName => $jsonQueryData[$fieldName]);
		}
		
		return $filter;
	}
}

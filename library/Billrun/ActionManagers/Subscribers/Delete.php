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
class Billrun_ActionManagers_Subscribers_Delete extends Billrun_ActionManagers_Subscribers_Action{
	
	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $query = array();

	/**
	 * If this is set to false then all the balances related to the user 
	 * that is deleted are to be closed.
	 * @var boolean.
	 */
	protected $keepBalances = false;
	
	/**
	 */
	public function __construct() {
		parent::__construct(array('error' => "Success deleting subscriber"));
	}

	/**
	 * Close all the open balances for a subscriber.
	 */
	protected function closeBalances($sid, $aid) {
		// Find all balances.
		$balancesUpdate = array('$set' => array('to' => new MongoDate()));
		$balancesQuery = Billrun_Util::getDateBoundQuery();
		$balancesQuery['sid'] = $sid; 
		$balancesQuery['aid'] = $aid; 
		$options = array(
			'upsert' => false,
			'new' => false,
			'w' => 1,
			'multi' => true,
		);
		$balancesColl = Billrun_Factory::db()->balancesCollection();
		$balancesColl->findAndModify($balancesQuery, $balancesUpdate, array(), $options, true);
	}
	
	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$success = false;
		try {
			$rowToDelete = $this->collection->query($this->query)->cursor()->current();
			
			// Could not find the row to be deleted.
			if(!$rowToDelete || $rowToDelete->isEmpty()) {
				$error = "Subscriber record not found";
				$this->reportError($error, Zend_Log::ALERT);
				$success = false;
			} else {
				$success = $this->collection->updateEntity($rowToDelete, array('to' => new MongoDate()));
			}
			
			if(!$this->keepBalances) {
				// Close balances.
				$this->closeBalances($rowToDelete['sid'], $rowToDelete['aid']);
			}
			
		} catch (\Exception $e) {
			$error = 'failed to storing in DB got error : ' . $e->getCode() . ' : ' . $e->getMessage();
			$this->reportError($error, Zend_Log::ALERT);
			Billrun_Factory::log('failed saving request :' . print_r($this->query, 1), Zend_Log::ALERT);
			$success = false;
		}

		$outputResult = 
			array('status' => ($success) ? (1) : (0),
				  'desc'   => $this->error);
		
		return $outputResult;
	}

	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 */
	public function parse($input) {
		if(!$this->setQueryRecord($input)) {
			return false;
		}
		 
		$this->keepBalances = $input->get('keep_balances');
		
		return true;
	}
	
	/**
	 * Set the values for the query record to be set.
	 * @param httpRequest $input - The input received from the user.
	 * @return true if successful false otherwise.
	 */
	protected function setQueryRecord($input) {
		$jsonData = null;
		$query = $input->get('query');
		if(empty($query) || (!($jsonData = json_decode($query, true)))) {
			$error = "Failed decoding JSON data";
			$this->reportError($error, Zend_Log::ALERT);
			return false;
		}
		
		// If there were errors.
		if(!$this->setQueryFields($jsonData)) {
			$error="Subscribers delete received invalid query values";
			$this->reportError($error, Zend_Log::ALERT);
			return false;
		}
		
		return true;
	}
	
	/**
	 * Set all the query fields in the record with values.
	 * @param array $queryData - Data received.
	 * @return array - Array of strings of invalid field name. Empty if all is valid.
	 */
	protected function setQueryFields($queryData) {
		$queryFields = $this->getQueryFields();
		
		// Initialize the query with date bound values.
		$this->query = Billrun_Util::getDateBoundQuery();
		
		// Get only the values to be set in the update record.
		// TODO: If no update fields are specified the record's to and from values will still be updated!
		foreach ($queryFields as $field) {
			// ATTENTION: This check will not allow updating to empty values which might be legitimate.
			if(isset($queryData[$field]) && !empty($queryData[$field])) {
				$this->query[$field] = $queryData[$field];
			}
		}
		
		// No ID given.
		if(empty($this->query)) {
			$error = "No query given for delete subscriber action";
			$this->reportError($error, Zend_Log::ALERT);
			return false;
		}
		$fieldCount = count($this->query);
		if($fieldCount != 1 && $fieldCount != count($queryFields)) {
			$error = "Delete subscriber can only use one OR all of the fields!";
			$this->reportError($error, Zend_Log::ALERT);
			return false;
		}
		
		return true;
	}
}

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
class Billrun_ActionManagers_Subscribers_Update extends Billrun_ActionManagers_Subscribers_Action{
	
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
	 * Close all the open balances for a subscriber.
	 * 
	 * @param string $sid - The sid of the user to close the balance for.
	 * @param string $aid - The aid of the user to close the balance for.
	 */
	protected function closeBalances($sid, $aid) {
		// Find all balances.
		$balancesUpdate = array('$set' => array('to', new MongoDate()));
		$balancesQuery = 
			array('sid' => $sid, 
				  'aid' => $aid);
		$options = array(
			'upsert' => false,
			'new' => false,
			'w' => 1,
		);
		// TODO: Use balances DB/API proxy class.
		$balancesColl = Billrun_Factory::db()->balancesCollection();
		$balancesColl->findAndModify($balancesQuery, $balancesUpdate, array(), $options, true);
	}
	
	/**
	 * Keeps history before the records are modified.
	 * @param type $record - Record to be modified.
	 */
	protected function handleKeepHistory($record) {				
		// Cloning the record.
		$oldRecord = clone $record;
		$oldRecord['to'] = new MongoDate();
		// This throws an exception if fails.
		$oldRecord->save($this->collection);
	}
	
	/**
	 * Update a single subscriber record.
	 * @param Mongodloid_Entity $record - Subscriber record to update.
	 * @return boolean true if successful.
	 * @throws WriteConcernException
	 */
	protected function updateSubscriberRecord($record) {
		foreach ($this->recordToSet as $key => $value) {
			$record->collection($this->collection);

			// Check if the user requested to keep history.
			if($this->keepHistory) {
				// This throws an exception if fails.
				$this->handleKeepHistory($record);
			}

			if(!$record->set($key, $value)) {
				return false;
			}

			// This throws an exception if fails.
			$record->save($this->collection);
		}
		
		return true;
	}
	
	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$success = true;
		$updatedDocument = null;
		try {
			$cursor = $this->collection->query($this->options)->cursor();
			foreach ($cursor as $record) {
				if(!$this->updateSubscriberRecord($record)) {
					$success = false;
					break;
				}
			}
			
			if(!$this->keepBalances) {
				// Close balances.
				$this->closeBalances($this->recordToSet['sid'], $this->recordToSet['aid']);
			}
			
		} catch (\Exception $e) {
			Billrun_Factory::log('failed to store into DB got error : ' . $e->getCode() . ' : ' . $e->getMessage(), Zend_Log::ALERT);
			Billrun_Factory::log('failed saving request :' . print_r($this->recordToSet, 1), Zend_Log::ALERT);
			$success = false;
		}

		$outputResult = 
			array('status'  => ($success) ? (1) : (0),
				  'desc'    => ($success) ? ('Success') : ('Failed') . ' updating subscriber',
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
		$update = $input->get('update');
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
		
		// TODO: Do i need to validate that all these fields are set?
		$this->recordToSet = 
			array('imsi'			 => $jsonUpdateData['imsi'],
				  'msisdn'			 => $jsonUpdateData['msisdn'],
				  'aid'				 => $jsonUpdateData['aid'],
				  'sid'				 => $jsonUpdateData['sid'],
				  'plan'			 => $jsonUpdateData['plan'], 
				  'language'		 => $jsonUpdateData['language'],
				  'service_provider' => $jsonUpdateData['service_provider'],
			//	  'from'			 => THIS FIELD IS SET AFTERWARDS WITH THE DATA FROM THE EXISTING RECORD IN MONGO.
				  'to'				 => new MongoDate(strtotime('+100 years')));
		
		$this->query = 
			array('sid'    => $jsonQueryData['sid'],
				  'imsi'   => $jsonQueryData['imsi'],
				  'msisdn' => $jsonQueryData['msisdn']);
		
		// If keep_history is set take it.
		$this->keepHistory = $input->get('keep_history');
		
		// If keep_balances is set take it.
		$this->keepBalances = $input->get('keep_balances');
		
		return true;
	}
}

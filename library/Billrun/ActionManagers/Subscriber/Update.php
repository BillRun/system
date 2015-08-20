<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a parser to be used by the subscribers action.
 *
 * @author tom
 */
class Billrun_ActionManagers_Subscriber_Update extends Billrun_ActionManagers_Subscriber_Action{
	
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
				  'desc'    => ($success) ? ('success') : ('Failed updating subscriber'),
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

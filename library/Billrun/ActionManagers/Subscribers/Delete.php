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
	protected $options = array();
	
	protected $rawinput = array();

	protected $keepBalances = false;
	
	/**
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Close all the open balances for a subscriber.
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
			$rowToDelete = $this->collection->query($this->options)->cursor()->current();
			
			// Could not find the row to be deleted.
			if(!$rowToDelete || $rowToDelete->isEmpty()) {
				Billrun_Factory::log("Failed to get subscriber action instance for received input", Zend_Log::ALERT);
				$success = false;
			} else {
				$this->collection->updateEntity($rowToDelete, array('to' => new MongoDate()));
			}
			
			if(!$this->keepBalances) {
				// Close balances.
				$this->closeBalances($rowToDelete['sid'], $rowToDelete['aid']);
			}
			
		} catch (\Exception $e) {
			Billrun_Factory::log('failed to store into DB got error : ' . $e->getCode() . ' : ' . $e->getMessage(), Zend_Log::ALERT);
			Billrun_Factory::log('failed saving request :' . print_r($this->options, 1), Zend_Log::ALERT);
			$success = false;
		}

		$outputResult = 
			array('status' => ($success) ? (1) : (0),
				  'desc'   => ($success) ? ('Success') : ('Failed') . ' deleting subscriber');
		
		return $outputResult;
	}

	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 */
	public function parse($input) {
		$jsonData = null;
		$query = $input->get('query');
		if(empty($query) || (!($jsonData = json_decode($query, true)))) {
			return false;
		}
		
		$deleteIdFields = array('imsi', 'aid', 'sid');
		
		// Go through the ID fields.
		foreach ($deleteIdFields as $value) {
			if(isset($jsonData[$value])) {
				$this->options[$value] = $jsonData[$value];
			}
		}
		
		// No ID given.
		if(empty($this->options)) {
			Billrun_Factory::log("No ID given for delete subscriber action", Zend_Log::ALERT);
			return false;
		}
		 
		$this->keepBalances = $input->get('keep_balances');
		
		return true;
	}
}

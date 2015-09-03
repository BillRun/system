<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is an update parser for the use of Cards Update action.
 *
 * @author Dori
 */
class Billrun_ActionManagers_Cards_Update extends Billrun_ActionManagers_Cards_Action{
	
	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $query = array();
	protected $update = array();
	
	/**
	 */
	public function __construct() {
		parent::__construct();
	}
	
    /**
     * Get the array of fields to be set in the query record from the user input.
     * @return array - Array of fields to set.
     */
	protected function getQueryFields() {
		return Billrun_Factory::config()->getConfigValue('cards.query_fields', array());
	}
	
    /**
     * Get the array of fields to be set in the update record from the user input.
     * @return array - Array of fields to set.
     */
	protected function getUpdateFields() {
		return Billrun_Factory::config()->getConfigValue('cards.update_fields', array());
	}
	
	/**
	 * This function builds the query for the Cards Update API after 
	 * validating existance of mandatory fields and their values.
	 * @param array $input - fields for query in Jason format. 
	 * @return Return false (and writes errLog) when fails to loocate 
	 * all needed fields and/or values for query and true when success.
	 */
	protected function queryProcess($input) {
		$errLog = '';
		$queryFields = $this->getQueryFields();
		
		$jsonQueryData = null;
		$query = $input->get('query');
		if(empty($query) || (!($jsonQueryData = json_decode($query, true)))) {
			Billrun_Factory::log("There is no query tag or query tag is empty!", Zend_Log::ALERT);
			return false;
		}

		foreach($queryFields as $field){
			if(!isset($jsonQueryData[$field])) {
				$errLog[] = $field;
			}
		}
		
		if (!empty($errLog)) {
			Billrun_Factory::log("The following fields are missing or empty:" . implode(', ',$errLog), Zend_Log::ALERT);
			return false;
		}
		
		$this->query = 
			array(
				'status'			=> $jsonQueryData['status'],
				'batch_number'		=> $jsonQueryData['batch_number'],
				'serial_number'		=> $jsonQueryData['serial_number']
			);
		
		return true;
	}
	
	/**
	 * This function builds the update for the Cards Update API after 
	 * validating existance of field and that they are not empty.
	 * @param array $input - fields for update in Jason format. 
	 * @return Return false (and writes errLog) when fails to loocate 
	 * all needed field and/or values for query and true when success.
	 */
	protected function updateProcess($input) {
		$updateFields = $this->getUpdateFields();
		
		$jsonUpdateData = null;
		$update = $input->get('update');		
		if(empty($update) || (!($jsonUpdateData = json_decode($update, true)))) {
			Billrun_Factory::log("There is no update tag or update tag is empty!", Zend_Log::ALERT);
			return false;			
		}
	
		foreach($updateFields as $field){
			if(isset($jsonUpdateData[$field])) {
				$this->update[$field] = $jsonUpdateData[$field];
			}
		}		
		
		if(isset($this->update['to'])) {
			$this->update['to'] = new MongoDate(strtotime($this->update['to']));
		}
		
		return true;
	}
	
	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$updated = 0;
		try {
			$updateResult = $this->collection->update($this->query, array('$set' => $this->update), array('w' => 1, 'multiple' => 1));
			$updated = isset($updateResult['nModified']) ? $updateResult['nModified'] : 0;
		} catch (\Exception $e) {
			Billrun_Factory::log('failed to store into DB got error : ' . $e->getCode() . ' : ' . $e->getMessage(), Zend_Log::ALERT);
			Billrun_Factory::log('failed saving request :' . print_r($this->update, 1), Zend_Log::ALERT);
			$updated = 0;
		}

		$outputResult = 
			array(
				'status'  => ($updated) ? (1) : (0),
				'desc'    => ($updated) ? ('success') : ('Failed updating card(s)'),
				'details' => 'Updated ' . $updated . ' card(s)'
			);
		
		return $outputResult;
	}

	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 */
	public function parse($input) {
							
		if(!$this->queryProcess($input)){
			return false;			
		}

		if(!$this->updateProcess($input)){
			return false;			
		}
		
		return true;
	}
}

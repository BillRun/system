<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a parser to be used by the cards action.
 *
 * @author Dori
 */
class Billrun_ActionManagers_Cards_Query extends Billrun_ActionManagers_Cards_Action{
	
	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $cardsQuery = array();	
	protected $queryLimit = false;
	
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
		return(array('status','batch_number','serial_number'));
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
		$queryFields = $this->getQueryFields($input);
		
		$jsonQueryData = null;
		$query = $input->get('query');
		if(empty($query) || (!($jsonQueryData = json_decode($query, true)))) {
			Billrun_Factory::log("There is no query tag or query tag is empty!", Zend_Log::ALERT);
			return false;
		}

		foreach($queryFields as $field){
			if(!isset($jsonQueryData[$field]) || (empty($jsonQueryData[$field]))) {
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
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {

		$success=true;
		try {
			$cursor = $this->collection->query($this->cardsQuery)->cursor()->limit($this->queryLimit);			
			$returnData = array();
			
			// Going through the lines
			foreach ($cursor as $line) {
				$returnData[] = json_encode($line->getRawData());
			}
		} catch (\Exception $e) {
			Billrun_Factory::log('failed quering DB got error : ' . $e->getCode() . ' : ' . $e->getMessage(), Zend_Log::ALERT);
			$success=false;
			$returnData = array();
		}	

		$outputResult = 
			array(
				'status'	=> ($success) ? (1) : (0),
				'desc'		=> ($success) ? ('success') : ('Failed querying cards'),
				'details'	=> $returnData
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
                
        //$page = $input->get('page');
        //$this->cardsQuery['page'] = (!empty($page)) ? ($page) : (Billrun_Factory::config()->getConfigValue('api.cards.query.page', 0));            
		$size = $input->get('size');
		$this->queryLimit = (!empty($size)) ? ($size) : (Billrun_Factory::config()->getConfigValue('api.cards.query.size', 10000));
	
		return true;
	}
}

<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Parser to be used by the cards action
 *
 * @package  cards
 * @since    4.0
 * @author   Dori
 */
class Billrun_ActionManagers_Cards_Create extends Billrun_ActionManagers_Cards_Action{
	
	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $cards = array();

	/**
	 */
	public function __construct() {
		parent::__construct();
	}
	
	/**
	 * Get the array of fields to be inserted in the create record from the user input.
	 * @return array - Array of fields to be inserted.
	 */
	protected function getCreateFields() {
		return Billrun_Factory::config()->getConfigValue('cards.create_fields', array());
	}
	
		/**
	 * This function builds the create for the Cards creation API after 
	 * validating existance of field and that they are not empty.
	 * @param array $input - fields for insertion in Jason format. 
	 * @return Return false (and writes errLog) when fails to loocate 
	 * all needed field and/or values for insertion and true when success.
	 */
	protected function createProcess($input) {
		$createFields = $this->getCreateFields();
		$jsonCreateDataArray = null;
		$create = $input->get('cards');
		
		if(empty($create) || (!($jsonCreateDataArray = json_decode($create, true)))) {
			Billrun_Factory::log("There is no create tag or create tag is empty!", Zend_Log::ALERT);
			return false;			
		}
		
		if ($jsonCreateDataArray !== array_values($jsonCreateDataArray)) {
			$jsonCreateDataArray = array($jsonCreateDataArray);
		}		
	
		foreach ($jsonCreateDataArray as $jsonCreateData) {
			$oneCard = array();
			foreach($createFields as $field){
				if(!isset($jsonCreateData[$field])) {
					Billrun_Factory::log("Field: " . $field . " is not set!", Zend_Log::ALERT);					
					return false;
				}
				$oneCard[$field] = $jsonCreateData[$field];
			}
			
			if(isset($oneCard['secret'])) {
				$oneCard['secret'] = password_hash($oneCard['secret'], PASSWORD_DEFAULT);
			}

			if(isset($oneCard['to'])) {
				$oneCard['to'] = new MongoDate(strtotime($oneCard['to']));
			}
			$this->cards[] = $oneCard;
		}		
		
		return true;
	}
	
	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$success = false;
		try {
			$bulkOptions = array(
				'continueOnError' => true,
				'socketTimeoutMS' => 300000,
				'wTimeoutMS' => 300000,
				'w' => 1,
			);
			$res = Billrun_Factory::db()->cardsCollection()->batchInsert($this->cards, $bulkOptions);
			$success = $res['ok'];
			$count = $res['nInserted'];
				
		} catch (\Exception $e) {
			Billrun_Factory::log('failed to store into DB got error : ' . $e->getCode() . ' : ' . $e->getMessage(), Zend_Log::ALERT);
			Billrun_Factory::log('failed saving request :' . print_r($this->cards, 1), Zend_Log::ALERT);
			$success = false;
		}

		$outputResult = 
			array(
				'status'	=> ($success) ? (1) : (0),
				'desc'		=> ($success) ? ('success creating ' . $count . ' cards') : ('Failed creating card'),
				'details'	=> json_encode($this->cards)
			);
		return $outputResult;
	}

	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 */
	public function parse($input) {
		
		if(!$this->createProcess($input)){
			return false;			
		}
		
		return true;
	}
}

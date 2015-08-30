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
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$success = false;
		try {
			$entity = new Mongodloid_Entity($this->cards);
		
			$success = ($entity->save($this->collection, 1) !== false);
				
		} catch (\Exception $e) {
			Billrun_Factory::log('failed to store into DB got error : ' . $e->getCode() . ' : ' . $e->getMessage(), Zend_Log::ALERT);
			Billrun_Factory::log('failed saving request :' . print_r($this->cards, 1), Zend_Log::ALERT);
			$success = false;
		}

		$outputResult = 
			array(
				'status'	=> ($success) ? (1) : (0),
			    'desc'		=> ($success) ? ('success') : ('Failed creating card'),
			    'details'	=> json_encode($entity));
		return $outputResult;
	}

	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 */
	public function parse($input) {
		$jsonData = null;
		$cards = $input->get('cards');
		if(empty($cards) || !($jsonData = json_decode($cards, true))) {
			Billrun_Factory::log("Insert action does not have a cards field!", Zend_Log::ALERT);
			return false;
		}

		$this->cards = 
			array(
				'secret'					=> $jsonData['secret'],
                'batch_number'				=> $jsonData['batch_number'],
                'serial_number'				=> $jsonData['serial_number'],
                'charging_plan_external_id'	=> $jsonData['charging_plan_external_id'],
				'service_provider'			=> $jsonData['service_provider'], 
				'to'						=> new MongoDate(strtotime($jsonData['to'])),
				'status'					=> $jsonData['status'],
                'additional_information'	=> $jsonData['additional_information']);
		
		return true;
	}
}

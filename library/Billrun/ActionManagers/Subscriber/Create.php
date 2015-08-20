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
class Billrun_ActionManagers_Subscriber_Create extends Billrun_ActionManagers_Subscriber_Action{
	
	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $options = array();

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
			$entity = new Mongodloid_Entity($this->options);
		
			$success = ($entity->save($this->collection, 1) !== false);
				
		} catch (\Exception $e) {
			Billrun_Factory::log('failed to store into DB got error : ' . $e->getCode() . ' : ' . $e->getMessage(), Zend_Log::ALERT);
			Billrun_Factory::log('failed saving request :' . print_r($this->options, 1), Zend_Log::ALERT);
			$success = false;
		}

		$outputResult = 
			array('status'  => ($success) ? (1) : (0),
				  'desc'    => ($success) ? ('success') : ('Failed creating subscriber'),
				  'details' => json_encode($entity));
		return $outputResult;
	}

	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 */
	public function parse($input) {
		$jsonData = null;
		$subscriber = $input->get('subscriber');
		if(empty($subscriber) || !($jsonData = json_decode($subscriber, true))) {
			return false;
		}

		// TODO: Do i need to validate that all these fields are set?
		$this->options = 
			array('imsi'			 => $jsonData['imsi'],
				  'msisdn'			 => $jsonData['msisdn'],
				  'aid'				 => $jsonData['aid'],
				  'sid'				 => $jsonData['sid'],
				  'plan'			 => $jsonData['plan'], 
				  'language'		 => $jsonData['language'],
				  'service_provider' => $jsonData['service_provider'],
				  'from'			 => new MongoDate(),
				  'to'				 => new MongoDate(strtotime('+100 years')));
		
		return true;
	}
}

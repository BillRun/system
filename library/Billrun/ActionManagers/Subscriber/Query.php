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
class Billrun_ActionManagers_Subscriber_Query extends Billrun_ActionManagers_Subscriber_Action{
	
	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $subscriberQuery = array();
	
	protected $rawinput = array();

	protected $queryInRange = false;
	
	/**
	 */
	public function __construct() {
		parent::__construct();
	}
	
	/**
	 * Query the subscribers collection to receive data in a range.
	 */
	protected function queryRangeSubscribers() {
		try {
			$cursor = $this->collection->query($this->subscriberQuerys)->cursor();
			if(!$queryInRange) {
				$cursor->limit(1);
			}
			$returnData = array();
			
			// Going through the lines
			foreach ($cursor as $line) {
				$returnData[] = json_encode($line->getRawData());
			}
		} catch (\Exception $e) {
			Billrun_Factory::log('failed quering DB got error : ' . $e->getCode() . ' : ' . $e->getMessage(), Zend_Log::ALERT);
			return null;
		}	
		
		return $returnData;
	}
	/**
	 * Query the subscribers collection to receive a single record.
	 */
	private function querySingleSubscribers() {
		try {
			$line = $this->collection->findOne($this->subscriberQuery);
			
		} catch (\Exception $e) {
			Billrun_Factory::log('failed quering DB got error : ' . $e->getCode() . ' : ' . $e->getMessage(), Zend_Log::ALERT);
			return null;
		}	
		
		return array(json_encode($line));
	}
	
	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$returnData = 
			$this->queryRangeSubscribers();

		$success=true;
		// Check if the return data is invalid.
		if(!$returnData) {
			$returnData = array();
			$success=false;
		}
		
		$outputResult = 
			array('status'  => ($success) ? (1) : (0),
				  'desc'    => ($success) ? ('success') : ('Failed querying subscriber'),
				  'details' => $returnData);
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
		
		// TODO: Do i need to validate that all these fields are set?
		$this->subscriberQuery = 
			array('imsi'			 => $jsonData['imsi'],
				  'msisdn'			 => $jsonData['msisdn'],
				  'sid'				 => $jsonData['sid']);
		
		// Check if there is a to field.
		$to = $input->get('to');
		$from = $input->get('from');
		if($to && $from) {
			$this->subscriberQuery['to'] =
				array('$lte' => new MongoTimestamp($to));
			$this->subscriberQuery['from'] = 
				array('$gte' => new MongoTimestamp($from));
			$this->queryInRange = true;
		}
		
		return true;
	}
}

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
 * @todo This class is very similar to balances query, 
 * a generic query class should be created for both to implement.
 */
class Billrun_ActionManagers_Subscribers_Query extends Billrun_ActionManagers_Subscribers_Action{
	
	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $subscriberQuery = array();
	
	/**
	 * If true then the query is a ranged query in a specific date.
	 * @var boolean 
	 */
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
			if(!$this->queryInRange) {
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
				  'desc'    => ($success) ? ('success') : ('Failed') . ' querying subscriber',
				  'details' => $returnData);
		return $outputResult;
	}
	
	/**
	 * Parse the to and from parameters if exists. If not execute handling logic.
	 * @param type $input - The received input.
	 */
	protected function parseDateParameters($input) {
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
		
		$this->parseDateParameters($input);
		
		return true;
	}
}

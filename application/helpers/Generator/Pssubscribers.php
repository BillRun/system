<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Udata Generator class
 *
 * @package  Models
 * @since    4.0
 */
class Generator_Pssubscribers extends Generator_Prepaidsubscribers {

	static $type = 'pssubscribers';
	
	protected $startMongoTime;
	
	protected $balances = array();
	protected $plans = array();

	public function __construct($options) {
		$this->transactions = array();
		parent::__construct($options);
		$this->startMongoTime = new MongoDate($this->startTime);
		$this->releventTransactionTimeStamp = $this->getLastRunDate(static::$type);
		$this->loadPlans();
	}
	
	public function generate() {
		$fileData = $this->getNextFileData();
		$this->writeRows();
		$this->logDB($fileData);
	}

	public function getNextFileData() {
		$seq = $this->getNextSequenceData(static::$type);

		return array('seq' => $seq, 'filename' => 'PS1_SUBSCRIBER_H_' . date('Ymd',$this->startTime), 'source' => static::$type);
	}
	
	public function load() {
		$this->data = array();
		Billrun_Factory::dispatcher()->trigger('afterGeneratorLoadData', array('generator' => $this));
	}

	//--------------------------------------------  Protected ------------------------------------------------

	protected function getStartTime($options) {
		return strtotime(date('Y-m-d 00:00:00P'));
	}	
	
	protected function writeRows() {
		if (!empty($this->headers)) {
			$this->writeHeaders();
		}
		$subscribersLimit = Billrun_Factory::config()->getConfigValue(static::$type.'.generator.subscribers_limit', 10000);
		$page = 0;
		
		do {
			$this->data = $this->getNextDataChunk($subscribersLimit * $page, $subscribersLimit);
			$sids = $this->getAllDataSids($this->data);
			
			$this->loadBalancesForBulk($sids);

			$hasData = $this->writeDataLines($this->data);
			$page++;
		} while ($hasData);
		$this->markFileAsDone();
	}

	protected function getReportCandiateMatchQuery() {
		$releventTransactionTimeStamp = isset($this->releventTransactionTimeStamp) && empty($this->data)  ? $this->releventTransactionTimeStamp : new MongoDate($this->startTime);
		return  array(	'from' => array('$lt' => new MongoDate($this->startTime)),
						'to' => array('$gt' => new MongoDate($this->startTime)),
						'$or' => array(
								array('sid'=> array('$in' => array_keys($this->transactions))),
								array( 'last_update' => array('$lte' => new MongoDate($this->startTime), '$gt' => $releventTransactionTimeStamp )),
							));
	}

	protected function getReportFilterMatchQuery() {
		return array();
	}

	protected function isLineEligible($line) {
		return ( !empty($line['last_recharge_date']) || !empty($line['last_trans_date']) );
	}

	// ------------------------------------ Helpers -----------------------------------------
	// 

}

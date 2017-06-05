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
	
	protected function writeRows() {
		if (!empty($this->headers)) {
			$this->writeHeaders();
		}
		$subscribersLimit = Billrun_Factory::config()->getConfigValue(static::$type.'.generator.subscribers_limit', 10000);
		$page = 0;
		
		do {
			$this->data = $this->getNextDataChunk($subscribersLimit * $page, $subscribersLimit);
			//$sids = $this->getAllDataSids($this->data);
			
			//$this->loadBalancesForBulk($sids);
			$this->data = $this->unifySubscriberRecords($this->data);
			$hasData = $this->writeDataLines($this->data);
			$page++;
		} while ($hasData);
		$this->markFileAsDone();
	}

	protected function getReportCandiateMatchQuery() {
		$releventTransactionTimeStamp = isset($this->releventTransactionTimeStamp) && empty($this->data)  ? $this->releventTransactionTimeStamp : new MongoDate($this->startTime);
		$retQuery =  array(	'from' => array('$lt' => new MongoDate($this->startTime)),
							'to' => array('$gt' => new MongoDate($this->startTime)),						
						);
		if(!$this->isInitialRun()) {
			
			if(empty($this->transactions)) {
				$retQuery['from'] = array('$gt'=> $releventTransactionTimeStamp,'$lt' => new MongoDate($this->startTime));
			} else {
				$retQuery['sid']= array('$in' => array_keys($this->transactions));
			}
		}
		return $retQuery;
	}

	protected function getReportFilterMatchQuery() {
		return array();
	}

	protected function isLineEligible($line) {
		return $this->isInitialRun() || ( !empty($line['last_recharge_date']) || !empty($line['last_trans_date']) ) || empty($this->transactions);
	}

	protected function unifySubscriberRecords($nonUniqueData) {
		$retData = array();
		foreach($nonUniqueData as $cdr) {
			$cdr = ($cdr instanceof Mongodloid_Entity) ? $cdr->getRawData() : $cdr;
			$stamp = $this->generateFilteredArrayStamp($cdr  ,array('ban','subscriber_no','creation_date','sp_id','cos_id','imsi','lang_id'));
			if(!empty($retData[$stamp])) {
				$retData[$stamp]['last_trans_date'] = max($retData[$stamp]['last_trans_date'],$cdr['last_trans_date']);
				$retData[$stamp]['last_recharge_date'] = max($retData[$stamp]['last_recharge_date'],$cdr['last_recharge_date']);
			} else {
				$retData[$stamp] = $cdr;
			}
		}
		return array_values($retData);
	}
	
	// ------------------------------------ Helpers -----------------------------------------
	// 

}

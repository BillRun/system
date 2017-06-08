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

   protected function loadTransactions($skip, $limit,$sids=array()) {	   
		Billrun_Factory::log("loading transactions...");
        unset($this->transactions);
		$sidsQuery = empty($sids) ? array('$gt'=> 0) :array('$in'=> $sids) ;
		$this->transactions = array();
		if(empty($this->transactionsCursor) || !Billrun_Factory::config()->getConfigValue(static::$type.'.generator.presist_transactions_cursor',1)){
			$aggregationPipeline = array(
								array('$match' => array('urt'=> array('$gt'=>$this->releventTransactionTimeStamp , '$lte' => new MongoDate($this->startTime) ),'sid'=> $sidsQuery )),
								//array('$sort' => array( 'sid'=> 1,'urt'=> 1) ),
								array('$project' => array('sid'=>1,'aid'=>1,'urt'=>1,
															'recharge_urt'=>array('$cond' => array('if' => array('$eq'=>array('$type','balance')), 'then'=>'$urt', 'else'=> null)),
															'trans_urt'=>array('$cond' => array('if' => array('$ne'=>array('$type','balance')), 'then'=>'$urt', 'else'=> null)),
														)),
								array('$group'=>array('_id'=>array('s'=>'$sid','a'=>'$aid'),'aid'=> array('$first'=>'$aid'), 'sid'=> array('$first'=>'$sid'), 'trans_urt' =>array('$max'=>'$trans_urt'), 'recharge_urt' => array('$max'=>'$recharge_urt') )),
							);
			$this->logQueries($aggregationPipeline);
			if(!Billrun_Factory::config()->getConfigValue(static::$type.'.generator.presist_transactions_cursor',1)) {
					$aggregationPipeline[] = array('$skip' => $skip);
					$aggregationPipeline[]=	array('$limit' => $limit);
			}
			$this->transactionsCursor  = $this->db->linesCollection()->aggregateWithOptions($aggregationPipeline, array('allowDiskUse' => true));
			$this->transactionsCursor->rewind();
		}
		$iterationLimit = $limit;
		while (($transaction = $this->transactionsCursor->current()) && !$transaction->isEmpty()) {
			$this->transactions[$transaction['sid'].'_'.$transaction['aid']]= array(
																'recharge'=> $transaction['recharge_urt'],
																'transaction'=> $transaction['trans_urt'],
															);
			$this->loadedSids[] = $transaction['sid'];
			$this->transactionsCursor->next();
			if(!--$iterationLimit ) { break; }
		}
		Billrun_Factory::log("Done loading transactions.");
    }	
	
	protected function getReportCandiateMatchQuery() {
		$releventTransactionTimeStamp = isset($this->releventTransactionTimeStamp) && empty($this->data)  ? $this->releventTransactionTimeStamp : new MongoDate($this->startTime);
		$retQuery =  array(	'from' => array('$lt' => new MongoDate($this->startTime)),
							'to' => array('$gt' => new MongoDate($this->startTime)),						
						);
		if(!$this->isInitialRun()) {
			
			if(empty($this->transactions) && !empty($this->loadedSids)) {
				$retQuery['from'] = array('$gt'=> $releventTransactionTimeStamp,'$lt' => new MongoDate($this->startTime));
				$retQuery['sid'] = array('$nin' => $this->loadedSids);
			} else {
				$retQuery['sid']= array('$in' => array_map(function($v) {return intval(preg_replace('/_.*/', '', $v));}, array_keys($this->transactions)) );
				$retQuery['aid']= array('$in' => array_map(function($v) {return intval(preg_replace('/\d*_/', '', $v));}, array_keys($this->transactions)) );
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

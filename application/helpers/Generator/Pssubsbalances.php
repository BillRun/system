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
class Generator_Pssubsbalances extends Generator_Prepaidsubscribers {

	static $type = 'pssubsbalances';
	
	protected $startMongoTime;
	
	protected $balances = array();
	protected $plans = array();

	public function __construct($options) {
		parent::__construct($options);
	}
	
	public function getNextFileData() {
		$seq = $this->getNextSequenceData(static::$type);

		return array('seq' => $seq, 'filename' => 'PS1_SUBS_BALANCE_H_' . date('Ymd',$this->startTime), 'source' => static::$type);
	}

	//--------------------------------------------  Protected ------------------------------------------------

	protected function writeRows() {
		if (!empty($this->headers)) {
			$this->writeHeaders();
		}
		$subscribersLimit = Billrun_Factory::config()->getConfigValue(static::$type.'.generator.subscribers_limit', 10000);
		$page = 0;
		
		do {
			$this->data =$this->getNextDataChunk($subscribersLimit * $page, $subscribersLimit);
			$this->data = $this->unifyByBalanceId($this->data);
			$hasData = $this->writeDataLines($this->data);
			$page++;
		} while ($hasData);
		$this->markFileAsDone();
	}

	protected function getReportCandiateMatchQuery() {
		$retQuery = array(	'from' => array('$lt' => new MongoDate($this->startTime)),
							'to' => array('$gt' => !empty($this->releventTransactionTimeStamp) ? $this->releventTransactionTimeStamp : new MongoDate($this->startTime) ),
				);
		if(!$this->isInitialRun()) {
			$retQuery['sid']= array('$in' => array_keys($this->transactions));
		}
		return $retQuery;
	}

	protected function getReportFilterMatchQuery() {
		return array();
	}

	protected function isLineEligible($line) {
		return $this->isInitialRun() || isset($this->transactions[$line['subscriber_no']][(string)$line['balance_ref']]);
	}
	
	protected function loadTransactions($skip,$limit,$sids= array()) {
		Billrun_Factory::log("loading transactions...");
        unset($this->transactions);
		$sidsQuery = empty($sids) ? array('$gt'=> 0) :array('$in'=> $sids) ;
		$this->transactions = array();
		
		if(empty($this->transactionsCursor) || !Billrun_Factory::config()->getConfigValue(static::$type.'.generator.presist_transactions_cursor',1)){
			$aggregationPipeline = array(
								array('$match' => array(
														'sid' => $sidsQuery,
														'urt'=> array('$gt'=>$this->releventTransactionTimeStamp , '$lte' => new MongoDate($this->startTime) ),
														'balance_ref' => array('$type'=> 3),
														'balance_after' => array('$exists'=> 1),
														)),
								array('$sort' => array('sid'=>1,'urt'=>1)),
								array('$project' => array('sid'=>1,'urt'=>1,'balance_ref' =>1 ,'balance_after' => 1)),
								array('$group'=>array(
										'_id'=>array('s'=>'$sid','id'=> '$balance_ref'), 
										'sid'=> array('$first'=>'$sid'),
										'balance_ref'=> array('$first'=>'$balance_ref'),
										'urt' =>array('$max'=>'$urt'),
										'balance' =>  array('$last'=> '$balance_after')
									)),
							);
			if(!Billrun_Factory::config()->getConfigValue(static::$type.'.generator.presist_transactions_cursor',1)) {
				$aggregationPipeline[] = array('$skip' => $skip);
				$aggregationPipeline[]=	array('$limit' => $limit);
			}
			$this->logQueries($aggregationPipeline);
			$this->transactionsCursor = $this->db->archiveCollection()->aggregateWithOptions($aggregationPipeline, array('allowDiskUse' => true));
			$this->transactionsCursor->rewind();
			//Load sms transactions from lines as they are not inserted in the archive.	
			$aggregationPipeline[0]['$match']['type'] = 'smsrt';	
			$this->smsTransactionsCursor = $this->db->linesCollection()->aggregateWithOptions($aggregationPipeline, array('allowDiskUse' => true));
			Billrun_Factory::log("Transactions query done.");
		}
		$iterationLimit = $limit;
		while ($transaction = $this->transactionsCursor->current()) {
			$this->transactions[$transaction['sid']][(string)$transaction['balance_ref']['$id']] = array( 'urt'=>$transaction['urt'], 'balance' => $transaction['balance']);
			$this->transactionsCursor->next();
			if(!--$iterationLimit ) { break; }
			
		}
		
		$iterationLimit = $limit;
		while ($transaction = $this->smsTransactionsCursor->current()) {
			if(empty($this->transactions[$transaction['sid']][(string)$transaction['balance_ref']['$id']]) || 
				$transaction['urt'] > $this->transactions[$transaction['sid']][(string)$transaction['balance_ref']['$id']]['urt']) {
				$this->transactions[$transaction['sid']][(string)$transaction['balance_ref']['$id']] = array( 'urt'=>$transaction['urt'], 'balance' => $transaction['balance']);
			} 				
			$this->smsTransactionsCursor->next();
			if(!--$iterationLimit ) { break; }
		}
		
		Billrun_Factory::log("Done loading transactions.");
    }

	protected function unifyByBalanceId($nonUniqueData) {
		$retData = array();
		foreach($nonUniqueData as $cdr) {
			$cdr = ($cdr instanceof Mongodloid_Entity) ? $cdr->getRawData() : $cdr;
			$stamp = $this->generateFilteredArrayStamp($cdr  ,array('ban','subscriber_no','balance_id','balance_expiration_date'));
			if(!empty($retData[$stamp])) {
				$retData[$stamp]['balance'] += $cdr['balance'];
				$retData[$stamp]['update_date'] = max($retData[$stamp]['update_date'],$cdr['update_date']);
			} else {
				$retData[$stamp] = $cdr;
			}
		}
		return array_values($retData);
	}
	
	// ------------------------------------ Helpers -----------------------------------------
	// 

	protected function flattenBalances($sid, $parameters, &$line) {
		return $this->flattenArray(array($line->getRawData()), $parameters, $line);
	}
	
	protected function lastBalanceTransactionBalance($sid, $parameters, $balanceLine) {
		foreach($parameters['fields'] as  $field) {
			if(isset($this->transactions[$sid][(string)$balanceLine[$field]]) ) {
                                return -1 * $this->transactions[$sid][(string)$balanceLine[$field]]['balance'];
			}
		}
		return '';
	}
	
	protected function lastBalanceTransactionDate($sid, $parameters, $balanceLine) {
		foreach($parameters['fields'] as  $field) {
			if(isset($this->transactions[$sid][(string)$balanceLine[$field]]) ) {
                                return $this->translateUrt($this->transactions[$sid][(string)$balanceLine[$field]]['urt'], $parameters);
			}
		}
		return '';
	}
}

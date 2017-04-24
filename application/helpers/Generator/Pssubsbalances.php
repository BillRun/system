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
		$subscribersLimit = Billrun_Factory::config()->getConfigValue('prepaidsubscribers.generator.subscribers_limit', 10000);
		$page = 0;
		
		do {
			$this->data =$this->getNextDataChunk($subscribersLimit * $page, $subscribersLimit);
			
			$hasData = $this->writeDataLines($this->data);
			$page++;
		} while ($hasData);
		$this->markFileAsDone();
	}

	protected function getReportCandiateMatchQuery() {
		$retQuery = array(	'from' => array('$lt' => new MongoDate($this->startTime)),
							'to' => array('$gt' => isset($this->releventTransactionTimeStamp) ? $this->releventTransactionTimeStamp : new MongoDate($this->startTime) ),
							'sid' => array('$in' => array_keys($this->transactions)),
				);
		return $retQuery;
	}

	protected function getReportFilterMatchQuery() {
		return array();
	}

	protected function isLineEligible($line) {
		return isset($this->transactions[$line['subscriber_no']][(string)$line['balance_ref']]);
	}
	
	protected function loadTransactions($skip,$limit) {
		Billrun_Factory::log("loading transactions...");
        unset($this->transactions);
		$this->transactions = array();
		$transactions = $this->db->archiveCollection()->aggregateWithOptions(array(
                            array('$match' => array(
													'urt'=> array('$gt'=>$this->releventTransactionTimeStamp , '$lte' => new MongoDate($this->startTime) ),
													'balance_ref' => array('$exists'=> 1),
													)),
							array('$project' => array('sid'=>1,'urt'=>1,'balance_ref' =>1 )),
                            array('$sort'=>array('sid'=>1,'urt'=>1)),
                            array('$project' => array('sid'=>1,'urt'=>1,'balance_ref' =>'$balance_ref' )),
                    array('$group'=>array(
							'_id'=>array('s'=>'$sid','id'=> '$balance_ref'), 
							'sid'=> array('$first'=>'$sid'),
							'balance_ref'=> array('$first'=>'$balance_ref'),
							'urt' =>array('$max'=>'$urt') 
						)),
					array('$skip' => $skip),
					array('$limit' => $limit)
                ), array('allowDiskUse' => true));
		foreach ($transactions as $transaction) {
			$this->transactions[$transaction['sid']][(string)$transaction['balance_ref']['$id']] = $transaction['urt'];
		}
		Billrun_Factory::log("Done loading transactions.");
    }
	
	protected function isInitialRun() {
		return isset($this->releventTransactionTimeStamp) && !$this->releventTransactionTimeStamp->sec && empty($this->transactions);
	}

	// ------------------------------------ Helpers -----------------------------------------
	// 

	protected function flattenBalances($sid, $parameters, &$line) {
		return $this->flattenArray(array($line->getRawData()), $parameters, $line);
	}
	
	protected function lastBalanceTransactionDate($sid, $parameters, $balanceLine) {
		foreach($parameters['fields'] as  $field) {
			if(isset($this->transactions[$sid][(string)$balanceLine[$field]]) ) {
                                return $this->translateUrt($this->transactions[$sid][(string)$balanceLine[$field]], $parameters);
			}
		}
		return '';
	}
}

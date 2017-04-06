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
		return  array(	'from' => array('$lt' => new MongoDate($this->startTime)),
						'to' => array('$gt' => new MongoDate($this->startTime)),
						'sid'=> array('$in' => array_keys($this->transactions)) );
	}

	protected function getReportFilterMatchQuery() {
		return array();
	}

	protected function isLineEligible($line) {
		return true;
	}
	
	protected function loadTransactions($skip,$limit) {
		Billrun_Factory::log("loading transactions...");
        unset($this->transactions);
		$this->transactions = array();
		$transactions = $this->db->linesCollection()->aggregateWithOptions(array(
                            array('$match' => array('urt'=> array('$gt'=>$this->releventTransactionTimeStamp , '$lte' => new MongoDate($this->startTime) ),'pp_includes_external_id' => array('$exists'=> 1) )),
                            array('$sort'=>array('sid'=>1,'urt'=>1)),
                            array('$project' => array('sid'=>1,'urt'=>1,'pp_includes_external_id' => 1,
                                                    )),
                    array('$group'=>array('_id'=>array('s'=>'$sid','id'=> '$pp_includes_external_id'), 'sid'=> array('$first'=>'$sid'), 'balance_id'=> array('$first'=>'$pp_includes_external_id'), 'urt' =>array('$last'=>'$urt') )),
					array('$skip' => $skip),
					array('$limit' => $limit)
                ), array('allowDiskUse' => true));
		foreach ($transactions as $transaction) {
			$this->transactions[$transaction['sid']][$transaction['balance_id']] = $transaction['urt'];
		}
		Billrun_Factory::log("Done loading transactions.");
    }

	// ------------------------------------ Helpers -----------------------------------------
	// 

	protected function flattenBalances($sid, $parameters, &$line) {
		//$balances = $this->getBalancesForSid($sid);
		return $this->flattenArray(array($line->getRawData()), $parameters, $line);
	}
	
	protected function lastBalanceTransactionDate($sid, $parameters, $line) {
		return isset($this->transactions[$sid][$line[$parameters['field']]]) ? 
                                $this->translateUrt($this->transactions[$sid][$parameters[$parameters['field']]], $parameters) :
                                '';
	}
}

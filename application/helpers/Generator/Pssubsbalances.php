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
//			$this->loadTransactions($subscribersLimit * $page, $subscribersLimit);
//			$this->buildAggregationQuery();
//			Billrun_Factory::log('Running bulk of records ' . $subscribersLimit * $page . '-' . $subscribersLimit * ($page+1));
//			$this->data = $this->collection->aggregateWithOptions($this->aggregation_array, array('allowDiskUse' => true));
			$this->data =$this->getNextDataChunk($subscribersLimit * $page, $subscribersLimit);
			
//			$sids = array();
//			foreach ($this->data as $line) {
//				if ($this->isLineEligible($line)) {
//					$sids[] = $line['subscriber_no'];
//				}
//			}
			//$sids = $this->getAllDataSids($this->data);
			//$this->loadBalancesForBulk($sids);

//			$hasData = false;
//			foreach ($this->data as $line) {
//				$hasData = true;
//				$translatedLine = $this->translateCdrFields($line, $this->translations);
//				if ($this->isLineEligible($translatedLine)) {
//					$this->writeRowToFile($translatedLine, $this->fieldDefinitions);
//				}
//			}
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

	// ------------------------------------ Helpers -----------------------------------------
	// 

	protected function flattenBalances($sid, $parameters, &$line) {
		//$balances = $this->getBalancesForSid($sid);
		return $this->flattenArray(array($line->getRawData()), $parameters, $line);
	}
}

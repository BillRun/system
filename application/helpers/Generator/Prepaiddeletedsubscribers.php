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
class Generator_Prepaiddeletedsubscribers extends Generator_Prepaidsubscribers {

	static $type = 'prepaiddeletedsubscribers';
	
	protected $startMongoTime;
	
	protected $balances = array();
	protected $plans = array();

	public function __construct($options) {
		parent::__construct($options);
		//$this->loadPlans();
	}
	

	public function getNextFileData() {
		$seq = $this->getNextSequenceData(static::$type);

		return array('seq' => $seq, 'filename' => 'PS1_SUBS_4_DEL_' . date('Ymd',$this->startTime), 'source' => static::$type);
	}
	
	
	protected function writeRows() {
		if (!empty($this->headers)) {
			$this->writeHeaders();
		}
		$subscribersLimit = Billrun_Factory::config()->getConfigValue('prepaidsubscribers.generator.subscribers_limit', 10000);
		$page = 0;
		
		do {
			$aggregation_array = array_merge(
				$this->aggregation_array, 
				array(array('$skip' => $subscribersLimit * $page)),
				array(array('$limit' => $subscribersLimit))
			);
			Billrun_Factory::log('Running bulk of records ' . $subscribersLimit * $page . '-' . $subscribersLimit * ($page+1));
			$this->data = $this->collection->aggregateWithOptions($aggregation_array, array('allowDiskUse' => true));
			
			$sids = array();
			foreach ($this->data as $line) {
				if ($this->isLineEligible($line)) {
					$sids[] = $line['subscriber_no'];
				}
			}

			$hasData = false;
			foreach ($this->data as $line) {
				$hasData = true;
				$translatedLine = $this->translateCdrFields($line, $this->translations);
				if ($this->isLineEligible($translatedLine)) {
					$this->writeRowToFile($translatedLine, $this->fieldDefinitions);
				}
			}
			$page++;
		} while ($hasData);
		$this->markFileAsDone();
	}
	
	//--------------------------------------------  Protected ------------------------------------------------


	protected function getReportCandiateMatchQuery() {
		return array('to' => array('$lt' => new MongoDate($this->startTime),'$gte' => $this->getLastRunDate(static::$type)));
	}

	protected function getReportFilterMatchQuery() {
		return array();
	}

	protected function isLineEligible($line) {
		return true;
	}


}

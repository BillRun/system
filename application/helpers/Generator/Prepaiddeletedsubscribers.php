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
		$this->startMongoTime = new Mongodloid_Date($this->startTime);
		$this->releventTransactionTimeStamp =  strtotime(Billrun_Factory::config()->getConfigValue('prepaiddeletedsubscribers.transaction_horizion','-48 hours'));
		$this->loadPlans();
	}
	

	public function getNextFileData() {
		$seq = $this->getNextSequenceData(static::$type);

		return array('seq' => $seq, 'filename' => 'PREPAID_DELETED_SUBSCRIBERS_' . date('YmdHi',$this->startTime), 'source' => static::$type);
	}
	
	//--------------------------------------------  Protected ------------------------------------------------


	protected function getReportCandiateMatchQuery() {
		return array();
	}

	protected function getReportFilterMatchQuery() {
		return array('to' => array('$lt' => $this->startMongoTime),'$gte' => $this->getLastRunDate(static::$type));
	}

	protected function isLineEligible($line) {
		return true;
	}


}

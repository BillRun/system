<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Job Manager of Cycle
 *
 * @package  Job Manager
 * @since    5.16
 */
class Billrun_Job_Cycle extends Billrun_Job_Abstract {

	protected $method = 'Cycle';
	
	/**
	 * the billrun key of the cycle
	 * @var type
	 */
	protected $billrun_key;
	
	/**
	 * the invoicing day if running in MDC mode
	 * @var type
	 */
	protected $invoicing_day = false;

	/**
	 * The number of account handle in the job
	 * @var type
	 */
	protected $count = 0;

	/**
	 * the start time of the job
	 * @var int
	 */
	protected $start_time;

	/**
	 * the fetch page size of accounts for the cycle
	 * 
	 * @var int
	 */
	protected $fetch_page_size;

	/**
	 * the zero page limit that define when cycle finished
	 * 
	 * @var int
	 */
	protected $zero_pages_limit = 3;
	
	/** 
	 * the mode the cycle is running: page or account
	 * @var type
	 */
	protected $mode;

	protected function init($params) {
		if (isset($params['billrun_key'])) {
			$this->billrun_key = $params['billrun_key'];
		} else if ($this->config['billrun_key']) {
			$this->billrun_key = $this->config['billrun_key'];
		}
		if (!empty($this->config['invoicing_day'])) {
			$this->invoicing_day = $this->config['invoicing_day'];
		}
		$this->start_time = time();
		$this->fetch_page_size = Billrun_Factory::config()->getConfigValue('customer.aggregator.size', 100);
		$this->zero_pages_limit = Billrun_Factory::config()->getConfigValue('customer.aggregator.zero_pages_limit', 3);
		return true;
	}

	public function fetch() {
		if (isset($this->config['include'])) {
			$this->mode = 'account';
			return Billrun_Util::verify_array($this->config['include'], 'int');
		} else {
			$this->mode = 'page';
		}
	}

	public function run() {
		if ($this->mode == 'page') {
			$jobSettings = [
				"billrun_key" => $this->billrun_key,
			];

			if ($this->config['invoicing_day']) {
				$jobSettings['invoicing_day'] = $this->invoicing_day;
			}

			for ($i = 0; $i <= $this->zero_pages_limit; $i++) {
				Billrun_Factory::log("Going to create job cycle page " . $i);
				$jobSettings["page_number"] = $i;
				Billrun_Jobsmanager::getInstance($this->queueMsg->getQueue())->push('Cycle_Page', $jobSettings, $this->queueMsg->md5);
			}
		} else if ($this->mode == 'account') {
			$jobSettings = [
				'billrun_key' => $this->billrun_key,
				'generate_pdf' => $this->config['generate_pdf'] ?? Billrun_Factory::config()->getConfigValue('billrun.generate_pdf'),
			];

			if ($this->invoicing_day) {
				$jobSettings['invoicing_day'] = $this->invoicing_day;
			}
			$parent = $this->queueMsg->md5;
			foreach ($this->data as $entry) {
				$this->addCycleAccountJob($aid, $parent);
			}
			
		}
	}
	
	public function markCompleted() {
		$ret = parent::markCompleted();
		if (get_parent_class($this) == 'Billrun_Job_Abstract') {
			// this will not run in the inherited classes
			$this->addBillingCycle();
		}
		
		return $ret;
	}
	
	protected function addCycleAccountJob($aid, $parent) {
		$jobSettings = [
			'aid' => $aid,
			'billrun_key' => $this->billrun_key,
			'generate_pdf' => $this->config['generate_pdf'] ?? Billrun_Factory::config()->getConfigValue('billrun.generate_pdf'),
		];
		if ($this->invoicing_day) {
			$jobSettings['invoicing_day'] = $this->invoicing_day;
		}
		Billrun_Factory::log("Going to create job cycle account " . $aid);
		Billrun_Jobsmanager::getInstance($this->queueMsg->getQueue())->push('Cycle_Account', $jobSettings, $parent);
	}

	/**
	 * method to insert the job into billing cycle collection for tracking the cycle
	 */
	protected function addBillingCycle() {
		$coll = Billrun_Factory::db()->billing_cycleCollection();

		$remove = ['billrun_key' => $this->billrun_key];

		if ($this->invoicing_day) {
			$remove['invoicing_day'] = $this->invoicing_day;
		}

		$coll->remove($remove);

		$record = [
			'billrun_key' => $this->billrun_key,
			'page_number' => 0,
			'page_size' => $this->fetch_page_size,
			'host' => Billrun_Util::getHostName(),
			'start_time' => new Mongodloid_Date($this->start_time),
			'count' => 0,
			'job_md5' => $this->queueMsg->md5,
			'completed' => 0,
			'zero_pages' => 0,
		];

//		if ($this->invoicing_day) {
//			$record['invoicing_day'] = $invoicing_day;
//		}

//		if ($count === 0) { // if there is no inherited jobs - done
//			$record['end_time'] = new Mongodloid_Date();
//		}

		$coll->insert($record);

//		if ($count === 0) { // if there is no inherited jobs - mark fake pages to be counted as cycle done
//			$record['fake'] = 1;
//			$record['count'] = 0;
//			$record['end_time'] = new Mongodloid_Date();
//			// add fake zero pages for FE backward compatibility
//			$zero_pages_limit = Billrun_Factory::config()->getConfigValue('customer.aggregator.zero_pages_limit', 3);
//			for ($i = 0; $i < $zero_pages_limit; $i++) { // todo: take the 10 from config
//				$record['page_number'] = $record['page_number'] + 1;
//				unset($record['_id']);
//				$coll->insert($record);
//			}
//		}
	}
}

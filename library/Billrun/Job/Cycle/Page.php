<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Job Manager of Cycle in Account level
 *
 * @package  Job Manager
 * @since    5.16
 */
class Billrun_Job_Cycle_Page extends Billrun_Job_Cycle {

	protected $method = 'Cycle_Page';

	/**
	 * the page number the cycle is running on
	 * @var int
	 */
	protected $page_number;
	
	protected function init($params) {
		parent::init($params);
		if (isset($this->config['page_number'])) {
			$this->page_number = $this->config['page_number'];
		} else {
			throw new Exception("page number is not passed to the job");
		}
		return true;
	}
	
	public function fetch() {
		$options = [
			'type' => 'customer',
			'stamp' => $this->billrun_key,
			'size' => $this->fetch_page_size,
		];
		
		if ($this->invoicing_day) {
			$options['invoicing_day'] = $this->invoicing_day;
		}

		$options['page'] = $this->page_number;
		$aggregator = Billrun_Aggregator::getInstance($options);
		$page = $aggregator->load();
		$ret = [];
		if (count($page) == 0) {
			return $ret;
		}
		foreach ($page as $entry) {
			$aid = $entry->getInvoice()->getAid();
			$ret[] = $aid;
		}
		$this->count = count($ret);
		return $ret;
	}

	public function run() {
		if (!count($this->data)) {
			return;
		}
				
		foreach ($this->data as $aid) {
			$this->addCycleAccountJob($aid, $this->parent);
		}
	}
		
	protected function finished() {
		$coll = Billrun_Factory::db()->billing_cycleCollection();
		$query = [
			'billrun_key' => $this->config['billrun_key'],
			'page_number' => 0,
		];
		if ($this->invoicing_day) {
			$query['invoicing_day'] = $this->invoicing_day;
		}
		if (count($this->data) == 0) {
			// update zero_pages in billing_cycle and check if completed
			Billrun_Factory::log("Job cycle page number " . $this->page_number . " generated 0 accounts");
			
			// update billing cycle zero pages count
			$set = [
				'$inc' => [
					'zero_pages' => 1,
				],
			];
			$options = array('upsert' => true, 'new' => true);
			$record = $coll->findAndModify($query, $set, null, $options);
			$this->checkCycleFinished($record);
		} else {
			// create the next page
			$jobSettings = $this->config;
			$jobSettings['page_number'] += $this->zero_pages_limit;
			Billrun_Factory::log("Page " . $this->page_number . " will generated page " . $jobSettings['page_number']);
			Billrun_Jobsmanager::getInstance()->push('Cycle_Page', $jobSettings, $this->parent);

			// update billing cycle count of accounts
			$set = [
				'$inc' => [
					'count' => $this->count,
				],
			];
			$options = array('upsert' => false, 'new' => true);
			$record = $coll->findAndModify($query, $set, null, $options);
		}
		return true;
	}

}
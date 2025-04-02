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

	protected $method = 'cycle';
	
	protected $billrun_key;
	
	public function fetch() {
		$options = [
			'type' => 'customer',
			'stamp' => $this->billrun_key,
			'size' => 10000, // TODO: make if configurable
		];
		
		if (!empty($this->config['invoicing_day'])) {
			$options['invoicing_day'] = $this->config['invoicing_day'];
		}
		
		if (isset($this->config['include'])) {
			return Billrun_Util::verify_array($this->config['include'], 'int');
		}
		
		$i = 0;
		$ret = [];
		while ($i <= 10000) {
			$options['page'] = $i;
			$aggregator = Billrun_Aggregator::getInstance($options);
			$page = $aggregator->load();
			if (count($page) == 0) {
				break; //while
			}
			foreach ($page as $entry) {
				$ret[] = $entry->getInvoice()->getAid();
			}

			$ret += $page;
			$i++;
		}

		return $ret; // TBD - list of accounts
	}
	
	protected function init($params) {
		if (isset($params['billrun_key'])) {
			$this->billrun_key = $params['billrun_key'];
		} else if ($this->config['billrun_key']) {
			$this->billrun_key = $this->config['billrun_key'];
		}
		return true;
	}


	public function run() {
		$jobSettings = array();
		
		$jobSettings['generate_pdf'] = $this->config['generate_pdf'] ?? Billrun_Factory::config()->getConfigValue('billrun.generate_pdf');
		
		$count = 0;
		$parent = $this->queueMsg->md5;
		foreach ($this->data as $entry) {
			$jobSettings['aid'] = $entry;
			$jobSettings['billrun_key'] = $this->billrun_key;
			if (!empty($this->config['invoicing_day'])) {
				$jobSettings['invoicing_day'] = $this->config['invoicing_day'];
			}

			Billrun_Factory::log("Going to create job cycle account " . $entry);
			Billrun_Jobsmanager::getInstance($this->queueMsg->getQueue())->push('Cycle_Account', $jobSettings, $parent);
			$count++;
		}
		$this->addBillingCycle($count);
		Billrun_Factory::log("Created " . $count . " cycle account jobs into queue", Zend_Log::INFO);
	}
	
	/**
	 * method to insert the job into billing cycle collection for tracking the cycle
	 */
	protected function addBillingCycle($count) {
		$coll = Billrun_Factory::db()->billing_cycleCollection();
		if (!empty($this->config['invoicing_day'])) {
			$invoicing_day = $this->config['invoicing_day'];
		} else {
			$invoicing_day = false;
		}
		
		$remove = ['billrun_key' => $this->billrun_key];
		
		if ($invoicing_day) {
			$remove['invoicing_day'] = $invoicing_day;
		}
		
		$coll->remove($remove);
		
		$record = [
			'billrun_key' => $this->billrun_key,
			'page_number' => 0,
			'page_size' => Billrun_Factory::config()->getConfigValue('customer.aggregator.size', 100),
			'host' => Billrun_Util::getHostName(),
			'start_time' => new Mongodloid_Date(),
			'count' => $count,
			'job_md5' => $this->queueMsg->md5,
			'completed' => 0,
		];
		
		if ($invoicing_day) {
			$record['invoicing_day'] = $invoicing_day;
		}

		if ($count === 0) { // if there is no inherited jobs - done
			$record['end_time'] = new Mongodloid_Date();
		}
		
		$coll->insert($record);
		
		if ($count === 0) { // if there is no inherited jobs - mark fake pages to be counted as cycle done
			$record['fake'] = 1;
			$record['count'] = 0;
			$record['end_time'] = new Mongodloid_Date();
			// add fake zero pages for FE backward compatibility
			$zero_pages_limit = Billrun_Factory::config()->getConfigValue('customer.aggregator.zero_pages_limit', 3);
			for ($i = 0; $i < $zero_pages_limit; $i++) { // todo: take the 10 from config
				$record['page_number'] = $record['page_number']+1;
				unset($record['_id']);
				$coll->insert($record);
			}

		}
	}

}

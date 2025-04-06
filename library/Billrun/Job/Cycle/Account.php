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
class Billrun_Job_Cycle_Account extends Billrun_Job_Abstract {

	protected $method = 'Cycle_Account';

	protected $billrun_key;
	
	protected $invoicing_day = false;
	
	public function init($params) {
		parent::init($params);
		if (isset($params['billrun_key'])) {
			$this->billrun_key = $params['billrun_key'];
		} else if ($this->config['billrun_key']) {
			$this->billrun_key = $this->config['billrun_key'];
		}
		if (!empty($this->config['invoicing_day'])) {
			$this->invoicing_day = $this->config['invoicing_day'];
		}
		return true;
	}
	
	public function run() {
		Billrun_Factory::log("cycle account start for " . ($this->config['aid'] ?? ''));
		$options = [
			'type' => 'customer',
			'stamp' => $this->billrun_key,
			'page' => 0,
			'size' => 1,
			'force_accounts' => $this->config['aid'],
		];
		

		if (!empty($this->config['generate_pdf'])) {
			$options['generate_pdf'] = $this->config['generate_pdf'];
		}

		$aggregator = Billrun_Aggregator::getInstance($options);
		$aggregator->load();
		$aggregator->aggregate();

		Billrun_Factory::log("cycle account end for " . ($this->config['aid'] ?? ''));
	}
	
	public function markCompleted() {
		$ret = parent::markCompleted();
		$coll = Billrun_Factory::db()->billing_cycleCollection();
		$query = [
			'billrun_key' => $this->billrun_key,
			'page_number' => 0,
		];
		if ($this->invoicing_day) {
			$query['invoicing_day'] = $this->invoicing_day;
		}
		$set = [
			'$inc' => [
				'completed' => 1
			]
		];
		$options = array('upsert' => false, 'new' => true);
		$record = $coll->findAndModify($query, $set, null, $options);

		if ($record['count'] <= $record['completed']) {
			$update = [
				'$set' => [
					'end_time' => new Mongodloid_Date(),
				],
			];
			$coll->update($query, $update);
			$record['fake'] = 1;
			$record['count'] = 0;
			$record['completed'] = 0;
			unset($record['zero_pages']);
			unset($record['job_md5']);
			$record['start_time'] = new Mongodloid_Date();
			$record['end_time'] = new Mongodloid_Date();
			// add fake zero pages for FE backward compatibility
			$zero_pages_limit = Billrun_Factory::config()->getConfigValue('customer.aggregator.zero_pages_limit', 3);
			for ($i = 0; $i < $zero_pages_limit; $i++) { // todo: take the 10 from config
				$record['page_number'] = $record['page_number']+1;
				unset($record['_id']);
				$coll->insert($record);
			}
		}
		return $ret;
	}
}

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
class Billrun_Job_Cycle_Account extends Billrun_Job_Cycle {

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

	/**
	 * this is only to override the parent method and do nothing
	 */
	public function fetch() {
		return;
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

		$options['generate_pdf'] = $this->config['generate_pdf'] ?? Billrun_Factory::config()->getConfigValue('billrun.generate_pdf');

		$aggregator = Billrun_Aggregator::getInstance($options);
		$aggregator->load();
		$aggregator->aggregate();

		Billrun_Factory::log("cycle account end for " . ($this->config['aid'] ?? ''));
	}

	protected function finished() {
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
		$options = array('upsert' => true, 'new' => true);
		$record = $coll->findAndModify($query, $set, null, $options);
		$this->checkCycleFinished($record);

		return true;
	}
}

<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Job Manager of Confirm Cycle
 *
 * @package  Job Manager
 * @since    5.16
 */
class Billrun_Job_Confirm extends Billrun_Job_Abstract {

	protected $method = 'Confirm';
	
	public function fetch() {
		
		$pipeline = array();
		
		$pipeline[] = array(
			'$match' => array(
				'billrun_key' => array(
					'$eq' => (string) $this->config['billrun_key'],
				),
			),
		);
		
		if (isset($this->config['include'])) {
			$pipeline[count($pipeline)-1]['$match']['aid'] = ['$in' => (array) $this->config['include']];
		}
		
		if (isset($this->config['exclude'])) {
			$pipeline[count($pipeline)-1]['$match']['aid'] = ['$nin' => (array) $this->config['exclude']];
		}

		if (isset($this->config['include_invoices'])) {
			$pipeline[count($pipeline)-1]['$match']['invoice_id'] = ['$in' => (array) $this->config['include_invoices']];
		}
		
		if (isset($this->config['exclude_invoices'])) {
			$pipeline[count($pipeline)-1]['$match']['invoice_id'] = ['$nin' => (array) $this->config['exclude_invoices']];
		}

		if (!empty($this->config['invoicing_day'])) {
			$pipeline['$match']['invoicing_day'] = $this->config['invoicing_day'];
		}
		
		if (empty($this->config['force'])) {
			$pipeline[0]['$match']['billed'] = ['$exists' => 0];
		}

		$pipeline[] = array(
			'$project' => array(
				'aid' => 1,
				'billrun_key' => 1,
				'invoice_id' => 1,
			),
		);
		$coll = Billrun_Factory::db()->billrunCollection();
		return $coll->aggregate($pipeline); // todo: allow advanced options for heavy queries
	}

	public function run() {
		$jobSettings = array();
		
		if (!empty($this->config['invoicing_day'])) {
			$jobSettings['invoicing_day'] = $this->config['invoicing_day'];
		}
		
		$count = 0;
		$parent = $this->queueMsg->md5;
		foreach ($this->data as $entry) {
			$jobSettings['aid'] = $entry['aid'];
			$jobSettings['invoices'] = $entry['invoice_id'];
			$jobSettings['stamp'] = $entry['billrun_key'];
			
			Billrun_Factory::log("Going to create job confirm account " . $entry['aid']);
			Billrun_Jobsmanager::getInstance($this->queueMsg->getQueue())->push('Confirm_Account', $jobSettings, $parent);
			$count++;
		}
		Billrun_Factory::log("Created " . $count . " confirm account jobs into queue", Zend_Log::INFO);
	}

}

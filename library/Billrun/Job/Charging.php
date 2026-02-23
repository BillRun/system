<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Job Manager of Charging
 *
 * @package  Job Manager
 * @since    5.16
 */
class Billrun_Job_Charging extends Billrun_Job_Abstract {

	protected $method = 'Charging';
	
	public function fetch() {
		if (isset($this->config['include'])) {
			$filtersQuery = array(
				'aid' => array(
					'$in' => (array) $this->config['include'],
				),
			);
		} elseif (isset($this->config['exclude'])) {
			$filtersQuery = array(
				'aid' => array(
					'$nin' => (array) $this->config['exclude'],
				),
			);
		} else {
			$filtersQuery = [];
		}
		
		if (!empty($this->config['pay_mode'])) {
			return Billrun_Bill::getBillsAggregateValues($filtersQuery, $this->config['pay_mode']);
		}
		return Billrun_Bill::getBillsAggregateValues($filtersQuery);
	}

	public function run() {
		$jobSettings = array();
		
		if (isset($this->config['mode'])) {
			$jobSettings['mode'] = $this->config['mode'];
		}
		
		if (isset($this->config['pay_mode'])) {
			$jobSettings['pay_mode'] = $this->config['pay_mode'];
		}
		
		if (isset($this->config['min_invoice_date'])) {
			$jobSettings['min_invoice_date'] = $this->config['min_invoice_date'];
		}
		
		if (isset($this->config['billrun_key'])) {
			$jobSettings['billrun_key'] = $this->config['billrun_key'];
		}
		
		$count = 0;
		$parent = $this->queueMsg->md5;
		foreach ($this->data as $entry) {
			$jobSettings['aid'] = $entry['aid'];
			Billrun_Factory::log("Going to create job charging account " . $entry['aid']);
			Billrun_Jobsmanager::getInstance()->push('Charging_Account', $jobSettings, $parent);
			$count++;
		}
		Billrun_Factory::log("Created " . $count . " charging account jobs into queue", Zend_Log::INFO);
	}

}

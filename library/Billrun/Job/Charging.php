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

	protected $method = 'charging';
	
	public function fetch() {
		$pipeline = array();
		
		if (isset($this->config['include'])) {
			$pipeline[] = array(
				'$match' => array(
					'aid' => array(
						'$in' => (array) $this->config['include'], // @todo: input validation
					),
				),
			);
		}
		
		if (isset($this->config['exclude'])) {
			$exclude = (array) $this->config['exclude'];
		} else {
			$exclude = [];
		}
		
		if (isset($this->config['billrun_key'])) {
			$pipeline = array_merge($pipeline, $this->cyclePipeline($this->config['billrun_key'], $exclude));
		} else {
			$pipeline = array_merge($pipeline, $this->outstandingBalancePipeline(0.005, $exclude)); // todo: take 0.005 from config
		}
		
		$coll = Billrun_Factory::db()->billsCollection();
		return $coll->aggregate($pipeline); // todo: allow advanced options for heavy queries
	}
	
	protected function outstandingBalancePipeline($min_outstanding = 0.005, $exclude = []) {
		$ret = [];
		$ret[] = array(
			'$group' => array(
				'_id' => '$aid',
				'due' => array(
					'$sum' => '$due'
				),
			),
		);
		$ret[] = array(
			'$match' => array(
				'$or' => array(
					array('due' => array('$lte' => (-1 * $min_outstanding))), // todo: take from config
					array('due' => array('$gte' => $min_outstanding)), // todo: take from config
				)
			)
		);
		
		if (!empty($exclude)) {
			$ret[count($ret)-1]['$match']['aid'] = ['$nin' => $exclude];
		}
		
		$ret[] = array(
			'$project' => array(
				'_id' => 0,
				'aid' => '$_id',
				'due' => '$due',
			),
		);
		return $ret;
	}
	
	protected function cyclePipeline($billrun_key, $exclude = []) {
		$ret = array();
		$ret[] = array(
			'$match' => array(
				'type' => 'inv',
				'billrun_key' => $billrun_key,
			)
		);
		
		if (!empty($this->config['invoicing_day'])) {
			$ret[count($ret)-1]['$match']['invoicing_day'] = $this->config['invoicing_day'];
		}
		
		if (!empty($exclude)) {
			$ret[count($ret)-1]['$match']['aid'] = ['$nin' => $exclude];
		}

		$ret[] = array(
			'$project' => array(
				'_id' => 0,
				'aid' => '$aid',
			),
		);
		return $ret;
	}

	public function run() {
		$jobSettings = array();
		
		if (isset ($this->config['mode'])) {
			$jobSettings['mode'] = $this->config['mode'];
		}
		
		$count = 0;
		$parent = $this->queueMsg->md5;
		foreach ($this->data as $entry) {
			$jobSettings['aid'] = $entry['aid'];
			Billrun_Factory::log("Going to create job charging account " . $entry['aid']);
			Billrun_Jobsmanager::getInstance($this->queueMsg->getQueue())->push('Charging_Account', $jobSettings, $parent);
			$count++;
		}
		Billrun_Factory::log("Created " . $count . " charging account jobs into queue", Zend_Log::INFO);
	}

}

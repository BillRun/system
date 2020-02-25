<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * BillRun CollectionsSteps class
 *
 * @package  Billrun
 * @since    5.0
 */
class Billrun_CollectionSteps_Db extends Billrun_CollectionSteps {

	/**
	 * The instance of the DB collection.
	 */
	protected $collection;

	/**
	 * Construct a new account DB instance.
	 * @param array $options - Array of initialization parameters.
	 */
	public function __construct($options = array()) {
		parent::__construct($options);
		$this->collection = Billrun_Factory::db()->collection_stepsCollection();
	}

	public function createCollectionSteps($aid) {
		$steps = Billrun_Factory::config()->getConfigValue('collection.steps', Array());
		$create_date = new MongoDate();
		$newSteps = array();
		foreach ($steps as $step) {
			if ($step['active']) {
				unset($step['active']);
				$do_after_days = '+' . intval($step['do_after_days']) . ' days';
				$trigger_date = new MongoDate(strtotime($do_after_days));
				$newStep = array();
				$newStep['step_code'] = $step['name'];
				$newStep['step_type'] = $step['type'];
				if(!empty($step['content'])){
					foreach ($step['content'] as $key => $value) {
						if($key !== 'custom_parameter') {
							$newStep['step_config'][$key] = $value;
						}
					}
				}
				if(!empty($step['content']['custom_parameter'])){
					foreach ($step['content']['custom_parameter'] as $key => $value) {
						$newStep['extra_params'][$key] = $value;
					}
				}
				$newStep['extra_params']['aid'] = $aid;
				$newStep['stamp'] = Billrun_Util::generateArrayStamp($newStep);
				$newStep['trigger_date'] = $trigger_date;
				$newStep['creation_time'] = $create_date;
				$newSteps[] = $newStep;
			}
		}
		if (!empty($newSteps)) {
			$this->collection->batchInsert($newSteps);
		}
	}

	public function removeCollectionSteps($aid) {
		$query = array(
			'extra_params.aid' => $aid,
			'notify_time' => array('$exists' => false)
		);
		$this->collection->remove($query);
	}

	public function runCollectStep($aids = array()) {
		$result = array();
		$steps = $this->getReadySteps($aids);
		foreach ($steps as $step) {
			$status = $this->runStep($step);
			if ($status !== false) {
				$this->markStepAsCompleted($step, $status);
				$result['completed'][$step['extra_params']['aid']][] = $step['stamp'];
			} else {
				$result['error'][$step['extra_params']['aid']][] = $step['stamp'];
			}
		}
		Billrun_Factory::log("Collect Step run result: " . print_r($result, 1), Zend_Log::INFO);
		return $result;
	}

	protected function getReadySteps($aids = array()) {
		$results = array();
		$ttl_value = intval(Billrun_Factory::config()->getConfigValue('collection.settings.step_ttl_value', 90));
		$ttl_type = Billrun_Factory::config()->getConfigValue('collection.settings.step_ttl_type', 'days');
		$step_ttl = "-{$ttl_value} {$ttl_type}";
		$query = array(
			'trigger_date' => array(
				'$lte' => new MongoDate(),
				'$gte' => new MongoDate(strtotime($step_ttl)),
			),
			'notify_time' => array('$exists' => false),
		);
		if (!empty($aids)) {
			$query['extra_params.aid']['$in'] = $aids;
		}
		$cursor = $this->collection->query($query)->cursor();
		foreach ($cursor as $row) {
			$results[] = $row->getRawData();
		}
		return $results;
	}

	protected function runStep($step) {
		$notifier = new Billrun_CollectionSteps_Notifier($step);
		return $notifier->notify();
	}

	protected function markStepAsCompleted($step, $response) {
		$query = array(
			'_id' => $step['_id'],
		);
		$update = array(
			'$set' => array(
				'notify_time' => new MongoDate(),
				'returned_value' => $response,
			),
		);
		try {
			return $this->collection->update($query, $update);
		} catch (Exception $exc) {
			Billrun_Factory::log("Unable to mark collection step as completed, ID: " . $step['stamp'], Zend_Log::INFO);
			return FALSE;
		}

	}
	
	public function runCollectionStateChange($aids, $in = true) {
		if (empty($aids)) {
			return true;
		}
		$url = Billrun_Factory::config()->getConfigValue('collection.settings.change_state_url', '');
		$method = Billrun_Factory::config()->getConfigValue('collection.settings.change_state_method', 'POST');
		$step = array(
			'step_code' => "collection state change",
			'step_type' => "httpnoack",
			'step_config' => array(
				'url' => $url,
				'method' => $method,
			),
			'extra_params'=> array(
				'state' => $in ? 'in_collection' : 'out_of_collection',
				'aids' => $aids,
			),
			'creation_time' => date('c')
		);
		return $this->runStep($step);
	}
	
	

}

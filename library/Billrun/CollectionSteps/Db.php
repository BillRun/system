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

				$step['aid'] = $aid;
				$step['done'] = false;
				$step['create_date'] = $create_date;
				$step['trigger_date'] = $trigger_date;
				$newSteps[] = $step;
			}
		}
		if (!empty($newSteps)) {
			$this->collection->batchInsert($newSteps);
		}
	}

	public function removeCollectionSteps($aid) {
		$query = array(
			'aid' => $aid,
			'done' => false
		);
		$this->collection->remove($query);
	}

	public function runCollectStep($aids = array()) {
		$result = array();
		$steps = $this->getReadySteps($aids);
		foreach ($steps as $step) {
			if ($this->runStep($step)) {
				$this->markStepAsCompleted($step);
				$result['completed'][$step['aid']][] = $step['name'];
			} else {
				$result['error'][$step['aid']][] = $step['name'];
			}
		}
		Billrun_Factory::log("Collect Step run result: " . print_r($result, 1), Zend_Log::INFO);
		return $result;
	}

	protected function getReadySteps($aids = array()) {
		$results = array();
		$query = array(
			'done' => false,
			'trigger_date' => array(
				'$lte' => new MongoDate(),
			)
		);
		if (!empty($aids)) {
			$query['aid']['$in'] = $aids;
		}

		$cursor = $this->collection->query($query)->cursor();
		foreach ($cursor as $row) {
			$results[] = $row->getRawData();
		}
		return $results;
	}

	protected function runStep($step) {
		$task = new Billrun_CollectionSteps_TaskManager($step);
		$result = $task->run();
		return $result;
	}

	protected function markStepAsCompleted($step) {
		$update = array('done' => true);
		try {
			$this->collection->update(array('_id' => $step['_id']), array('$set' => $update));
		} catch (Exception $exc) {
			Billrun_Factory::log("Unable to mark collection step as completed, ID: " . $id, Zend_Log::INFO);
			return FALSE;
		}
		return true;
	}

}

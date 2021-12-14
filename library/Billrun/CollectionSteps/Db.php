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

	protected function getClosestValidDate($date, $on_holidays, $on_days, $findDateRetry = 0) {
		$valid_date = true;
		if ($findDateRetry >= 30) { // to prevent recursion loop, search max for 30 days.
			return $date;
		}
		if (!$on_holidays && $valid_date) {
			$is_holiday = in_array(Billrun_HebrewCal::getDayType($date), [HEBCAL_SHORTDAY, HEBCAL_HOLIDAY]);
			if ($is_holiday) {
				$valid_date = false;
			}
		}
		if (!empty($on_days) && $valid_date) { // default if not set = true
			$all_days_disabled_or_enabled = count($on_days) == 7 && (!in_array(true, $on_days) || !in_array(false, $on_days)); // ignore configuration
			if (!$all_days_disabled_or_enabled) {
				$day_num = intval(date('w', $date));
				if (isset($on_days[$day_num]) && !$on_days[$day_num]) {
					$valid_date = false;
				}
			}
		}
		if ($valid_date) {
			return $date;
		}
		$findDateRetry++;
		$next_date = strtotime("+1 day", $date); // check next day
		return $this->getClosestValidDate($next_date, $on_holidays, $on_days, $findDateRetry);
	}

	protected function getClosestValidTime($date, $on_hours) {
		if (!empty($on_hours)) {
			// get rendom period allowd to avoid mass step execution at same time
			$rand_range = $on_hours[array_rand($on_hours)];
			$from_time = $rand_range[0];
			$to_time = $rand_range[1];
			$to_time_day = ($from_time > $to_time) ? "tomorrow" : "today";
			// get random datetime from rendom period
			$from_date = strtotime("today {$from_time}", $date);
			$to_date = strtotime("{$to_time_day} {$to_time}", $date);
			$rand_date = mt_rand($from_date, $to_date);
			return $rand_date;
		}
		return $date;
	}

	protected function getStepTriggerTime($step) {
		$on_holidays = (isset($step['run_on']['holidays'])) ? $step['run_on']['holidays'] : Billrun_Factory::config()->getConfigValue('collection.settings.run_on_holidays', false);
		$on_days = (isset($step['run_on']['days'])) ? $step['run_on']['days'] : Billrun_Factory::config()->getConfigValue('collection.settings.run_on_days', array());
		$on_hours = (isset($step['run_on']['hours'])) ? $step['run_on']['hours'] : Billrun_Factory::config()->getConfigValue('collection.settings.run_on_hours', array());
		$do_after_days = '+' . intval($step['do_after_days']) . ' days';
		$triggerDate = strtotime($do_after_days);
		$triggerDate = $this->getClosestValidTime($triggerDate, $on_hours);
		$triggerDate = $this->getClosestValidDate($triggerDate, $on_holidays, $on_days);
		return new Mongodloid_Date($triggerDate);
	}

	public function createCollectionSteps($aid) {
		$steps = Billrun_Factory::config()->getConfigValue('collection.steps', Array());
		$create_date = new Mongodloid_Date();
		$newSteps = array();
		foreach ($steps as $step) {
			if ($step['active']) {
				unset($step['active']);
				$trigger_date = $this->getStepTriggerTime($step);
				$newStep = array();
				$newStep['step_code'] = $step['name'];
				$newStep['step_type'] = $step['type'];
				if (!empty($step['content'])) {
					foreach ($step['content'] as $key => $value) {
						if ($key !== 'custom_parameter') {
							$newStep['step_config'][$key] = $value;
						}
					}
				}
				if (!empty($step['content']['custom_parameter'])) {
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
				'$lte' => new Mongodloid_Date(),
				'$gte' => new Mongodloid_Date(strtotime($step_ttl)),
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

	/**
	 * trigger the step
	 * 
	 * @param array $step collection step details
	 * 
	 * @return mixed array response details if success, else false
	 */
	protected function triggerStep($step) {
		$notifier = new Billrun_CollectionSteps_Notifier($step);
		return $notifier->notify();
	}

	protected function markStepAsCompleted($step, $response) {
		$query = array(
			'_id' => $step['_id'],
		);
		$update = array(
			'$set' => array(
				'notify_time' => new Mongodloid_Date(),
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
			'extra_params' => array(
				'state' => $in ? 'in_collection' : 'out_of_collection',
				'aids' => $aids,
			),
			'creation_time' => date('c')
		);
		return $this->runStep($step);
	}

}

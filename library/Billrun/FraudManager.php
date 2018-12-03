<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Handles fraud events
 *
 */
class Billrun_FraudManager {
	
	/**
	 *
	 * @var Billrun_FraudManager
	 */
	protected static $instance;

	/**
	 * @var string
	 */
	protected static $eventType = 'fraud';

	/**
	 * @var Mongodloid_Collection
	 */
	protected $collection;
	
	/**
	 * @var array
	 */
	protected $eventsSettings;
	
	/**
	 * @var unixtimestamp
	 */
	protected $runTime;
	
	/**
	 * @param array
	 */
	protected static $availableThresholds = ['usagev', 'aprice', 'final_charge', 'in_group', 'over_group', 'out_group'];

	private function __construct($params = []) {
		$this->runTime = time();
		$this->eventsSettings = Billrun_Factory::config()->getConfigValue('events.fraud', []);
		$this->collection = Billrun_Factory::db()->linesCollection();
	}

	public static function getInstance($params) {
		if (is_null(self::$instance)) {
			self::$instance = new Billrun_FraudManager($params);
		}
		return self::$instance;
	}
	
	public function run($params = []) {
		foreach ($this->getEventsToRun($params) as $eventSettings) {
			$this->runFraudEvent($eventSettings);
		}
	}
	
	protected function getEventsToRun($params = []) {
		$eventsToRun = [];
		foreach ($this->eventsSettings as $eventSettings) {
			if ($this->shouldRunEvent($eventSettings, $params)) {
				$eventsToRun[] = $eventSettings;
			}
		}

		return $eventsToRun;
	}
	
	protected function shouldRunEvent($eventSettings, $params = []) {
		return (Billrun_Util::getIn($eventSettings, 'recurrence.type', '') == $params['recurrenceType']) &&
			(in_array(Billrun_Util::getIn($eventSettings, 'recurrence.value', ''), $params['recurrenceValues']));
	}
	
	protected function runFraudEvent($eventSettings) {
		foreach ($this->getFraudEventResults($eventSettings) as $res) {
			$extraParams = [
				'aid' => $res['aid'],
				'sid' => $res['sid'],
				'row' => [],
			];
			foreach (self::$availableThresholds as $availableThreshold) {
				if (isset($res[$availableThreshold])) {
					$extraParams['row'][$availableThreshold] = $res[$availableThreshold];
				}
			}
			$timeRange = $this->getFraudEventsQueryTimeRange($eventSettings);
			$extraValues = [
				'max_urt' => $res['max_urt'],
				'from' => new MongoDate($timeRange['from']),
				'to' => new MongoDate($timeRange['to']),
			];
			$eventSettingsToSave = $this->getEventSettingsToSave($eventSettings);
			Billrun_Factory::eventsManager()->saveEvent(self::$eventType, $eventSettingsToSave, [], [], [], $extraParams, $extraValues);
		}
	}
	
	protected function getEventSettingsToSave($eventSettings) {
		$ret = $eventSettings;
		unset($ret['recurrence'], $ret['date_range'], $ret['conditions'], $ret['lines_overlap'], $ret['threshold_conditions']);
		$ret['thresholds'] = $eventSettings['threshold_conditions'];
		foreach ($ret['thresholds'] as &$thresholdSet) {
			foreach ($thresholdSet as &$threshold) {
				unset($threshold['op']);
			}
		}
		return $ret;
	}
	
	protected function getFraudEventResults($eventSettings) {
		$match = $this->getFraudEventsQueryMatch($eventSettings);
		$group = $this->getFraudEventsQueryGroup($eventSettings);
		$matchThresholds = $this->getFraudEventsQueryThresholds($eventSettings);
		$project = $this->getFraudEventsQueryProject($eventSettings);
		$res = $this->collection->aggregate($match, $group, $matchThresholds, $project);
		return $res;
	}
	
	protected function getFraudEventsQueryMatch($eventSettings) {
		$timeRange = $this->getFraudEventsQueryTimeRange($eventSettings);
		$dateRangeStart = $timeRange['from'];
		$dateRangeEnd = $timeRange['to'];
		$basicMatch = [
			'urt' => [
				'$gte' => new MongoDate($dateRangeStart),
				'$lt' => new MongoDate($dateRangeEnd),
			],
		];
		$conditionsMatch = $this->buildConditionsMatchQuery($eventSettings['conditions']);
		$match = array_merge($basicMatch, $conditionsMatch);
		return [ '$match' => $match ];
	}
	
	protected function getFraudEventsQueryGroup($eventSettings) {
		$group = [
			'_id' => [
				'sid' => '$sid',
				'aid' => '$aid',
			],
			'max_urt' => [ '$max' => '$urt' ],
		];
		foreach (self::$availableThresholds as $availableThreshold) {
			$group[$availableThreshold] = [ '$sum' => '$' . $availableThreshold ];
		}
		
		return [ '$group' => $group ];
	}
	
	protected function getFraudEventsQueryProject($eventSettings) {
		$project = [
			'_id' => 0,
			'sid' => '$_id.sid',
			'aid' => '$_id.aid',
			'max_urt' => '$max_urt',
		];
		foreach (self::$availableThresholds as $availableThreshold) {
			$project[$availableThreshold] = '$' . $availableThreshold;
		}
		
		return [ '$project' => $project ];
	}
	
	protected function getFraudEventsQueryThresholds($eventSettings) {
		return [ '$match' => $this->buildConditionsMatchQuery($eventSettings['threshold_conditions']) ];
	}
	
	protected function getFraudEventsQueryTimeRange($eventSettings) {
		$dateRangeStart = strtotime('-' .
			$eventSettings['date_range']['value'] . ' ' .
			($eventSettings['date_range']['type'] == 'hourly' ? 'hours' : 'minutes'),
			$this->runTime
		);
		$dateRangeEnd = $this->runTime;
		return [
			'from' => $dateRangeStart,
			'to' => $dateRangeEnd,
		];
	}
	
	protected function buildConditionsMatchQuery($conditionsSettings) {
		$match = [
			'$or' => [],
		];
		
		foreach ($conditionsSettings as $conditionsSet) {
			$conditionsSetMatch = [ '$and' => [] ];
			foreach ($conditionsSet as $conditionConfig) {
				$condition = [
					$conditionConfig['field'] => [ $conditionConfig['op'] => $conditionConfig['value'] ],
				];
				$conditionsSetMatch['$and'][] = $condition;
			}
			$match['$or'][] = $conditionsSetMatch;
		}
		
		return $match;
	}
	
}

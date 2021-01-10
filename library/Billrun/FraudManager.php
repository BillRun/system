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
	 * @var Mongodloid_Collection
	 */
	protected $eventsCollection;
	
	/**
	 * @var array
	 */
	protected $eventsInTimeRange = [];
	
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
		$this->collection = Billrun_Factory::db()->linesCollection();
		$this->eventsCollection = Billrun_Factory::db()->eventsCollection();
	}

	public static function getInstance($params) {
		if (is_null(self::$instance)) {
			self::$instance = new Billrun_FraudManager($params);
		}
		return self::$instance;
	}
	
	public function run($params = []) {
		Billrun_Factory::log('Fraud manager running', Billrun_Log::INFO);
		foreach ($this->getEventsToRun($params) as $eventSettings) {
			$this->runFraudEvent($eventSettings);
		}
		Billrun_Factory::log('Fraud manager running done', Billrun_Log::INFO);
	}
	
	protected function getEventsToRun($params = []) {
		$eventsToRun = [];
		$eventsSettings = Billrun_Factory::eventsManager()->getEventsSettings('fraud');
		foreach ($eventsSettings as $eventSettings) {
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
		Billrun_Factory::log('Fraud manager running event ' . $eventSettings['event_code'], Billrun_Log::INFO);
		foreach ($this->getFraudEventResults($eventSettings) as $res) {
			Billrun_Factory::log('Fraud manager running event ' . $eventSettings['event_code'] . ' on subscriber ' . $res['sid'] . ' account ' . $res['aid'], Billrun_Log::INFO);
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
				'from' => new Mongodloid_Date($timeRange['from']),
				'to' => new Mongodloid_Date($timeRange['to']),
			];
			$eventSettingsToSave = $this->getEventSettingsToSave($eventSettings);
			Billrun_Factory::eventsManager()->saveEvent(self::$eventType, $eventSettingsToSave, [], [], [], $extraParams, $extraValues);
		}
		Billrun_Factory::log('Fraud manager done running event ' . $eventSettings['event_code'], Billrun_Log::INFO);
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
		$thresholdsMatch = $this->getFraudEventsQueryThresholds($eventSettings);
		$ret = iterator_to_array($this->collection->aggregate($match, $group, $thresholdsMatch));
			
		foreach($this->eventsInTimeRange as $eventInTimeRange) {
			$timeRange = $this->getFraudEventsQueryTimeRange($eventSettings);
			$match['$match']['sid'] = $eventInTimeRange['sid'];
			$match['$match']['aid'] = $eventInTimeRange['aid'];
			$match['$match']['urt'] = [
				'$gt' => $eventInTimeRange['max_urt'],
				'$lt' => new Mongodloid_Date($timeRange['to']),
			];
			$excludedSubRes = iterator_to_array($this->collection->aggregate($match, $group, $thresholdsMatch));
			$ret = array_merge($ret, $excludedSubRes);
		}
		
		return $ret;
	}
	
	protected function getFraudEventsQueryMatch($eventSettings) {
		$timeRange = $this->getFraudEventsQueryTimeRange($eventSettings);
		$dateRangeStart = $timeRange['from'];
		$dateRangeEnd = $timeRange['to'];
		$basicMatch = [
			'urt' => [
				'$gte' => new Mongodloid_Date($dateRangeStart),
				'$lt' => new Mongodloid_Date($dateRangeEnd),
			],
		];
		$conditionsMatch = $this->buildConditionsMatchQuery($eventSettings['conditions']);
		$match = array_merge($basicMatch, $conditionsMatch);
		$sidsToExclude = $this->getFraudEventsQueryExcludeSubscribers($eventSettings);
		if (!empty($sidsToExclude)) {
			$match['sid'] = [
				'$nin' => $sidsToExclude,
			];
		}
		return [ '$match' => $match ];
	}
	
	protected function getFraudEventsQueryExcludeSubscribers($eventSettings) {
		if (Billrun_Util::getIn($eventSettings, 'lines_overlap', true)) {
			$this->eventsInTimeRange = [];
			return false;
		}
		
		$this->eventsInTimeRange = iterator_to_array($this->getEventsInTimeRange($eventSettings));
		if (empty($this->eventsInTimeRange)) {
			$this->eventsInTimeRange = [];
			return false;
		}

		$sidsToExclude = [];
		foreach ($this->eventsInTimeRange as $eventInTimeRange) {
			$sidsToExclude[] = $eventInTimeRange['sid'];
		}
		return $sidsToExclude;
	}
	
	protected function getEventsInTimeRange($eventSettings) {
		$timeRange = $this->getFraudEventsQueryTimeRange($eventSettings);
		$match = [
			'$match' => [
				'max_urt' => [
					'$gte' => new Mongodloid_Date($timeRange['from']),
					'$lt' => new Mongodloid_Date($timeRange['to']),
				],
			],
		];
		$group = [
			'$group' => [
				'_id' => [
					'sid' => '$extra_params.sid',
					'aid' => '$extra_params.aid',
				],
				'aid' => [ '$first' => '$extra_params.aid' ],
				'sid' => [ '$first' => '$extra_params.sid' ],
				'max_urt' => [
					'$max' => '$max_urt',
				],
 			],
 		];
		return $this->eventsCollection->aggregate($match, $group);
	}
	
	protected function getFraudEventsQueryGroup($eventSettings) {
		$group = [
			'_id' => [
				'sid' => '$sid',
				'aid' => '$aid',
			],
			'aid' => [ '$first' => '$aid' ],
			'sid' => [ '$first' => '$sid' ],
			'max_urt' => [ '$max' => '$urt' ],
		];
		foreach (self::$availableThresholds as $availableThreshold) {
			$group[$availableThreshold] = [ '$sum' => '$' . $availableThreshold ];
		}
		
		return [ '$group' => $group ];
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
					$conditionConfig['field'] => [ '$' . $conditionConfig['op'] => $conditionConfig['value'] ],
				];
				$conditionsSetMatch['$and'][] = $condition;
			}
			$match['$or'][] = $conditionsSetMatch;
		}
		
		return $match;
	}
	
}

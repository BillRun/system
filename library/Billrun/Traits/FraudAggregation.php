<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * A Trait to allow genereic fraud aggregation
 *
 * @package  Billing
 * @since    0.2
 */
trait Billrun_Traits_FraudAggregation {

	/**
	 *
	 * @var type 
	 */
	protected $fraudConfig = array();
	
	protected $fraudCollection = null;

	public function __construct($options = array()) {
		$this->initFraudAggregation();
		
	}

	protected function initFraudAggregation() {
		$this->fraudCollection = Billrun_Factory::db()->linesCollection();
		$this->fraudConfig = Billrun_Factory::config()->getConfigValue('fraud', $this->fraudConfig);
		$this->fraudConfig = array_merge($this->fraudConfig, Billrun_Factory::config()->getConfigValue($this->getName() . '.fraud', $this->fraudConfig));
	}

	/**
	 * Collect events  using  the configured values.
	 * @param type $groupName
	 * @param type $groupIds
	 */
	protected function collectFraudEvents($groupName, $groupIds, $baseQuery) {		
		$events = array();
		$timeField = $this->getTimeField();

		if (isset($this->fraudConfig['events']) && is_array($this->fraudConfig['events'])) {
			foreach ($this->fraudConfig['events'] as $key => $eventRules) {
				// check to see if the event included in a group if not continue to the next one
				if (isset($eventRules['groups']) && !in_array($groupName, $eventRules['groups'])) {
					continue;
				}

				foreach ($eventRules['rules'] as $eventQuery) {
					Billrun_Factory::log()->log("FraudAggregation::collectFraudEvents collecting {$eventQuery['name']} exceeders in group {$groupName}", Zend_Log::DEBUG);

					$query = $baseQuery;
					$eventQuery = $this->prepareRuleQuery($eventQuery, $key);
					$charge_time = new MongoDate(isset($eventQuery['time_period']) ? strtotime($eventQuery['time_period']) : Billrun_Util::getLastChargeTime(true));
					$query['base_match']['$match'][$timeField]['$gte'] = $charge_time;

					$project = $query['project'];
					$project['$project'] = array_merge($project['$project'], $this->addToProject((!empty($eventRules['added_values']) ? $eventRules['added_values'] : array())), $this->addToProject(array('units' => $eventQuery['units'], 'event_type' => $key,
								'threshold' => $eventQuery['threshold'], 'target_plans' => Billrun_Util::getFieldVal($eventRules['target_plans'], $this->fraudConfig['defaults']['target_plans']) )));
					$project['$project']['value'] = $eventQuery['value'];
					$project['$project'][$eventQuery['name']] = $eventQuery['value'];
					$query['project'] = $project;

					if(empty($query['group']['$group'][substr($eventQuery['value'],1)]) && $eventQuery['value'][0] == '$') {
						$addValField =substr($eventQuery['value'],1);
						$query['group']['$group'][$addValField] = ['$sum' => $eventQuery['value']];
						if(empty($query['translate']['$project'][$addValField])) {
							if(!empty($eventQuery['translate_operation'])) {
								$tOperation = [];
								foreach($eventQuery['translate_operation'] as $op => $opValues) {
									foreach($opValues as  &$opValue) {
										if(is_numeric($opValue)) {
											$opValue = floatval($opValue);
										}
									}

									$tOperation['$'.$op] = $opValues;
								}
								$query['translate']['$project'][$addValField] = $tOperation;
							} else {
								$query['translate']['$project'][$addValField] = 1;
							}

						}
					}

					$query['where']['$match'] = array_merge($query['where']['$match'], (isset($eventQuery['query']) ? $this->parseEventQuery($eventQuery['query']) : array()), (isset($eventRules['group_rules'][$groupName]) ? $this->parseEventQuery($eventRules['group_rules'][$groupName]) : array()));
					$ruleMatch = array('$match' => (isset($eventQuery['match']) ? $eventQuery['match'] : array('value' => array('$gte' => intval($eventQuery['threshold']))) ));
					//Billrun_Factory::log(json_encode(array($query['base_match'], $query['where'], $query['group_match'], $query['group'], $query['translate'], $query['project'], $ruleMatch)));
					$ret = $this->fraudCollection->aggregate( array($query['base_match'], $query['where'], $query['group_match'], $query['group'], $query['translate'], $query['project'], $ruleMatch), array("allowDiskUse" => true) );

					if ($ret = $this->postProcessEventResults($events, $ret, $eventQuery, $key)) {
						$events = array_merge($events, $ret);
					}

					Billrun_Factory::log()->log("FraudAggregation::collectFraudEvents found " . count($ret) . " exceeders on rule {$eventQuery['name']} ", Zend_Log::INFO);
				}
			}
		}

		return $events;
	}

	protected function parseEventQuery($parameters) {
		$query = array();
		foreach ($parameters as $parameter) {
			if (isset($parameter['type'])) {
				switch ($parameter['type']) {
					case 'number':
						$value = floatval($parameter['value']);
						break;
					case 'regex':
						$value = array('$regex' => $parameter['value']);
						break;
					case 'boolean':
						$value = (boolean) $parameter['value'];
						break;
					case 'exists':
						$value = array('$exists' => intval($parameter['value']));
						break;
				}
			} else {
				$value = $parameter['value'];
			}
			$query[$parameter['field']] = $value;
		}
		return $query;
	}

	/**
	 * Get query part to add fields with values to aggregation projection.
	 * @param type $valueArr
	 * @return array
	 */
	protected function addToProject($valueArr) {
		$retArr = array();
		foreach ($valueArr as $key => $value) {
			$retArr[$key] = array('$cond' => array(true, $value, $value));
		}
		return $retArr;
	}

	/**
	 * filter events by plans
	 * @param type $events the events to be filtered 
	 * @return type the events that shold be triggered.
	 */
	protected function filterEvents($events) {
		$retEvents = $events;
		foreach ($events as $key => $event) {
			$sub = $this->getSubscriberDataFromIMSI($key);
			if (in_array($sub['plan'], array_keys($this->fraudConfig['plans']))) {
				if (isset($this->fraudConfig['plans'][$sub['plan']]['events'][$event['event_type']]) &&
						in_array($event['group'], $this->fraudConfig['plans'][$sub['plan']]['events'][$event['event_type']])) {
					continue;
				}
				unset($retEvents[$key]);
			}
		}
		return $retEvents;
	}

	/**
	 * (stab function)
	 */
	protected function postProcessEventResults($allEventsResults, $eventResults, $eventQuery, $ruleName) {
		// abstract function doesn't alter anything
		return $eventResults;
	}

	/**
	 * (stab function)
	 * An helper function to allow the user of the trait to extend each of the rules queiries.
	 */
	protected function prepareRuleQuery($eventQuery, $ruleName) {
		return $eventQuery;
	}

	/**
	 * an abstract function to get the name of the current user of the trait
	 * (used to load he correct configuration)
	 */
	abstract function getName();

	/**
	 * an stub function to get the time filed of the current user of the trait lines
	 */
	protected function getTimeField() {
		return "unified_record_time";
	}

}

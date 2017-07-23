<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of EventsManager
 *
 * @author shani
 */
class Billrun_EventsManager {

	const EVENT_TYPE_BALANCE = 'balance';
	const CONDITION_IS = 'is';
	const CONDITION_IS_NOT = 'is_not';
	const CONDITION_IS_LESS_THAN = 'is_less_than';
	const CONDITION_IS_LESS_THAN_OR_EQUAL = 'is_less_than_or_equal';
	const CONDITION_IS_GREATER_THAN = 'is_greater_than';
	const CONDITION_IS_GREATER_THAN_OR_EQUAL = 'is_greater_than_or_equal';
	const CONDITION_REACHED_CONSTANT = 'reached_constant';
	const CONDITION_REACHED_CONSTANT_RECURRING = 'reached_constant_recurring';
	const CONDITION_HAS_CHANGED = 'has_changed';
	const CONDITION_HAS_CHANGED_TO = 'has_changed_to';
	const CONDITION_HAS_CHANGED_FROM = 'has_changed_from';
	const ENTITY_BEFORE = 'before';
	const ENTITY_AFTER = 'after';

//	$em->triggerEvent("vtiger.entity.beforesave.final", $entityData);
	/**
	 *
	 * @var Billrun_EventsManager
	 */
	protected static $instance;
	protected $eventsSettings;
	protected static $allowedExtraParams = array('aid' => 'aid', 'sid' => 'sid', 'stamp' => 'line_stamp');

	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected static $collection;

	private function __construct($params = array()) {
		$this->eventsSettings = Billrun_Factory::config()->getConfigValue('events', []);
		self::$collection = Billrun_Factory::db()->eventsCollection();
	}

	public static function getInstance($params) {
		if (is_null(self::$instance)) {
			self::$instance = new Billrun_EventsManager($params);
		}
		return self::$instance;
	}

	public function trigger($eventType, $entityBefore, $entityAfter, $extraParams = array()) {
		if (!empty($this->eventsSettings[$eventType])) {
			foreach ($this->eventsSettings[$eventType] as $event) {
				foreach ($event['conditions'] as $rawEventSettings) {
					if (!$this->isConditionMet($rawEventSettings['type'], $rawEventSettings, $entityBefore, $entityAfter)) {
						continue 2;
					}
				}
				$this->saveEvent($eventType, $event, $entityBefore, $entityAfter, $extraParams);
			}
		}
	}

	protected function isConditionMet($condition, $rawEventSettings, $entityBefore, $entityAfter) {
		switch ($condition) {
			case self::CONDITION_IS:
				return $this->arrayMatches($this->getWhichEntity($rawEventSettings, $entityBefore, $entityAfter), $rawEventSettings['path'], '$eq', $rawEventSettings['value']);
			case self::CONDITION_IS_NOT:
				return !$this->arrayMatches($this->getWhichEntity($rawEventSettings, $entityBefore, $entityAfter), $rawEventSettings['path'], '$eq', $rawEventSettings['value']);
			case self::CONDITION_IS_LESS_THAN:
				return !$this->arrayMatches($this->getWhichEntity($rawEventSettings, $entityBefore, $entityAfter), $rawEventSettings['path'], '$lt', $rawEventSettings['value']);
			case self::CONDITION_IS_LESS_THAN_OR_EQUAL:
				return !$this->arrayMatches($this->getWhichEntity($rawEventSettings, $entityBefore, $entityAfter), $rawEventSettings['path'], '$lte', $rawEventSettings['value']);
			case self::CONDITION_IS_GREATER_THAN:
				return !$this->arrayMatches($this->getWhichEntity($rawEventSettings, $entityBefore, $entityAfter), $rawEventSettings['path'], '$gt', $rawEventSettings['value']);
			case self::CONDITION_IS_GREATER_THAN_OR_EQUAL:
				return !$this->arrayMatches($this->getWhichEntity($rawEventSettings, $entityBefore, $entityAfter), $rawEventSettings['path'], '$gte', $rawEventSettings['value']);
			case self::CONDITION_HAS_CHANGED:
				return (Billrun_Util::getIn($entityBefore, $rawEventSettings['path'], NULL) != Billrun_Util::getIn($entityAfter, $rawEventSettings['path'], NULL));
			case self::CONDITION_HAS_CHANGED_TO:
				return (Billrun_Util::getIn($entityBefore, $rawEventSettings['path'], NULL) != Billrun_Util::getIn($entityAfter, $rawEventSettings['path'], NULL)) && $this->arrayMatches($entityAfter, $rawEventSettings['path'], '$eq', $rawEventSettings['value']);
			case self::CONDITION_HAS_CHANGED_FROM:
				return (Billrun_Util::getIn($entityBefore, $rawEventSettings['path'], NULL) != Billrun_Util::getIn($entityAfter, $rawEventSettings['path'], NULL)) && $this->arrayMatches($entityBefore, $rawEventSettings['path'], '$eq', $rawEventSettings['value']);
			case self::CONDITION_REACHED_CONSTANT:
				$valueBefore = Billrun_Util::getIn($entityBefore, $rawEventSettings['path'], NULL);
				$valueAfter = Billrun_Util::getIn($entityAfter, $rawEventSettings['path'], NULL);
				$eventValue = $rawEventSettings['value'];
				return ($valueBefore < $eventValue && $eventValue <= $valueAfter) || ($valueBefore > $eventValue && $valueAfter <= $eventValue);
			case self::CONDITION_REACHED_CONSTANT:
				$valueBefore = Billrun_Util::getIn($entityBefore, $rawEventSettings['path'], NULL);
				$valueAfter = Billrun_Util::getIn($entityAfter, $rawEventSettings['path'], NULL);
				$eventValue = $rawEventSettings['value'];
				$eventValueMultiple = ceil($eventValue / $valueBefore);
				return ($valueBefore < $eventValue && $eventValue <= $valueAfter) || ($valueBefore > $eventValue && $valueAfter <= $eventValue);

//	const CONDITION_REACHED_CONSTANT_RECURRING = 'reached_constant_recurring';
	
	

			default:
				return FALSE;
		}
	}
	
	protected function getWhichEntity($rawEventSettings, $entityBefore, $entityAfter) {
		return $rawEventSettings['which'] == self::ENTITY_BEFORE ? $entityBefore : $entityAfter;
	}

	protected function arrayMatches($data, $path, $operator, $value = NULL) {
		if (is_null($value)) {
			$value = Billrun_Util::getIn($data, $path, NULL);
			if (is_null($value)) {
				return FALSE;
			}
		}
		$query = array($path => array($operator => $value));
		return Billrun_Utils_Arrayquery_Query::exists(array($data), $query);
	}

	protected function saveEvent($eventType, $rawEventSettings, $entityBefore, $entityAfter, $extraParams = array()) {
		$event = $rawEventSettings;
		$event['event_type'] = $eventType;
		$event['creation_time'] = new MongoDate();
//		$event['value_before'] = $valueBefore;
//		$event['value_after'] = $valueAfter;
		foreach ($extraParams as $key => $value) {
			if (isset(self::$allowedExtraParams[$key])) {
				$event['extra_params'][self::$allowedExtraParams[$key]] = $value;
			}
		}
		self::$collection->insert($event);
	}

}

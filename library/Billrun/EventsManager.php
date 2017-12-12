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
	const CONDITION_IN = 'in';
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

	public function trigger($eventType, $entityBefore, $entityAfter, $additionalEntities = array(), $extraParams = array()) {
		if (empty($this->eventsSettings[$eventType])) {
			return;
		}
		foreach ($this->eventsSettings[$eventType] as $event) {
			foreach ($event['conditions'] as $rawEventSettings) {
				if (isset($rawEventSettings['entity_type']) && $rawEventSettings['entity_type'] !== $eventType) {
					$conditionEntityAfter = $conditionEntityBefore = $additionalEntities[$rawEventSettings['entity_type']];
				} else {
					$conditionEntityAfter = $entityAfter;
					$conditionEntityBefore = $entityBefore;
				}
				if (!$this->isConditionMet($rawEventSettings['type'], $rawEventSettings, $conditionEntityBefore, $conditionEntityAfter)) {
					continue 2;
				}
			}
			$this->saveEvent($eventType, $event, $entityBefore, $entityAfter, $extraParams);
		}
	}

	protected function isConditionMet($condition, $rawEventSettings, $entityBefore, $entityAfter) {
		switch ($condition) {
			case self::CONDITION_IS:
				return $this->arrayMatches($this->getWhichEntity($rawEventSettings, $entityBefore, $entityAfter), $rawEventSettings['path'], '$eq', $rawEventSettings['value']);
			case self::CONDITION_IN:
				return $this->arrayMatches($this->getWhichEntity($rawEventSettings, $entityBefore, $entityAfter), $rawEventSettings['path'], '$in', $rawEventSettings['value']);
			case self::CONDITION_IS_NOT:
				return !$this->arrayMatches($this->getWhichEntity($rawEventSettings, $entityBefore, $entityAfter), $rawEventSettings['path'], '$eq', $rawEventSettings['value']);
			case self::CONDITION_IS_LESS_THAN:
				return $this->arrayMatches($this->getWhichEntity($rawEventSettings, $entityBefore, $entityAfter), $rawEventSettings['path'], '$lt', $rawEventSettings['value']);
			case self::CONDITION_IS_LESS_THAN_OR_EQUAL:
				return $this->arrayMatches($this->getWhichEntity($rawEventSettings, $entityBefore, $entityAfter), $rawEventSettings['path'], '$lte', $rawEventSettings['value']);
			case self::CONDITION_IS_GREATER_THAN:
				return $this->arrayMatches($this->getWhichEntity($rawEventSettings, $entityBefore, $entityAfter), $rawEventSettings['path'], '$gt', $rawEventSettings['value']);
			case self::CONDITION_IS_GREATER_THAN_OR_EQUAL:
				return $this->arrayMatches($this->getWhichEntity($rawEventSettings, $entityBefore, $entityAfter), $rawEventSettings['path'], '$gte', $rawEventSettings['value']);
			case self::CONDITION_HAS_CHANGED:
				return (Billrun_Util::getIn($entityBefore, $rawEventSettings['path'], NULL) != Billrun_Util::getIn($entityAfter, $rawEventSettings['path'], NULL));
			case self::CONDITION_HAS_CHANGED_TO:
				return (Billrun_Util::getIn($entityBefore, $rawEventSettings['path'], NULL) != Billrun_Util::getIn($entityAfter, $rawEventSettings['path'], NULL)) && $this->arrayMatches($entityAfter, $rawEventSettings['path'], '$eq', $rawEventSettings['value']);
			case self::CONDITION_HAS_CHANGED_FROM:
				return (Billrun_Util::getIn($entityBefore, $rawEventSettings['path'], NULL) != Billrun_Util::getIn($entityAfter, $rawEventSettings['path'], NULL)) && $this->arrayMatches($entityBefore, $rawEventSettings['path'], '$eq', $rawEventSettings['value']);
			case self::CONDITION_REACHED_CONSTANT:
				$valueBefore = Billrun_Util::getIn($entityBefore, $rawEventSettings['path'], 0);
				$valueAfter = Billrun_Util::getIn($entityAfter, $rawEventSettings['path'], 0);
				$eventValue = $rawEventSettings['value'];
				return ($valueBefore < $eventValue && $eventValue <= $valueAfter) || ($valueBefore > $eventValue && $valueAfter <= $eventValue);
			case self::CONDITION_REACHED_CONSTANT_RECURRING:
				$rawValueBefore = Billrun_Util::getIn($entityBefore, $rawEventSettings['path'], 0);
				$rawValueAfter = Billrun_Util::getIn($entityAfter, $rawEventSettings['path'], 0);
				$eventValue = $rawEventSettings['value'];
				$valueBefore = ceil($rawValueBefore / $eventValue);
				$valueAfter = ceil($rawValueAfter / $eventValue);
				return (intval($valueBefore) != intval($valueAfter));
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
		$event['stamp'] = Billrun_Util::generateArrayStamp($event);
		self::$collection->insert($event);
	}
	
	/**
	 * used for Cron to handle the events exists in the system
	 */
	public function notify() {
		$events = $this->getEvents();
		foreach ($events as $event) {
			$response = Billrun_Events_Notifier::notify($event->getRawData());
			if ($response === false) {
				Billrun_Factory::log('Error notify event. Event details: ' . print_R($event, 1), Billrun_Log::NOTICE);
				continue;
			}
			$this->addEventResponse($event, $response);
		}
	}
	
	/**
	 * get all events that were found in the system and was not already handled
	 * 
	 * @return type
	 */
	protected function getEvents() {
		$query = array(
			'notify_time' => array('$exists' => false),
		);
		
		return self::$collection->query($query);
	}
	
	/**
	 * add response data to event and update notification time
	 * 
	 * @param array $event
	 * @param array $response
	 * @return mongo update result.
	 */
	protected function addEventResponse($event, $response) {
		$query = array(
			'_id' => $event->getId()->getMongoId(),
		);
		$update = array(
			'$set' => array(
				'notify_time' => new MongoDate(),
				'returned_value' => $response,
			),
		);
		
		return self::$collection->update($query, $update);
	}
}

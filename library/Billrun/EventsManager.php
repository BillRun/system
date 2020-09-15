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
	const CONDITION_REACHED_PERCENTAGE = 'reached_percentage';
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
	protected static $allowedExtraParams = array('aid' => 'aid', 'sid' => 'sid', 'stamp' => 'line_stamp', 'row' => 'row');
	protected $notifyHash;

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
	
	public function getEventsSettings($type, $activeOnly = true) {
		$events = Billrun_Util::getIn($this->eventsSettings, $type, []);
		if (!$activeOnly) {
			return $events;
		}
		return array_filter($events, function ($event) {
			return Billrun_Util::getIn($event, 'active', true);
		});
	}

	public function trigger($eventType, $entityBefore, $entityAfter, $additionalEntities = array(), $extraParams = array()) {
		$eventSettings = $this->getEventsSettings($eventType);
		if (empty($eventSettings)) {
			return;
		}
		
		foreach ($eventSettings as $event) {
			$conditionSettings = [];
			foreach ($event['conditions'] as $rawsEventSettings) {
				$conditionSettings = [];
				$additionalEventData = array(
					'unit' => $rawsEventSettings['unit'] ?? '',
					'usaget' => $rawsEventSettings['usaget'] ?? '',
					'property_type' => $rawsEventSettings['property_type'] ?? '',
					'type' => $rawsEventSettings['type'] ?? '',
					'value' => $rawsEventSettings['value'] ?? '',
				);
				$pathsMatched = [];
				
				if (!isset($rawsEventSettings['paths'])) { // BC
					$path = isset($rawsEventSettings['path']) ? $rawsEventSettings['path'] : '';
					$rawsEventSettings['paths'] = [
						['path' => $path],
					];
					unset($rawsEventSettings['path']);
				}
				
				foreach($rawsEventSettings['paths'] as $rawEventSettings) {
					$rawEventSettings = array_merge($rawEventSettings, $additionalEventData);
					if (isset($rawEventSettings['entity_type']) && $rawEventSettings['entity_type'] !== $eventType) {
						$conditionEntityAfter = $conditionEntityBefore = $additionalEntities[$rawEventSettings['entity_type']];
					} else {
						$conditionEntityAfter = $entityAfter;
						$conditionEntityBefore = $entityBefore;
					}
					$extraValues = $this->getValuesPerCondition($rawEventSettings['type'], $rawEventSettings, $conditionEntityBefore, $conditionEntityAfter);
					if ($extraValues !== false) {
						$path_data = ['event_settings' => $rawEventSettings, 'extra_values' => $extraValues];
						$path_stamp = Billrun_Util::generateArrayStamp($path_data);
						$pathsMatched[$path_stamp] = $path_data;
					}
				}
				
				if (empty($pathsMatched)) { // all paths failed to match
					continue 2;
				}
				$conditionSettings = array_merge($conditionSettings, $pathsMatched);
			}
			foreach ($conditionSettings as $stamp => $path_info) {
				$this->saveEvent($eventType, $event, $entityBefore, $entityAfter, $path_info['event_settings'], $extraParams, $path_info['extra_values']);
			}
		}

	}

	protected function getValuesPerCondition($condition, $rawEventSettings, $entityBefore, $entityAfter) {
		switch ($condition) {
			case self::CONDITION_IS:
				if (!$this->arrayMatches($this->getWhichEntity($rawEventSettings, $entityBefore, $entityAfter), $rawEventSettings['path'], '$eq', $rawEventSettings['value'])) {
					return false;
				}
				return array();
			case self::CONDITION_IN:
				if (!$this->arrayMatches($this->getWhichEntity($rawEventSettings, $entityBefore, $entityAfter), $rawEventSettings['path'], '$in', $rawEventSettings['value'])) {
					return false;
				}
				return array();
			case self::CONDITION_IS_NOT:
				if ($this->arrayMatches($this->getWhichEntity($rawEventSettings, $entityBefore, $entityAfter), $rawEventSettings['path'], '$eq', $rawEventSettings['value'])) {
					return false;
				}
				return array();
			case self::CONDITION_IS_LESS_THAN:
				if (!$this->arrayMatches($this->getWhichEntity($rawEventSettings, $entityBefore, $entityAfter), $rawEventSettings['path'], '$lt', $rawEventSettings['value'])) {
					return false;
				}
				return array();
			case self::CONDITION_IS_LESS_THAN_OR_EQUAL:
				if (!$this->arrayMatches($this->getWhichEntity($rawEventSettings, $entityBefore, $entityAfter), $rawEventSettings['path'], '$lte', $rawEventSettings['value'])) {
					return false;
				}
				return array();
			case self::CONDITION_IS_GREATER_THAN:
				if (!$this->arrayMatches($this->getWhichEntity($rawEventSettings, $entityBefore, $entityAfter), $rawEventSettings['path'], '$gt', $rawEventSettings['value'])) {
					return false;
				}
				return array();
			case self::CONDITION_IS_GREATER_THAN_OR_EQUAL:
				if (!$this->arrayMatches($this->getWhichEntity($rawEventSettings, $entityBefore, $entityAfter), $rawEventSettings['path'], '$gte', $rawEventSettings['value'])) {
					return false;
				}
				return array();
			case self::CONDITION_HAS_CHANGED:
				if (!(Billrun_Util::getIn($entityBefore, $rawEventSettings['path'], NULL) != Billrun_Util::getIn($entityAfter, $rawEventSettings['path'], NULL))) {
					return false;
				}
				return array();
			case self::CONDITION_HAS_CHANGED_TO:
				if (!((Billrun_Util::getIn($entityBefore, $rawEventSettings['path'], NULL) != Billrun_Util::getIn($entityAfter, $rawEventSettings['path'], NULL)) && $this->arrayMatches($entityAfter, $rawEventSettings['path'], '$eq', $rawEventSettings['value']))) {
					return false;
				}
				return array();
			case self::CONDITION_HAS_CHANGED_FROM:
				if (!((Billrun_Util::getIn($entityBefore, $rawEventSettings['path'], NULL) != Billrun_Util::getIn($entityAfter, $rawEventSettings['path'], NULL)) && $this->arrayMatches($entityBefore, $rawEventSettings['path'], '$eq', $rawEventSettings['value']))) {
					return false;
				}
				return array();
			case self::CONDITION_REACHED_CONSTANT:
				$valueBefore = Billrun_Util::getIn($entityBefore, $rawEventSettings['path'], 0);
				$valueAfter = Billrun_Util::getIn($entityAfter, $rawEventSettings['path'], 0);
				$eventValue = $rawEventSettings['value'];
				if (preg_match('/\d+,\d+/', $eventValue)) {
					$eventValues = explode(',', $eventValue);
				} else {
					$eventValues = array($eventValue);
				}
				if ($valueBefore < $valueAfter) {
					rsort($eventValues);
				} else {
					sort($eventValues);
				}			
				foreach ($eventValues as $eventVal) {
					if (($valueBefore < $eventVal && $eventVal <= $valueAfter) || ($valueBefore > $eventVal && $valueAfter <= $eventVal)) {
						$extraValues['reached_constant'] = $eventVal;

						return $extraValues;
					}
				}
				
				return false;
			case self::CONDITION_REACHED_CONSTANT_RECURRING:
				$rawValueBefore = Billrun_Util::getIn($entityBefore, $rawEventSettings['path'], 0);
				$rawValueAfter = Billrun_Util::getIn($entityAfter, $rawEventSettings['path'], 0);
				$eventValue = $rawEventSettings['value'];
				$valueBefore = floor($rawValueBefore / $eventValue);
				$valueAfter = floor($rawValueAfter / $eventValue);
				if (intval($valueBefore) == intval($valueAfter)) {
					return false;
				}
				$thresholdIncreasing = $rawValueAfter - ($rawValueAfter % $eventValue);
				$extraValues['reached_constant'] = ($rawValueBefore < $rawValueAfter) ? $thresholdIncreasing : $thresholdIncreasing + $eventValue;
				
				return $extraValues;
			case self::CONDITION_REACHED_PERCENTAGE:
				$valueBefore = Billrun_Util::getIn($entityBefore, $rawEventSettings['path'], 0);
				$valueAfter = Billrun_Util::getIn($entityAfter, $rawEventSettings['path'], 0);
				$eventTotalValue = Billrun_Util::getIn($entityAfter, $rawEventSettings['total_path'], 0); // we need to use after in case before is empty (new balance)
				$relatedEntities = $rawEventSettings['related_entities'] ?: [];
				$eventPercentageValues = explode(',', $rawEventSettings['value']);
				$eventValues = [];
				foreach ($eventPercentageValues as $percentageValue) {
					$eventValues[] = $percentageValue * $eventTotalValue / 100;
				}

				if ($valueBefore < $valueAfter) {
					rsort($eventValues);
					rsort($eventPercentageValues);
				} else {
					sort($eventValues);
					sort($eventPercentageValues);
				}			
				foreach ($eventValues as $key => $eventVal) {
					if (($valueBefore < $eventVal && $eventVal <= $valueAfter) || ($valueBefore > $eventVal && $valueAfter <= $eventVal)) {
						$extraValues['reached_constant'] = $eventVal;
						$extraValues['reached_constant_percentage'] = $eventPercentageValues[$key];
						$extraValues['related_entities'] = $relatedEntities;

						return $extraValues;
					}
				}
				return false;
			default:
				return FALSE;
		}
	}
	
	protected function getWhichEntity($rawEventSettings, $entityBefore, $entityAfter) {
		return (isset($rawEventSettings['which']) && ($rawEventSettings['which'] == self::ENTITY_BEFORE) ? $entityBefore : $entityAfter);
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

	public function saveEvent($eventType, $rawEventSettings, $entityBefore, $entityAfter, $conditionSettings, $extraParams = array(), $extraValues = array()) {
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

		if ($eventType == 'balance') {
			$event['before'] = $this->getEntityValueByPath($entityBefore, $conditionSettings['path']);
			$event['after'] =  $this->getEntityValueByPath($entityAfter, $conditionSettings['path']);
			$event['based_on'] = $this->getEventBasedOn($conditionSettings['path']);
			if ($this->isConditionOnGroup($conditionSettings['path'])) {
				$pathArray = explode('.', $conditionSettings['path']);
				array_pop($pathArray);
				$path = implode('.', $pathArray) . '.total';
				$event['group_total'] = $this->getEntityValueByPath($entityAfter, $path);
			}
		}
		foreach ($extraValues as $key => $value) {
			$event[$key] = $value;
		}
		$event['stamp'] = Billrun_Util::generateArrayStamp($event);
		Billrun_Factory::dispatcher()->trigger('beforeEventSave', array(&$event, $entityBefore, $entityAfter, $this));
		self::$collection->insert($event);
	}
	
	/**
	 * used for Cron to handle the events exists in the system
	 */
	public function notify() {
		$this->lockNotifyEvent();
		$events = $this->getEvents();
		$emailNotificationEvents = [];
		foreach ($events as $event) {
			try {
				$eventRaw = $event->getRawData();
				$emailNotificationEvents[] = $eventRaw;
				$response = Billrun_Events_Notifier::notify($eventRaw);
				if ($response === false) {
					Billrun_Factory::log('Error notify event. Event details: ' . print_R($event->getRawData(), 1), Billrun_Log::NOTICE);
					$this->unlockNotifyEvent($event);
					continue;
				}
				$this->addEventResponse($event, $response);
			} catch (Exception $e) {
				$this->unlockNotifyEvent($event);
			}
		}
		$this->handleEmailNotification($emailNotificationEvents);
	}

	/**
	 * get all events that were found in the system and was not already handled
	 * 
	 * @return type
	 */
	protected function getEvents() {
		$query = array(
			'notify_time' => array('$exists' => false),
			'hash' => $this->notifyHash,
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
	
	/**
	 * lock event before sending it.
	 * 
	 * @param array $event
	 */
	protected function lockNotifyEvent() {
		$this->notifyHash = md5(time() . rand(0, PHP_INT_MAX));
		$notifyOrphanTime = Billrun_Factory::config()->getConfigValue('events.settings.notify.notify_orphan_time', '1 hour');
		$query = array(
			'notify_time' => array('$exists' => false),
			'$or' => array(
				array('start_notify_time' => array('$exists' => false)),
				array('start_notify_time' => array('$lte' => new MongoDate(strtotime('-' . $notifyOrphanTime))))
			)
		);
		self::$collection->update($query, array('$set' => array('hash' => $this->notifyHash, 'start_notify_time' => new MongoDate())), array('multiple' => true));
	}
	
	
	/**
	 * unlock event in case of failue.
	 * 
	 * @param array $event
	 */
	protected function unlockNotifyEvent($event) {
		$query = array(
			'_id' => $event->getId()->getMongoId(),
		);
		$update = array(
			'$unset' => array(
				'hash' => true,
			),
		);
		
		self::$collection->update($query, $update);
	}
	
	/**
	 * get the value in entity by the defined path.
	 * 
	 * @param array $entity
	 * @param string $path
	 * 
	 */
	protected function getEntityValueByPath($entity, $path) {
		$pathArray = explode('.', $path);
		foreach($pathArray as $value) {
			$entity = isset($entity[$value]) ? $entity[$value] : 0;
			if (!$entity) {
				return 0;
			}
		}
		return $entity;
	}
	
		
	/**
	 * is the event usage / monetary based.
	 * 
	 * @param string $path
	 * 
	 */
	protected function getEventBasedOn($path) {
		return (substr_count($path, 'cost') == 0) ? 'usage' : 'monetary';
	}
	
			
	/**
	 * retuns true for conditions on groups.
	 * 
	 * @param string $path
	 * 
	 */
	protected function isConditionOnGroup($path) {
		return (substr_count($path, 'balance.groups') > 0);
	}


	protected function shouldSendEmailNotification($event) {
		return Billrun_Util::getIn($event, 'notify_by_email.notify', false);
	}
	
	protected function getEventDescription($event) {
		$thresholdsDescription = [];
		foreach ($event['thresholds'] as $thresholds) {
			foreach ($thresholds as $threshold) {
				$thresholdsDescription[] = "{$threshold['field']}: {$threshold['value']}";
			}
		}
		return implode(', ', $thresholdsDescription);
	}
	
	protected function getEventRecipients($event) {
		$sendToGlobalAddresses = Billrun_Util::getIn($event, 'notify_by_email.use_global_addresses', true);
		$globalAddresses = $sendToGlobalAddresses
			? Billrun_Factory::config()->getConfigValue('events.settings.email.global_addresses', [])
			: [];
		$specificEventAddresses = Billrun_Util::getIn($event, 'notify_by_email.additional_addresses', []);
		
		return array_unique(array_merge($globalAddresses, $specificEventAddresses));
	}
	
	protected function sendEmailNotification($emailNotifications) {
		foreach ($emailNotifications as $eventType => $eventTypeEmailNotification) {
			$emailTemplateName = "{$eventType}_notification";
			$emailTemplateConfig = Billrun_Factory::config()->getConfigValue('email_templates.' . $emailTemplateName, []);
			$subject = Billrun_Util::getIn($emailTemplateConfig, 'subject', '');
			$body = Billrun_Util::getIn($emailTemplateConfig, 'content', '');
			foreach ($eventTypeEmailNotification as $eventCode => $eventCodeEmailNotification) {
				$fraudEventDetails = [];
				foreach ($eventCodeEmailNotification['aids'] as $aid => $sids) {
					$sids = implode(', ', $sids);
					$fraudEventDetails[] = "Account id: {$aid}, Subscriber ids: {$sids}, {$eventCodeEmailNotification['desc']}";
				}
				$subjectTranslations = [
					'event_code' => $eventCode,	
				];
				$bodyTranslations = [
					'fraud_event_details' => implode(PHP_EOL, $fraudEventDetails),
				];
				$subject = Billrun_Util::translateTemplateValue($subject, $subjectTranslations);
				$body = Billrun_Util::translateTemplateValue($body, $bodyTranslations);
				$recipients = Billrun_Util::getIn($eventCodeEmailNotification, 'recipients');
				Billrun_Util::sendMail($subject, $body, $recipients);
			}
		}
	}

	/**
	 * send email on notifications sent
	 * currently, for fraud events that has "Notify also by email" flag on
	 * 
	 * @param array $events
	 */
	protected function handleEmailNotification($events) {
		$emailNotifications = [];
		foreach ($events as $event) {
			if ($this->shouldSendEmailNotification($event)) {
				$eventType = $event['event_type'];
				$eventCode = $event['event_code'];
				$aid = $event['extra_params']['aid'];
				$sid = $event['extra_params']['sid'];
				
				$eventToNotify = Billrun_Util::getIn($emailNotifications, [$eventType, $eventCode], []);
				if (empty($eventToNotify)) {
					$eventToNotify = [
						'desc' => $this->getEventDescription($event),
						'recipients' => $this->getEventRecipients($event),
						'aids' => [],
					];
					Billrun_Util::setIn($emailNotifications, [$eventType, $eventCode], $eventToNotify);
				}
				
				$sids = Billrun_Util::getIn($eventToNotify, ['aids', $aid], []);
				$sids[] = $sid;
				Billrun_Util::setIn($emailNotifications, [$eventType, $eventCode, 'aids', $aid], $sids);
			}
		}
		if (!empty($emailNotifications)) {
			$this->sendEmailNotification($emailNotifications);
		}
	}
	
}

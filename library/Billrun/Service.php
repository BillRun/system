<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing service class
 * Service extend the basic subscriber usage for additional usage counters (beside Plan group)
 *
 * @package  Service
 * @since    5.2
 */
class Billrun_Service {
	
	const UNLIMITED_VALUE = 'UNLIMITED';

	/**
	 * container of the entity data
	 * 
	 * @var mixed
	 */
	protected $data = null;
	protected $groupSelected = null;
	protected $groups = null;
	protected $strongestGroup = null;
	protected static $cache = array();
	protected static $cacheType = 'services';

	/**
	 * number of cycles the service apply to
	 * @var mixed int or UNLIMITED_VALUE constant
	 */
	protected $cyclesCount;
	
	/**
	 * local cache to store all entities (services/plans), so on run-time they will be fetched from memory instead of from DB
	 * 
	 * @var array
	 */
	protected static $entities = [];

	/**
	 * constructor
	 * set the data instance
	 * 
	 * @param array $params array of parameters (service name & time)
	 */
	public function __construct(array $params = array()) {
		if (isset($params['time'])) {
			$time = $params['time'];
		} else {
			$time = time();
		}
		if (isset($params['data'])) {
			$this->data = $params['data'];
		} else if (isset($params['id'])) {
			$this->load(new MongoId($params['id']));
		} else if (isset($params['name'])) {
			$this->load($params['name'], $time, 'name');
		}
		
		if (isset($params['service_start_date'])) {
			$this->data['service_start_date'] = $params['service_start_date'];
		}
		
		$this->data['plan_included'] = isset($params['plan_included']) ? $params['plan_included'] : false;
	}
	
	/**
	 * initialize internal variables
	 */
	public function init() {
		$this->groups = null;
		$this->groupSelected = null;
		$this->strongestGroup = null;
	}

	/**
	 * load the service from DB
	 * 
	 * @param mixed $param the value to load by
	 * @param int $time unix timestamp
	 * @param string $loadByField the field to load by the value
	 */
	protected function load($param, $time = null, $loadByField = '_id') {
		if (is_null($time)) {
			$queryTime = new MongoDate();
		} else {
			$queryTime = new MongoDate($time);
		}
		
		switch ($loadByField) {
			case 'name':
				$this->data = self::getEntityByNameAndTime($param, $queryTime);
				break;
			case 'id':
			case '_id':
				$this->data = self::getEntityById($param);
				break;
			default: // BC
				$this->loadFromDb($param, $queryTime, $loadByField);
		}
	}

	/**
	 * load the service from DB
	 * 
	 * @param mixed $param the value to load by
	 * @param mixed $time unix timestamp OR mongo date
	 * @param string $loadByField the field to load by the value
	 */
	protected function loadFromDb($param, $time = null, $loadByField = '_id') {
		if (is_null($time)) {
			$queryTime = new MongoDate();
		} else if (!$time instanceof MongoDate) {
			$queryTime = new MongoDate($time);
		}
		$serviceQuery = array(
			$loadByField => $param,
			'$or' => array(
				array('to' => array('$gt' => $queryTime)),
				array('to' => null)
			)
		);
		$coll = self::getCollection();
		$record = $coll->query($serviceQuery)->lessEq('from', $queryTime)->cursor()->current();
		$record->collection($coll);
		$this->data = $record;
	}

	/**
	 * method to pull current plan data
	 * 
	 * @param string $name the property name; could be mongo key
	 * 
	 * @return mixed the property value
	 */
	public function get($name) {
		return $this->data->get($name);
	}

	public function getName() {
		return $this->get('name');
	}
	
	public function getData($raw = false) {
		if ($raw) {
			return $this->data->getRawData();
		}
		return $this->data;
	}
	
	public static function getByNameAndTime($name, $time) {
		$items = self::getCacheItems();
		if (isset($items['by_name'][$name])) {
			foreach ($items['by_name'][$name] as $itemTimes) {
				if ($itemTimes['from'] <= $time && (!isset($itemTimes['to']) || is_null($itemTimes['to']) || $itemTimes['to'] >= $time)) {
					return $itemTimes['plan'];
				}
			}
		}
		return false;
	}
	
	public static function getCacheItems() {
		if (empty(static::$cache)) {
			self::initCacheItems();
		}
		return static::$cache;
	}
	
	public static function initCacheItems() {
		$coll = Billrun_Factory::db()->{static::$cacheType . 'Collection'}();
		$items = $coll->query()->cursor();
		foreach ($items as $item) {
			$item->collection($coll);
			static::$cache['by_id'][strval($item->getId())] = $item;
			static::$cache['by_name'][$item['name']][] = array(
				'plan' => $item,
				'from' => $item['from'],
				'to' => $item['to'],
			);
		}
		return static::$cache;
	}

	/**
	 * Validates that the service still have cycles left (not exhausted yet)
	 * If this is custom period service it will check if the duration is still aligned to the row time
	 * 
	 * @param $serviceStartDate the date from which the service is valid for the subscriber
	 * 
	 * @return boolean true if exhausted, else false
	 */
	public function isExhausted($serviceStartDate, $rowTime = null) {
		if ($serviceStartDate instanceof MongoDate) {
			$serviceStartDate = $serviceStartDate->sec;
		}
		
		if (is_null($rowTime)) {
			$rowTime = time();
		}
		
		if (($customPeriod = $this->get("balance_period")) && $customPeriod !== "default") {
			$serviceEndDate = strtotime($customPeriod, $serviceStartDate);
			return $rowTime < $serviceStartDate || $rowTime > $serviceEndDate;
		}

		if (!isset($this->data['price']) || !is_array($this->data['price'])) {
			return false;
		}
		$lastEntry = array_slice($this->data['price'], -1)[0];
		$serviceAvailableCycles = Billrun_Util::getIn($lastEntry, 'to', 0);
		if ($serviceAvailableCycles === Billrun_Service::UNLIMITED_VALUE) {
			return false;
		}
		$serviceCycleStartDate = Billrun_Billingcycle::getBillrunStartTimeByDate(date(Billrun_Base::base_datetimeformat,$serviceStartDate));
		$cyclesSpent = Billrun_Utils_Autorenew::countMonths($serviceCycleStartDate, $rowTime);
		return $cyclesSpent > $serviceAvailableCycles;
	}
	
	/**
	 * method to receive all group rates of the current plan
	 * @param array $rate the rate to check
	 * @param string $usageType usage type to check
	 * @return false when no group rates, else array list of the groups
	 * @since 2.6
	 */
	public function getRateGroups($rate, $usageType) {
		$groups = array();
		if (isset($this->data['include']['groups']) && is_array($this->data['include']['groups'])) {
			foreach ($this->data['include']['groups'] as $groupName => $groupIncludes) {
				if ((array_key_exists($usageType, $groupIncludes) || array_key_exists('cost', $groupIncludes) || isset($groupIncludes['usage_types'][$usageType])) && !empty($groupIncludes['rates']) && in_array($rate['key'], $groupIncludes['rates'])) {
					$groups[] = $groupName;
				}
			}
		}
		if ($groups) {
			return $groups;
		}
		
		//backward compatibility
		if (isset($rate['rates'][$usageType]['groups'])) {
			$groups = $rate['rates'][$usageType]['groups'];
		} else {
			return array();
		}
		if (!empty($groups) && isset($this->data['include']['groups'])) {
			return array_intersect($groups, array_keys($this->data['include']['groups']));
		}
		return array();
	}

	public function setEntityGroup($group) {
		$this->groupSelected = $group;
	}

	public function getEntityGroup() {
		return $this->groupSelected;
	}

	public function unsetGroup($group) {
		$item = array_search($group, $this->groups);
		if (isset($this->groups[$item])) {
			unset($this->groups[$item]);
		}
	}

	/**
	 * method to check if rate is part of group of rates balance
	 * there is option to create balance for group of rates
	 * 
	 * @param array $rate the rate to check
	 * @param string $usageType the usage type to check
	 * @return true when the rate is part of group else false
	 */
	public function isRateInEntityGroup($rate, $usageType) {
		if (count($this->getRateGroups($rate, $usageType))) {
			return true;
		}
		return false;
	}

	/**
	 * method to receive the strongest group of list of groups 
	 * method will init the groups list if not loaded yet
	 * by default, the strongest rule is simple the first rule selected (in the plan)
	 * rules can be complex with plugins (see vodafone and ird plugins for example)
	 * 
	 * @param array $rate the rate to check
	 * @param string $usageType the usage type to check
	 * @param boolean $reset reset to the first group plan
	 * 
	 * @return false when no group found, else string name of the group selected
	 */
	protected function setNextStrongestGroup($rate, $usageType, $reset = FALSE) {
		if (is_null($this->groups)) {
			$this->groups = $this->getRateGroups($rate, $usageType);
		}
		if (!count($this->groups)) {
			$this->setEntityGroup(FALSE);
		} else if ($reset || is_null($this->getEntityGroup())) { // if reset required or it's the first set
			$this->setEntityGroup(reset($this->groups));
		} else if (next($this->groups) !== FALSE) {
			$this->setEntityGroup(current($this->groups));
		} else {
			$this->setEntityGroup(FALSE);
		}

		return $this->getEntityGroup();
	}

	/**
	 * method to receive the usage left in group of rates
	 * 
	 * @param array $subscriberBalance subscriber balance
	 * @param array $rate the rate to check the balance
	 * @param string $usageType the usage type
	 * @param string $staticGroup check specifically on group without calculate the strongest group of the plan
	 * 
	 * @return int usage left in the group
	 */
	public function usageLeftInEntityGroup($subscriberBalance, $rate, $usageType, $staticGroup = null, $time = null, $serviceQuantity = 1) {
		if (is_null($staticGroup)) {
			$rateUsageIncluded = 0; // pass by reference
			$groupSelected = $this->getStrongestGroup($rate, $usageType);
		} else { // specific group required to check
			if (!isset($this->data['include']['groups'][$staticGroup][$usageType]) 
				&& !isset($this->data['include']['groups'][$staticGroup]['usage_types'][$usageType]) 
				&& !isset($this->data['include']['groups'][$staticGroup]['cost'])) {
				return array('usagev' => 0);
			}

			if (isset($this->data['include']['groups'][$staticGroup]['limits'])) {
				// on some cases we have limits to check through plugin
				$limits = $this->data['include']['groups'][$staticGroup]['limits'];
				Billrun_Factory::dispatcher()->trigger('planGroupRule', array(&$staticGroup, $limits, $this, $usageType, $rate, $subscriberBalance));
				if ($groupSelected === FALSE) {
					return array('usagev' => 0);
				}
			}
			
			$groupSelected = $staticGroup;
		}
		
		if (!isset($this->data['include']['groups'][$groupSelected][$usageType]) 
			&& !isset($this->data['include']['groups'][$groupSelected]['usage_types'][$usageType])) {
			if (!isset($this->data['include']['groups'][$groupSelected]['cost'])) {
				return array('usagev' => 0);
			}
			
			$cost = $this->getGroupVolume('cost', $subscriberBalance['aid'], $groupSelected, $time, $serviceQuantity);
			// convert cost to volume
			if ($cost === Billrun_Service::UNLIMITED_VALUE) {
				return array(
					'cost' => PHP_INT_MAX,
				);
			}

			if (isset($subscriberBalance['balance']['groups'][$groupSelected]['cost'])) {
				$subscriberSpent = $subscriberBalance['balance']['groups'][$groupSelected]['cost'];
			} else {
				$subscriberSpent = 0;
			}
			$costLeft = $cost - $subscriberSpent;
			return array(
				'cost' => floatval($costLeft < 0 ? 0 : $costLeft),
			);
		} else {
			$rateUsageIncluded = $this->getGroupVolume($usageType, $subscriberBalance['aid'], $groupSelected, $time, $serviceQuantity);
			if ($rateUsageIncluded === 'UNLIMITED') {
				return array(
					'usagev' => PHP_INT_MAX,
				);
			}

			if (isset($subscriberBalance['balance']['groups'][$groupSelected]['usagev'])) {
				$subscriberSpent = $subscriberBalance['balance']['groups'][$groupSelected]['usagev'];
			} else {
				$subscriberSpent = 0;
			}
			$usageLeft = $rateUsageIncluded - $subscriberSpent;
			return array(
				'usagev' => floatval($usageLeft < 0 ? 0 : $usageLeft),
			);
		}
	}

	/**
	 * method to calculate the strongest group of the service
	 * 
	 * @param array $rate the rate to check the balance
	 * @param string $usageType the usage type
	 * @param int $rateUsageIncluded the usage included in the group (reference)
	 * 
	 * @return mixed string if found strongest group else false
	 */
	protected function getStrongestGroup($rate, $usageType) {
		if (!is_null($this->strongestGroup)) {
			return $this->strongestGroup;
		}
		$limit = 10; // protect infinite loop
		do {
			$groupSelected = $this->setNextStrongestGroup($rate, $usageType);
			// group not found
			if ($groupSelected === FALSE) {
//				$this->setEntityGroup($this->setNextStrongestGroup($rate, $usageType, true)); // removed, because it's run only one time per row
				break; // do-while
			}
			// not group included in the specific usage try to take iterate next group
			if ((!isset($this->data['include']['groups'][$groupSelected][$usageType]) || !isset($this->data['include']['groups'][$groupSelected]['usage_types'][$usageType]))
				&& !isset($this->data['include']['groups'][$groupSelected]['cost'])) {
				continue;
			}
			if (isset($this->data['include']['groups'][$groupSelected]['limits'])) {
				// on some cases we have limits to check through plugin
				$limits = $this->data['include']['groups'][$groupSelected]['limits'];
				Billrun_Factory::dispatcher()->trigger('planGroupRule', array(&$groupSelected, $limits, $this, $usageType, $rate));
				if ($groupSelected === FALSE) {
					$this->unsetGroup($this->getEntityGroup());
				}
			}
		}
		while ($groupSelected === FALSE && $limit--);
		$this->strongestGroup = $groupSelected;
		return $this->strongestGroup;
	}

	/**
	 * method to check if group is shared or not
	 * 
	 * @param array $rate the rate to intersect
	 * @param string $usageType the usage type to check
	 * @param string $group check specific group, if null check the strongest group available
	 * 
	 * @return boolean true if group is account shared else false
	 * @since 5.3
	 */
	public function isGroupAccountShared($rate, $usageType, $group = null) {
		if (is_null($group)) {
			$rateUsageIncluded = 0;
			$group = $this->getStrongestGroup($rate, $usageType, $rateUsageIncluded);
		}
		return isset($this->data['include']['groups'][$group]['account_shared']) && $this->data['include']['groups'][$group]['account_shared'];
	}

	/**
	 * method to check if group is pool or not
	 * 
	 * @param array $rate the rate to intersect
	 * @param string $usageType the usage type to check
	 * @param string $group check specific group, if null check the strongest group available
	 * 
	 * @return boolean true if group is account shared else false
	 * @since 5.3
	 */
	public function isGroupAccountPool($group = null) {
		if (is_null($group)) {
			$group = $this->getEntityGroup();
		}
		return isset($this->data['include']['groups'][$group]['account_pool']) && $this->data['include']['groups'][$group]['account_pool'];
	}

	public function getGroupVolume($usageType, $aid, $group = null, $time = null, $serviceQuantity = 1) {
		if (is_null($group)) {
			$group = $this->getEntityGroup();
		}
		$isShared = isset($this->data['include']['groups'][$group]['account_shared']) ? $this->data['include']['groups'][$group]['account_shared'] : false;
		$isquantityAffected = isset($this->data['include']['groups'][$group]['quantity_affected']) ? $this->data['include']['groups'][$group]['quantity_affected'] : false;
		$groupValue = $this->getGroupValue($group, $usageType);
		if ($groupValue === FALSE) {
			return 0;
		}
		if (!$isShared && $isquantityAffected) {
			return $groupValue * $serviceQuantity;
		}
		if ($this->isGroupAccountPool($group) && $pool = $this->getPoolSharingUsageCount($aid, $time, $isquantityAffected)) {
			return $groupValue * $pool;
		}
		return $groupValue;
	}
	
	/**
	 * method to get group includes value
	 * 
	 * @param string $group the group name
	 * @param string $usaget the usage type related
	 * 
	 * @return mixed double if found, else false
	 * 
	 * @since 5.7
	 */
	protected function getGroupValue($group, $usaget) {
		if (!isset($this->data['include']['groups'][$group][$usaget]) && !isset($this->data['include']['groups'][$group]['usage_types'][$usaget])) {
			return false;
		}
		if (!isset($this->data['include']['groups'][$group]['value'])) {
			return $this->data['include']['groups'][$group][$usaget];
		}
		$value = $this->data['include']['groups'][$group]['value'];
		return $value == Billrun_Service::UNLIMITED_VALUE ? PHP_INT_MAX: $value;
	}
	
	/**
	 * method to calculate how much usage there is in pool sharing usage/cost
	 * @param int $aid the account
	 * @param string $group the group
	 * @param boolean $quantityAffected flag whether to multiply by subscriber service quantity 
	 * @return int
	 */
	protected function getPoolSharingUsageCount($aid, $time = null, $quantityAffected = false) {
		if (is_null($time)) {
			$time = time();
		}
		$query = array(
			'aid' => $aid,
			'type' => 'subscriber',
			'to' => array('$gt' => new MongoDate($time)),
			'from' => array('$lt' => new MongoDate($time)),
		);
		$isPlan = $this instanceof Billrun_Plan;
		$isService = $this instanceof Billrun_Service;
		if ($isPlan) {
			$query['plan'] = $this->data['name'];
		} else if ($isService) {
			$query['services.name'] = $this->data['name'];
		} else {
			return 0;
		}
		
		$aggregateMatch = array(
			'$match' => $query,
		);
		
		$unwindServices = array('$unwind' => '$services');
		
		$aggregateServices = array(
			'$match' => array(
				'services.name' => $this->data['name']
			)
		);
		
		if ($isPlan || ($isService && !$quantityAffected)) {
			$aggregateGroup = array(
				'$group' => array(
					'_id' => null,
					's' => array(
						'$sum' => 1
						)
					)
				);				
		} else if ($isService && $quantityAffected) {
			$aggregateGroup = array(
				'$group' => array(
					'_id' => null,
					's' => array(
						'$sum' => array(
							'$ifNull' => array(
								'$services.quantity', 1
							)
						)
					)
				)
			);
		}
		
		$aggreagateArray = array($aggregateMatch);
		
		if ($isPlan) {
			array_push($aggreagateArray, $aggregateGroup);
		} else if ($this instanceof Billrun_Service) {
			array_push($aggreagateArray, $unwindServices, $aggregateServices, $aggregateGroup);
		} 
				
		$results = Billrun_Factory::db()->subscribersCollection()->aggregate($aggreagateArray)->current();
		if (!isset($results['s'])) {
			return 0;
		}
		return $results['s'];
	}
	
	public function getPlays() {
		$plays = $this->get('play');
		return empty($plays) ? [] : $plays;
	}
	
	/**
	 * gets the DB collection of the entity (servicesCollection/plansCollection/etc...)
	 * 
	 * @return Mongodloid Collection
	 */
	public static function getCollection() {
		return Billrun_Factory::db()->getCollection(str_replace("billrun_", "", strtolower(get_called_class())) . 's');
	}
	
	/**
	 * loads all entities (Services/Plans/etc...) to a static local variable
	 * these entities will be later use to fetch from the memory instead of from the DB
	 */
	public static function initEntities() {
		$coll = self::getCollection();
		$entities = $coll->query()->cursor();
		self::$entities['by_id'] = [];
		self::$entities['by_name'] = [];
		foreach ($entities as $entity) {
			$entity->collection($coll);
			self::$entities['by_id'][strval($entity->getId())] = $entity;
			self::$entities['by_name'][$entity['name']][] = [
				'entity' => $entity,
				'from' => $entity['from'],
				'to' => $entity['to'],
			];
		}
	}

	/**
	 * get local stored entities
	 * 
	 * @return array
	 */
	public static function getEntities() {
		if (empty(self::$entities)) {
			self::initEntities();
		}
		return self::$entities;
	}

	/**
	 * get the entity by its id
	 *
	 * @param string $id
	 *
	 * @return array of entity details if id exists else false
	 */
	protected static function getEntityById($id) {
		$entities = static::getEntities();
		if (isset($entities['by_id'][$id])) {
			return $entities['by_id'][$id];
		}
		return new Mongodloid_Entity(array(), self::getCollection());
	}

	/**
	 * get entuty by name and date
	 * entity is time-depend
	 * @param string $name name of the entity
	 * @param int $time unix timestamp
	 * @return array with entity details if entity exists, else false
	 */
	protected static function getEntityByNameAndTime($name, $time) {
		$entities = static::getEntities();
		if (isset($entities['by_name'][$name])) {
			foreach ($entities['by_name'][$name] as $entityTimes) {
				if ($entityTimes['from'] <= $time && (!isset($entityTimes['to']) || is_null($entityTimes['to']) || $entityTimes['to'] >= $time)) {
					return $entityTimes['entity'];
				}
			}
		}
		return new Mongodloid_Entity(array(), self::getCollection());
	}
	
	/**
	 * method to receive the number of cycles to charge
	 * @return mixed true is service is infinite (unlimited)
	 */
	public function getServiceCyclesCount() {
		if (is_null($this->cyclesCount)) {
			$lastEntry = array_slice($this->data['price'], -1)[0];
			$this->cyclesCount = Billrun_Util::getIn($lastEntry, 'to', 0);
		}
		return $this->cyclesCount;
	}

	/**
	 * method to check if server is unlimited of cycles to charge
	 * @return mixed true is service is infinite (unlimited)
	 */
	public function isServiceUnlimited() {
		$serviceAvailableCycles = $this->getServiceCyclesCount();
		if ($serviceAvailableCycles === Billrun_Service::UNLIMITED_VALUE) {
			return true;
		}
		return false;
	}

}

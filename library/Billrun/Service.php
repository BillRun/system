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
	/**
	 * service internal id
	 * 
	 * @var int
	 */
	protected $service_id = 0;

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
		
		if (isset($params['service_id'])) {
			$this->data['service_id'] = $params['service_id'];
		}
		if (isset($params['service_start_date'])) {
			$this->data['service_start_date'] = $params['service_start_date'];
		}
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
		$serviceQuery = array(
			$loadByField => $param,
			'$or' => array(
				array('to' => array('$gt' => $queryTime)),
				array('to' => null)
			)
		);
		$coll = Billrun_Factory::db()->getCollection(str_replace("billrun_", "", strtolower(get_class($this))) . 's');
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
		$cyclesSpent = Billrun_Utils_Autorenew::countMonths($serviceStartDate, $rowTime );
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
	public function usageLeftInEntityGroup($subscriberBalance, $rate, $usageType, $staticGroup = null, $time = null) {
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
			
			$cost = $this->getGroupVolume('cost', $subscriberBalance['aid'], $groupSelected, $time);
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
			$rateUsageIncluded = $this->getGroupVolume($usageType, $subscriberBalance['aid'], $groupSelected, $time);
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

	public function getGroupVolume($usageType, $aid, $group = null, $time = null) {
		if (is_null($group)) {
			$group = $this->getEntityGroup();
		}
		$groupValue = $this->getGroupValue($group, $usageType);
		if ($groupValue === FALSE) {
			return 0;
		}
		if ($this->isGroupAccountPool($group) && $pool = $this->getPoolSharingUsageCount($aid, $time)) {
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
	 * @return int
	 */
	protected function getPoolSharingUsageCount($aid, $time = null) {
		if (is_null($time)) {
			$time = time();
		}
		$query = array(
			'aid' => $aid,
			'type' => 'subscriber',
			'to' => array('$gt' => new MongoDate($time)),
			'from' => array('$lt' => new MongoDate($time)),
		);
		if ($this instanceof Billrun_Plan) {
			$query['plan'] = $this->data['name'];
		} else if ($this instanceof Billrun_Service) {
			$query['services.name'] = $this->data['name'];
		} else {
			return 0;
		}
		
		$aggregateMatch = array(
			'$match' => $query,
		);
		
		$aggregateGroup = array(
			'$group' => array(
				'_id' => null,
				's' => array(
					'$sum' => 1,
				)
			)
		);
		$results = Billrun_Factory::db()->subscribersCollection()->aggregate($aggregateMatch, $aggregateGroup)->current();
		if (!isset($results['s'])) {
			return 0;
		}
		return $results['s'];
	}
	
}

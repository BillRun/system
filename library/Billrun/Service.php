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

	/**
	 * method to receive all group rates of the current plan
	 * @param array $rate the rate to check
	 * @param string $usageType usage type to check
	 * @return false when no group rates, else array list of the groups
	 * @since 2.6
	 */
	public function getRateGroups($rate, $usageType) {
		if (isset($rate['rates'][$usageType]['groups'])) {
			$groups = $rate['rates'][$usageType]['groups'];
		} else if (($name = $this->getName()) && isset($rate['rates'][$usageType]['groups'][$name])) {
			$groups = $rate['rates'][$usageType]['groups'][$name];
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
	public function usageLeftInEntityGroup($subscriberBalance, $rate, $usageType, $staticGroup = null) {
		if (is_null($staticGroup)) {
			$rateUsageIncluded = 0; // pass by reference
			$groupSelected = $this->getStrongestGroup($rate, $usageType);
		} else { // specific group required to check
			if (!isset($this->data['include']['groups'][$staticGroup][$usageType])) {
				return 0;
			}

			if (isset($this->data['include']['groups'][$staticGroup]['limits'])) {
				// on some cases we have limits to check through plugin
				$limits = $this->data['include']['groups'][$staticGroup]['limits'];
				Billrun_Factory::dispatcher()->trigger('planGroupRule', array(&$staticGroup, $limits, $this, $usageType, $rate, $subscriberBalance));
				if ($groupSelected === FALSE) {
					return 0;
				}
			}
			
			$groupSelected = $staticGroup;
		}
		
		if (!isset($this->data['include']['groups'][$groupSelected][$usageType])) {
			return 0;
		}

		$rateUsageIncluded = $this->data['include']['groups'][$groupSelected][$usageType];

		if ($rateUsageIncluded === 'UNLIMITED') {
			return PHP_INT_MAX;
		}

		if (isset($subscriberBalance['balance']['groups'][$groupSelected][$usageType]['usagev'])) {
			$subscriberSpent = $subscriberBalance['balance']['groups'][$groupSelected][$usageType]['usagev'];
		} else {
			$subscriberSpent = 0;
		}
		$usageLeft = $rateUsageIncluded - $subscriberSpent;
		return floatval($usageLeft < 0 ? 0 : $usageLeft);
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
		$limit = 10; // protect infinit loop
		do {
			$groupSelected = $this->setNextStrongestGroup($rate, $usageType);
			// group not found
			if ($groupSelected === FALSE) {
//				$this->setEntityGroup($this->setNextStrongestGroup($rate, $usageType, true)); // removed, because it's run only one time per row
				break; // do-while
			}
			// not group included in the specific usage try to take iterate next group
			if (!isset($this->data['include']['groups'][$groupSelected][$usageType])) {
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

	public function getGroupVolume($usageType, $group = null) {
		if (is_null($group)) {
			$group = $this->getEntityGroup();
		}
		return $this->data['include']['groups'][$group][$usageType];
	}

}

<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing plan class
 *
 * @package  Plan
 * @since    0.5
 */
class Billrun_Plan {

	/**
	 * container of the plan data
	 * 
	 * @var mixed
	 */
	protected $data = null;
	protected static $plans = array();
	protected $plan_ref = array();
	protected $groupSelected = null;
	protected $groups = null;

	/**
	 * constructor
	 * set the data instance
	 * 
	 * @param array $params array of parmeters (plan name & time)
	 */
	public function __construct(array $params = array()) {
		if ((!isset($params['name']) || !isset($params['time'])) && (!isset($params['id'])) && (!isset($params['data']))) {
			//throw an error
			throw new Exception("plan constructor was called  without the appropriate parameters , got : " . print_r($params, 1));
		}
		if (isset($params['data'])) {
			$this->data = $params['data'];
		} else {
			self::initPlans();
			if (isset($params['id'])) {
				$id = $params['id'];
				if ($id instanceof Mongodloid_Id) {
					$filter_id = strval($id->getMongoId());
				} else if ($id instanceof MongoId) {
					$filter_id = strval($id);
				} else {
					// probably a string
					$filter_id = $id;
				}
				if ($plan = $this->getPlanById($filter_id)) {
					$this->data = $plan;
				} else {
					$this->data = Billrun_Factory::db()->plansCollection()->findOne($params['id']);
					$this->data->collection(Billrun_Factory::db()->plansCollection());
				}
			} else {
				$date = new MongoDate($params['time']);
				if ($plan = $this->getPlanByNameAndTime($params['name'], $date)) {
					$this->data = $plan;
				} else {
					$this->data = Billrun_Factory::db()->plansCollection()
							->query(array(
								'name' => $params['name'],
								'$or' => array(
									array('to' => array('$gt' => $date)),
									array('to' => null)
								)
							))
							->lessEq('from', $date)
							->cursor()
							->current();
					$this->data->collection(Billrun_Factory::db()->plansCollection());
				}
			}
		}
	}

	public function getData($raw = false) {
		if ($raw) {
			return $this->data->getRawData();
		}
		return $this->data;
	}

	protected static function initPlans() {
		if (empty(self::$plans)) {
			$plans_coll = Billrun_Factory::db()->plansCollection();
			$plans = $plans_coll->query()->cursor();
			foreach ($plans as $plan) {
				$plan->collection($plans_coll);
				self::$plans['by_id'][strval($plan->getId())] = $plan;
				self::$plans['by_name'][$plan['name']][] = array(
					'plan' => $plan,
					'from' => $plan['from'],
					'to' => $plan['to'],
				);
			}
		}
	}

	public static function getPlans() {
		self::initPlans();
		return self::$plans;
	}

	/**
	 * get the plan by its id
	 * 
	 * @param string $id
	 * 
	 * @return array of plan details if id exists else false
	 */
	protected function getPlanById($id) {
		if (isset(self::$plans['by_id'][$id])) {
			return self::$plans['by_id'][$id];
		}
		return false;
	}

	/**
	 * get plan by name and date
	 * plan is time-depend
	 * @param string $name name of the plan
	 * @param int $time unix timestamp
	 * @return array with plan details if plan exists, else false
	 */
	protected function getPlanByNameAndTime($name, $time) {
		if (isset(self::$plans['by_name'][$name])) {
			foreach (self::$plans['by_name'][$name] as $planTimes) {
				if ($planTimes['from'] <= $time && (!isset($planTimes['to']) || is_null($planTimes['to']) || $planTimes['to'] >= $time)) {
					return $planTimes['plan'];
				}
			}
		}
		return false;
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

	/**
	 * check if a usage type included as part of the plan
	 * @param type $rate
	 * @param type $type
	 * @return boolean
	 * @deprecated since version 0.1
	 * 		should be removed from here;
	 * 		the check of plan should be run on line not subscriber/balance
	 */
	public function isRateInBasePlan($rate, $type) {
		return isset($rate['rates'][$type]['plans']) &&
				is_array($rate['rates'][$type]['plans']) &&
				in_array($this->createRef(), $rate['rates'][$type]['plans']);
	}

	/**
	 * method to check if a usage type included in the rate plan
	 * rate plan means that there is rate that have balance that included as part of the plan
	 * it's described in the plan meta data
	 * 
	 * @param array $rate details of the rate
	 * @param string $usageType the usage type
	 * 
	 * @return boolean
	 * @since 2.6
	 * @deprecated since version 2.7
	 */
	public function isRateInPlanRate($rate, $usageType) {
		return (isset($this->data['include']['rates'][$rate['key']][$usageType]));
	}

	/**
	 * check if usage left in the rate balance (part of the plan)
	 * 
	 * @param array $subscriberBalance subscriber balance to check
	 * @param array $rate the rate to check
	 * @param string $usageType usage type to check
	 * @return int the usage left
	 * @since 2.6
	 * @deprecated since version 2.7
	 */
	public function usageLeftInRateBalance($subscriberBalance, $rate, $usageType = 'call') {
		if (!isset($this->get('include')[$rate['key']][$usageType])) {
			return 0;
		}

		$rateUsageIncluded = $this->get('include')[$rate['key']][$usageType];

		if ($rateUsageIncluded === 'UNLIMITED') {
			return PHP_INT_MAX;
		}

		if (isset($subscriberBalance['rates'][$rate['key']][$usageType]['usagev'])) {
			$subscriberSpent = $subscriberBalance['rates'][$rate['key']][$usageType]['usagev'];
		} else {
			$subscriberSpent = 0;
		}
		$usageLeft = $rateUsageIncluded - $subscriberSpent;
		return floatval($usageLeft < 0 ? 0 : $usageLeft);
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
			if (!empty($groups) && isset($this->data['include']['groups'])) {
				return array_intersect($groups, array_keys($this->data['include']['groups']));
			}
		}
		return array();
	}

	/**
	 * method to check if rate is part of group of rates balance
	 * there is option to create balance for group of rates
	 * 
	 * @param array $rate the rate to check
	 * @param string $usageType the usage type to check
	 * @return true when the rate is part of group else false
	 */
	public function isRateInPlanGroup($rate, $usageType) {
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
			$this->setPlanGroup(FALSE);
		} else if ($reset || is_null($this->getPlanGroup())) { // if reset required or it's the first set
			$this->setPlanGroup(reset($this->groups));
		} else if (next($this->groups) !== FALSE) {
			$this->setPlanGroup(current($this->groups));
		} else {
			$this->setPlanGroup(FALSE);
		}

		return $this->getPlanGroup();
	}

	public function setPlanGroup($group) {
		$this->groupSelected = $group;
	}

	public function getPlanGroup() {
		return $this->groupSelected;
	}

	public function unsetGroup($group) {
		$item = array_search($group, $this->groups);
		if (isset($this->groups[$item])) {
			unset($this->groups[$item]);
		}
	}

	/**
	 * method to receive the usage left in group of rates of current plan
	 * 
	 * @param array $subscriberBalance subscriber balance
	 * @param array $rate the rate to check the balance
	 * @param string $usageType the 
	 * @return int|string
	 */
	public function usageLeftInPlanGroup($subscriberBalance, $rate, $usageType = 'call') {
		do {
			$groupSelected = $this->setNextStrongestGroup($rate, $usageType);
			// group not found
			if ($groupSelected === FALSE) {
				$rateUsageIncluded = 0;
				// @todo: add more logic instead of fallback to first
				$this->setPlanGroup($this->setNextStrongestGroup($rate, $usageType, true));
				break; // do-while
			}
			// not group included in the specific usage try to take iterate next group
			if (!isset($this->data['include']['groups'][$groupSelected][$usageType])) {
				$groupSelected = FALSE;
				continue;
			}
			$rateUsageIncluded = $this->data['include']['groups'][$groupSelected][$usageType];
			if (isset($this->data['include']['groups'][$groupSelected]['limits'])) {
				// on some cases we have limits to unlimited
				$limits = $this->data['include']['groups'][$groupSelected]['limits'];
				Billrun_Factory::dispatcher()->trigger('planGroupRule', array(&$rateUsageIncluded, &$groupSelected, $limits, $this, $usageType, $rate, &$subscriberBalance));
				if ($rateUsageIncluded === FALSE) {
					$this->unsetGroup($this->getPlanGroup());
				}
			}
		}
		// @todo: protect max 5 loops
		while ($groupSelected === FALSE);

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
	 * Get the usage left in the current plan.
	 * @param $subscriberBalance the current sunscriber balance.
	 * @param $usagetype the usage type to check.
	 * @return int  the usage  left in the usage type of the subscriber.
	 */
	public function usageLeftInBasePlan($subscriberBalance, $rate, $usagetype = 'call') {

		if (!isset($this->get('include')[$usagetype])) {
			return 0;
		}

		$usageIncluded = $this->get('include')[$usagetype];
		if ($usageIncluded == 'UNLIMITED') {
			return PHP_INT_MAX;
		}

		$usageLeft = $usageIncluded - $subscriberBalance['balance']['totals'][$this->getBalanceTotalsKey($usagetype, $rate)]['usagev'];
		return floatval($usageLeft < 0 ? 0 : $usageLeft);
	}

	/**
	 * Get the price of the current plan.
	 * @return float the price  of the plan without VAT.
	 */
	public function getPrice() {
		return $this->get('price');
	}

	public function getName() {
		return $this->get('name');
	}

	/**
	 * create  a DB reference to the current plan
	 * @param type $collection (optional) the collection to use to create the reference.
	 * @return MongoDBRef the refernce to current plan.
	 */
	public function createRef($collection = false) {
		if (count($this->plan_ref) == 0) {
			$collection = $collection ? $collection :
					($this->data->collection() ? $this->data->collection() : Billrun_Factory::db()->plansCollection() );
			$this->plan_ref = $this->data->createRef($collection);
		}
		return $this->plan_ref;
	}

	public function isUnlimited($usage_type) {
		return isset($this->data['include'][$usage_type]) && $this->data['include'][$usage_type] == "UNLIMITED";
	}

	public function isUnlimitedRate($rate, $usageType) {
		return (isset($this->data['include']['rates'][$rate['key']][$usageType]) && $this->data['include']['rates'][$rate['key']][$usageType] == "UNLIMITED");
	}

	public function isUnlimitedGroup($rate, $usageType) {
		$groupSelected = $this->getPlanGroup();
		if ($groupSelected === FALSE) {
			return FALSE;
		}
		return (isset($this->data['include']['groups'][$groupSelected][$usageType]) && $this->data['include']['groups'][$groupSelected][$usageType] == "UNLIMITED");
	}

	public function getBalanceTotalsKey($usage_type, $rate) {
		if ($this->isRateInBasePlan($rate, $usage_type)) {
			$usage_class_prefix = "";
		} else {
			$usage_class_prefix = "out_plan_";
		}
		return $usage_class_prefix . $usage_type;
	}

}

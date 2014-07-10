<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
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
			if (empty(self::$plans)) {
				$this->initPlans();
			}
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
						->cursor()->setReadPreference(Billrun_Factory::config()->getConfigValue('read_only_db_pref'))
						->current();
					$this->data->collection(Billrun_Factory::db()->plansCollection());
				}
			}
		}
	}

	protected function initPlans() {
		$plans_coll = Billrun_Factory::db()->plansCollection();
		$plans = $plans_coll->query()->cursor()->setReadPreference(Billrun_Factory::config()->getConfigValue('read_only_db_pref'));
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
	 * @deprecated since 2.7
	 */
	public function usageLeftInRateBalance($subscriberBalance, $rate, $usageType = 'call') {
		if (!isset($this->get('include')[$rate['key']][$usageType])) {
			return 0;
		}
		
		$rateUsageIncluded = $this->get('include')[$rate['key']][$usageType];

		if ($rateUsageIncluded == 'UNLIMITED') {
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
		return false;
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
		if ($this->getRateGroups($rate, $usageType)) {
			return true;
		}
		return false;
	}
	
	/**
	 * method to receive the strongest group of list of groups
	 * currently the strongest rule is simple the first rule selected
	 * 
	 * @param array $rate the rate to check
	 * @param string $usageType the usage type to check
	 * 
	 * @return false when no group, else string name of the group
	 * @todo create mechanism to define the strongest group
	 */
	public function getStrongestGroup($rate, $usageType) {
		$groups = $this->getRateGroups($rate, $usageType);
		if (empty($groups)) {
			return false;
		}
		
		return $groups[0];
	}

	/**
	 * method to receive the usage left in group of rates of current plan
	 * 
	 * @param array $subscriberBalance subscriber balance
	 * @param array $rate the rate to check the balance
	 * @param string $usageType the 
	 * @return int|string
	 */
	public function usageLeftInRateGroup($subscriberBalance, $rate, $usageType = 'call') {
		$groupSelected = $this->getStrongestGroup($rate, $usageType);
		if ($groupSelected === FALSE) {
			return 0;
		}
		
		$rateUsageIncluded = $this->data['include']['groups'][$groupSelected][$usageType];

		// if isset $rule {
		// Billrun_Factory::dispatcher()->trigger('triggerGroupRateRule', array($rule, $this, $group, $usageType, $rate, &$usageLeft));
		// return $usageLeft
		// }
		// else continue as usual
		
		if ($rateUsageIncluded == 'UNLIMITED') {
			return PHP_INT_MAX;
		}
		
		if (isset($subscriberBalance['groups'][$groupSelected][$usageType]['usagev'])) {
			$subscriberSpent = $subscriberBalance['groups'][$groupSelected][$usageType]['usagev'];
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
	public function usageLeftInPlan($subscriberBalance, $rate, $usagetype = 'call') {

		if (!isset($this->get('include')[$usagetype])) {
			return 0;
		}
		
		$usageIncluded = $this->get('include')[$usagetype];
		if ($usageIncluded == 'UNLIMITED') {
			return PHP_INT_MAX;
		}

		$usageLeft = $usageIncluded - $subscriberBalance['totals'][$this->getBalanceTotalsKey($usagetype, $rate)]['usagev'];
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
		return (isset($this->data['include']['rates'][$rate['key']][$usageType]) 
			&& $this->data['include']['rates'][$rate['key']][$usageType] == "UNLIMITED");
	}

	public function isUnlimitedGroup($rate, $usageType) {
		$groupSelected = $this->getStrongestGroup($rate, $usageType);
		if ($groupSelected === FALSE) {
			 return FALSE;
		}
		return (isset($this->data['include']['groups'][$groupSelected][$usageType]) 
			&& $this->data['include']['groups'][$groupSelected][$usageType] == "UNLIMITED");
	}
	
	public function getBalanceTotalsKey($usage_type, $rate) {
		if (($usage_type == "call" || $usage_type == "sms") && !$this->isRateInBasePlan($rate, $usage_type)) {
			$usage_class_prefix = "out_plan_";
		} else {
			$usage_class_prefix = "";
		}
		return $usage_class_prefix . $usage_type;
	}

}
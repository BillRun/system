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
	 * @var array
	 */
	protected $data = array();
	
	/**
	 * constructor
	 * set the data instance
	 * 
	 * @param array $params array of parmeters (plan name & time)
	 */
	public function __construct(array $params = array()) {
		if (!isset($params['name']) || isset($params['time'])) {
			//throw an error
		}
		$date = new MongoDate($params['time']);
		$this->data = Billrun_Factory::db()
			->plansCollection()
			->greaterEq('from', $date)
			->lessEq('to', $date)
			->query('name', $params['name'])
			->cursor()
			->current();
	}

	/**
	 * method to pull plan data
	 * 
	 * @param string $name the property name; could be mongo key
	 * 
	 * @return mixed the property value
	 */
	public function get($name) {
		return $this->data->get($name);
	}

	/**
	 * check if a subscriber 
	 * @param type $rate
	 * @param type $sub
	 * @return boolean
	 * @deprecated since version 0.1
	 *		should be removed from here; 
	 *		the check of plan should be run on line not subscriber/balance
	 */
	public function isRateInSubPlan($rate, $sub, $type) {
		return isset($rate['rates'][$type]['plans']) &&
			is_array($rate['rates'][$type]['plans']) &&
			in_array($sub['plan_current'], $rate['rates'][$type]['plans']);
	}

	/**
	 * TODO  move to a different class
	 * @deprecated since version 0.1
	 *		should be removed from here; 
	 *		the check of plan should be run on line not subscriber/balance
	 */
	public function usageLeftInPlan($subscriber, $usagetype = 'call') {

		if (!isset($subscriber['balance']['usage_counters'][$usagetype])) {
			throw new Exception("Inproper usage counter requested : $usagetype from subscriber : " . print_r($subscriber, 1));
		}

		if (!($plan = self::get($subscriber['plan_current']))) {
			throw new Exception("Couldn't load plan for subscriber : " . print_r($subscriber, 1));
		}
		$usageLeft = 0;
		if (isset($plan['include'][$usagetype])) {
			if ($plan['include'][$usagetype] == 'UNLIMITED') {
				return PHP_INT_MAX;
			}
			$usageLeft = $plan['include'][$usagetype] - $subscriber['balance']['usage_counters'][$usagetype];
		}
		return floatval($usageLeft < 0 ? 0 : $usageLeft);
	}

}

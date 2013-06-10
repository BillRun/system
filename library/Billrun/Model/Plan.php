<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Plan
 *
 * @author eran
 */
class Billrun_Model_Plan {

	/**
	 * 
	 * @param string $name
	 * @param datetime $plan_date
	 * @return type
	 */
	static public function get($name, $plan_date = null) {
		if (is_null($plan_date)) {
			$plan_date = Billrun_Util::generateCurrentTime();
		}
		$plan_date = new MongoDate(strtotime($plan_date));
		$filter_plan = array(
			'name' => $name,
			'from' => array(
				'$lte' => $plan_date,
			),
			'to' => array(
				'$gte' => $plan_date,
			),
		);
		return Billrun_Factory::db()->plansCollection()->query($filter_plan)->cursor()->current();
	}

	/**
	 * check if a subscriber 
	 * @param type $rate
	 * @param type $sub
	 * @return type
	 */
	static public function isRateInSubPlan($rate, $sub, $type) {
		return isset($rate['rates'][$type]['plans']) &&
			is_array($rate['rates'][$type]['plans']) &&
			in_array($sub['plan_current'], $rate['rates'][$type]['plans']);
	}

	/**
	 * TODO  move to a different class
	 */
	public static function usageLeftInPlan($subscriber, $usagetype = 'call') {

		if (!isset($subscriber['balance']['usage_counters'][$usagetype])) {
			throw new Exception("Inproper usage counter requested : $usagetype from subscriber : " . print_r($subscriber, 1));
		}

		if (!($plan = self::get($subscriber['plan_current']))) {
			throw new Exception("Couldn't load plan for subscriber : " . print_r($subscriber, 1));
		}

		if ($plan['include'][$usagetype] == 'UNLIMITED') { //@TODO $plan is a ref...
			return PHP_INT_MAX;
		}
		$usageLeft = $plan['include'][$usagetype] - $subscriber['balance']['usage_counters'][$usagetype];

		return floatval($usageLeft < 0 ? 0 : $usageLeft);
	}

	static public function getPlanRef($name, $plan_date = null) {
		return self::get($name, $plan_date)->getId();
	}

}

?>

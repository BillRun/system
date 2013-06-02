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
	
	static public function get($name) {
			return Billrun_Factory::db()->plansCollection()->query('name',$name)->cursor()->current();
	}

	/**
	 * check if a subscriber 
	 * @param type $rate
	 * @param type $sub
	 * @return type
	 */
	static public function isRateInSubPlan($rate,$sub,$type) {			
			return isset($rate['rates'][$type]['plans']) && 
					is_array($rate['rates'][$type]['plans']) && 
					in_array($sub['plan_current'], $rate['rates'][$type]['plans']);
	}
	
	/**
	 * TODO  move to a different class
	 */
	public static function usageLeftInPlan($subscriber,$usagetype = FALSE) {
		//TODO cache this...
		$plan = self::get($subscriber['plan']));
		
		if(!$usagetype) {
			return $plan['include']['$usagetype'];
		}		
		if(!isset($subscriber['balance']['usage_counters'][$usagetype])) {
			throw new Exception("Inproper usage counter requested : $usagetype from subscriber : ".  print_r($subscriber,1));
		}
		
		if($plan['include'][$usagetype] == 'UNLIMITED') {
			return PHP_INT_MAX;
		}
		$usageLeft = $plan['include'][$usagetype] - $subscriber['balance']['usage_counters'][$usagetype];
		
		return floatval($usageLeft < 0 ? 0  : $usageLeft);
	}
}

?>

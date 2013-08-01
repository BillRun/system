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
	
	/**
	 * constructor
	 * set the data instance
	 * 
	 * @param array $params array of parmeters (plan name & time)
	 */
	public function __construct(array $params = array()) {
		if ((!isset($params['name']) || !isset($params['time'])) && (!isset($params['id'])) && (!isset($params['data'])) ) {
			//throw an error
			throw new Exception("plan constructor was called  without the appropiate paramters , got : ". print_r($params,1));
		}
		if(isset($params['data'])) {
			$this->data =  $params['data'];
		} else if(isset($params['id'])) {
			$this->data = Billrun_Factory::db()->plansCollection()->findOne($params['id']) ;
			$this->data->collection(Billrun_Factory::db()->plansCollection());
		} else {
			$date = new MongoDate($params['time']);
			$this->data = Billrun_Factory::db()->plansCollection()
					->query(array(
							'name' => $params['name'], 
							'$or' => array(
										array('to'=> array('$gt' => $date)), 
										array('to' => null)
								) 
							))
					->lessEq('from', $date)			
					->cursor()
					->current();
			$this->data->collection(Billrun_Factory::db()->plansCollection());
		}

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
			in_array($sub->get('current_plan',true), $rate['rates'][$type]['plans']);
	}

	/**
	 * Get the usage left in the current plan.
	 * @param $subscriberBalance the current sunscriber balance.
	 * @param $usagetype the usage type to check.
	 * @return int  the usage  left in the usage type of the subscriber.
	 */
	public function usageLeftInPlan($subscriberBalance, $usagetype = 'call') {

		if (!isset($subscriberBalance['totals'][$usagetype]['usagev'])) {
			throw new Exception("Inproper usage counter requested : $usagetype from subscriber : " . print_r($subscriber, 1));
		}

		/*if ( ($this->getRef() != $subscriberBalance['current_plan']) ) {
			throw new Exception("Couldn't load plan for subscriber : " . print_r($subscriber, 1));
		}*/
		$usageLeft = 0;
		if (isset($this->get('include')[$usagetype])) {
			if ($this->get('include')[$usagetype] == 'UNLIMITED') {
				return PHP_INT_MAX;
			}
			$usageLeft = $this->get('include')[$usagetype] - $subscriberBalance['totals'][$usagetype]['usagev'];
		}
		return floatval($usageLeft < 0 ? 0 : $usageLeft);
	}
	
	/**
	 * Get the price of the current plan.
	 * @return float the price  of the plan without VAT.
	 */
	public function getPrice() {
		return $this->get('price');
	}
	
	/**
	 * create  a DB reference to the current plan
	 * @param type $collection (optional) the collection to use to create the reference.
	 * @return MongoDBRef the refernce to current plan.
	 */
	public function createRef($collection = false ) {
		$collection =$collection ? $collection : 
					($this->data->collection() ? $this->data->collection() : Billrun_Factory::db()->plansCollection() );
		return $this->data->createRef( $collection );
	}

	static public function getPlanRef($name, $plan_date = null) {
		return self::get($name, $plan_date)->createRef(Billrun_Factory::db()->plansCollection());
	}
	
}

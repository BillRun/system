<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing plan class
 *
 * @package  Plan
 * @since    0.5
 */
class Billrun_Plan extends Billrun_Service {

	const PLAN_SPAN_YEAR = 'year';
	const PLAN_SPAN_MONTH = 'month';

	protected static $cache = array();
	protected $plan_ref = array();
	protected $planActivation;
	protected $planDeactivation = null;
	protected static $cacheType = 'plans';

	/**
	 * constructor
	 * set the data instance
	 *
	 * @param array $params array of parameters (plan name & time)
	 */
	public function __construct(array $params = array()) {
		if ((!isset($params['name']) || !isset($params['time'])) && (!isset($params['id'])) && (!isset($params['data']))) {
			//throw an error
			throw new Exception("Plan constructor was called without the appropriate parameters. Got : " . print_r($params, 1));
		}
		if (isset($params['data'])) {
			$this->data = $params['data'];
		} else if (isset($params['id'])) {
			$this->constructWithID($params['id']);
		} else {
			$this->constructWithActivePlan($params);
		}

		$this->constructExtraOptions($params);
	}

	/**
	 * Query the DB with the input ID and set it as the plan data.
	 * @param type $id
	 * @todo use load method
	 */
	protected function constructWithID($id) {
		if ($id instanceof Mongodloid_Id) {
			$filter_id = strval($id->getMongoId());
		} else if ($id instanceof MongoId) {
			$filter_id = strval($id);
		} else {
			// probably a string
			$filter_id = $id;
		}
		$plan = $this->getPlanById($filter_id);
		if ($plan) {
			$this->data = $plan;
		} else {
			$this->data = Billrun_Factory::db()->plansCollection()->findOne($id);
			$this->data->collection(Billrun_Factory::db()->plansCollection());
		}
	}

	/**
	 * Construct the plan with the active plan in the data base
	 * @param type $params
	 * @return type
	 * @todo use load method
	 */
	protected function constructWithActivePlan($params) {
		$date = new MongoDate($params['time']);
		$plan = static::getByNameAndTime($params['name'], $date);
		if ($plan) {
			$this->data = $plan;
			return;
		}

		$planQuery = array(
			'name' => $params['name'],
			'$or' => array(
				array('to' => array('$gt' => $date)),
				array('to' => null)
			)
		);
		$plansColl = Billrun_Factory::db()->plansCollection();
		$planRecord = $plansColl->query($planQuery)->lessEq('from', $date)->cursor()->current();
		$planRecord->collection($plansColl);
		$this->data = $planRecord;
	}

	/**
	 * Handle constructing the instance with extra input options, if available
	 * @param arrray $options
	 */
	protected function constructExtraOptions($options) {
		if (isset($options['activation'])) {
			$this->planActivation = $options['activation'];
		}
		if (isset($options['deactivation'])) {
			$this->planDeactivation = $options['deactivation'];
		}
	}

	public function getData($raw = false) {
		if ($raw) {
			return $this->data->getRawData();
		}
		return $this->data;
	}

	public function getActivation() {
		return $this->planActivation;
	}

	public function getDectivation() {
		return $this->planDeactivation;
	}

	/**
	 * get the plan by its id
	 *
	 * @param string $id
	 *
	 * @return array of plan details if id exists else false
	 */
	protected function getPlanById($id) {
		if (isset(self::$cache['by_id'][$id])) {
			return self::$cache['by_id'][$id];
		}
		return false;
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

		if ($rateUsageIncluded === Billrun_Service::UNLIMITED_VALUE) {
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
	 * Get the usage left in the current plan.
	 * @param $subscriberBalance the current sunscriber balance.
	 * @param $usagetype the usage type to check.
	 * @return int  the usage  left in the usage type of the subscriber.
	 * @deprecated since version 5.0
	 */
	public function usageLeftInBasePlan($subscriberBalance, $rate, $usagetype = 'call') {

		if (!isset($this->get('include')[$usagetype])) {
			return 0;
		}

		$usageIncluded = $this->get('include')[$usagetype];
		if ($usageIncluded == Billrun_Service::UNLIMITED_VALUE) {
			return PHP_INT_MAX;
		}

		$usageLeft = $usageIncluded - $subscriberBalance['balance']['totals'][$this->getBalanceTotalsKey($usagetype, $rate)]['usagev'];
		return floatval($usageLeft < 0 ? 0 : $usageLeft);
	}

	/**
	 * Get the price of the current plan.
	 * @return float the price of the plan without VAT.
	 */
	public function getPrice($from, $to, $firstActivation = null) {
		if ($firstActivation === null) {
			$firstActivation = $this->getActivation();
		}

		$startOffset = Billrun_Utils_Time::getMonthsDiff($firstActivation, date(Billrun_Base::base_dateformat, strtotime('-1 day', strtotime($from))));
		$endOffset = Billrun_Utils_Time::getMonthsDiff($firstActivation, $to);
		$charges = array();
		if ($this->isUpfrontPayment()) {
			return $this->getPriceForUpfrontPayment($startOffset);
		}

		if ($this->getPeriodicity() != 'month') {
			Billrun_Factory::log("Plan get price cannot handle non month periodicity value");
			return 0;
		}

		foreach ($this->data['price'] as $tariff) {
			$price = self::getPriceByTariff($tariff, $startOffset, $endOffset);
			$charges[] = array('value' => $price['price'], 'cycle' => $tariff['from']);
		}
		return $charges;
	}

	public function getNextTierDate($firstActivation,  $currentDate) {
		$startOffset = Billrun_Utils_Time::getMonthsDiff( date(Billrun_Base::base_dateformat,$firstActivation ), date(Billrun_Base::base_dateformat, strtotime('-1 day', $currentDate)));
		foreach ($this->data['price'] as $tariff) {
			if($tariff['from'] > $startOffset) {
				return static::monthDiffToDate($tariff['from'], $firstActivation);
			}

		}

		return FALSE;
	}

	/**
	 * Validate the input to the getPriceByTariff function
	 * @param type $tariff
	 * @param type $startOffset
	 * @param type $endOffset
	 * @return boolean
	 */
	protected static function validatePriceByTariff($tariff, $startOffset, $endOffset) {
		if ($tariff['from'] > $tariff['to'] && !static::isValueUnlimited($tariff['to']) ) {
			Billrun_Factory::log("getPriceByTariff received invalid tariff.", Zend_Log::CRIT);
			return false;
		}

		if ($startOffset > $endOffset) {
			Billrun_Factory::log("getPriceByTariff received invalid offset values.", Zend_Log::WARN);
			return false;
		}

		if ($startOffset > $tariff['to'] && !static::isValueUnlimited($tariff['to'])) {
			Billrun_Factory::log("getPriceByTariff start offset is out of bounds.", Zend_Log::WARN);
			return false;
		}

		if ($endOffset < $tariff['from']) {
			Billrun_Factory::log("getPriceByTariff end offset is out of bounds.", Zend_Log::WARN);
			return false;
		}
		return true;
	}

	/**
	 * Get the price to charge by a tariff
	 * @param type $tariff
	 * @param type $startOffset
	 * @param type $endOffset
	 * @return int
	 */
	public static function getPriceByTariff($tariff, $startOffset, $endOffset ,$activation = FALSE) {
		if (!self::validatePriceByTariff($tariff, $startOffset, $endOffset)) {
			return 0;
		}

		$endPricing = $endOffset;
		$startPricing = $startOffset;

		if ($tariff['from'] > $startOffset) {
			$startPricing = $tariff['from'];
			// HACK :  fix for the month length differance between the  activation and the  plan change , NOTICE will only work on monthly charges
			if(round($endOffset -1,6) == round($startOffset,6) && $activation && $startOffset > 0) {
				$startFratcion = 1 -($startOffset-floor($startOffset));
				$currentDays = date('t',Billrun_Plan::monthDiffToDate($endOffset, $activation));
				$startPricing += ((($startFratcion * date('t',$activation)) /  $currentDays) - $startFratcion);
			}
		}
		if (!static::isValueUnlimited($tariff['to']) && $tariff['to'] < $endOffset) {
			$endPricing = $tariff['to'];
			// HACK :  fix for the month length differance between the  activation and the  plan change , NOTICE will only work on monthly charges
			if(round($endOffset -1,6) == round($startOffset,6) && $activation && $startOffset > 0) {
				$endFratcion = 1 -($startOffset - floor($startOffset));
				$currentDays = date('t',Billrun_Plan::monthDiffToDate($endOffset, $activation));
				$endPricing += (( ($endFratcion * date('t',$activation)) / $currentDays) - $endFratcion);
			}
		}
		//If the tariff is of expired service/plan don't charge anything
		if(!static::isValueUnlimited($tariff['to']) && $tariff['to'] <= $startPricing && $tariff['from'] < $startPricing) {
            return 0;
		}
		$fullMonth = (round(($endPricing - $startPricing), 5) == 1 || $endPricing == $startPricing);
		return array('start' => $fullMonth ? FALSE : $startPricing,
			'end' => $fullMonth ? FALSE : $endPricing,
			'price' => ($endPricing - $startPricing) * $tariff['price']);
	}

	/**
	 * Get the price of the current plan when the plan is to be paid upfront
	 * @param type $startOffset
	 * @return price
	 */
	protected function getPriceForUpfrontPayment($startOffset) {
		if ($this->getPeriodicity() == 'year') {
			$startOffset = $startOffset / 12;
		}
		foreach ($this->data['price'] as $tariff) {
			if ($tariff['from'] <= $startOffset && $tariff['to'] > $startOffset) {
				return $tariff['price'];
			}
		}

		return 0;
	}

	public function getSpan() {
		return $this->data['recurrence']['unit'];
	}

	/**
	 * @deprecated
	 * (replaced by non-monthly plans)
	 * Get the plan periodicity value
	 */
	public function getPeriodicity() {
		return $this->data['recurrence']['periodicity'];
	}

	/**
	 * get the plan  recurence (frequency/start month) configuration
	 * @returns the plan recurence configuration (frequency/start month)
	 */
	public function getRecurrenceConfig() {
		return $this->data['recurrence'];
	}

	/**
	 * Is the current plan is a non monthly/quertely plan
	 * @returns  true if the plan is configred to be a non-monthly plan false otherwise
	 */
	public function isNonMonthly() {
		return !empty($this->data['recurrence']['frequency']) && $this->data['recurrence']['frequency'] != 1;
	}
	/**
	 * create  a DB reference to the current plan
	 * @param type $collection (optional) the collection to use to create the reference.
	 * @return MongoDBRef the refernce to current plan.
	 * @todo Should the collection here really be false by default? I think it's safer
	 * if the user of this function will have to specify a collection.
	 */
	public function createRef($collection = false) {
		if (count($this->plan_ref) == 0) {
			$collection = $collection ? $collection :
				($this->data->collection() ? $this->data->collection() : Billrun_Factory::db()->plansCollection() );
			$this->plan_ref = $collection->createRefByEntity($this->data);
		}
		return $this->plan_ref;
	}

	public static function isValueUnlimited($value) {
		return $value == Billrun_Service::UNLIMITED_VALUE;
	}

	public function isUnlimited($usage_type) {
		return isset($this->data['include'][$usage_type]) && $this->data['include'][$usage_type] == Billrun_Service::UNLIMITED_VALUE;
	}

	public function isUnlimitedRate($rate, $usageType) {
		return (isset($this->data['include']['rates'][$rate['key']][$usageType]) && $this->data['include']['rates'][$rate['key']][$usageType] == Billrun_Service::UNLIMITED_VALUE);
	}

	public function isUnlimitedGroup($rate, $usageType) {
		$groupSelected = $this->getEntityGroup();
		if ($groupSelected === FALSE) {
			return FALSE;
		}
		return (isset($this->data['include']['groups'][$groupSelected][$usageType]) && $this->data['include']['groups'][$groupSelected][$usageType] == Billrun_Service::UNLIMITED_VALUE);
	}

	/**
	 * method to get balance totals key
	 *
	 * @param string $usage_type
	 * @param array $rate rate handle
	 * @return string
	 *
	 * @deprecated since version 5.2
	 */
	public function getBalanceTotalsKey($usage_type, $rate) {
		return $usage_type;
	}

	public function isUpfrontPayment() {
		return !empty($this->data['upfront']);
	}

	/**
	 * calcualte the date based on monthly difference from activation.
	 * @return the unix time of the  monthly fraction from activation.
	 */
	public static function monthDiffToDate($cycleFraction , $activationTime , $isStart = TRUE, $deactivationTime = FALSE,$deactivated = FALSE,$cycleDuration = 1) {
		if(empty($cycleFraction) ) {
			return $isStart ? $activationTime : $deactivationTime;
		}
		$cycleFraction = $cycleFraction * $cycleDuration;
		$activation  =  new DateTime(date('Y-m-d 00:00:00', $activationTime));
		$addedMonths = 0;

		//add the starting month fraction
		$addedDays = $activation->format('t') - $activation->format('d') + 1;
		$startFraction = ( $addedDays ) / $activation->format('t');
		$resultDate = new DateTime($activation->format('Y-m-d'));

		if($cycleFraction - $startFraction > 0) {
			$i = $cycleFraction - $startFraction;
			$resultDate->modify($addedDays.' day');
		} else {
			$i = $cycleFraction;
		}

		//Accumulate the full months days that were passsed since the  activation date.
		if($i > 0) {
			$addedMonths = $i - ($i - floor($i));
			$resultDate->modify($addedMonths.' month');
			$i = $i - $addedMonths;
		}
		//if there was a fraction of a month left split it to the  starting month fraction and ending month fraction (due to diffrent month lengths)
		if( $i != 0 ) {
			//based on the starting month fraction  retrive the  current month fraction
			$endFraction = $i;
			$daysInMonth = $resultDate->format('t');
			$roundedDays = floor(round($daysInMonth *  $endFraction ,6));
			$resultDate->modify($roundedDays.' day');
			if($resultDate->format('t') != $resultDate->format('d') && $resultDate->format('d') != "01" && empty($deactivated)) {
				$resultDate->modify('-1 day');
			}
		}
		return $resultDate->format('U') + ($isStart ? 0 : -1);
	}

	public static function calcFractionOfMonthUnix($billrunKey, $start_date, $end_date) {
		return static::calcFractionOfMonth(	$billrunKey,
											date(Billrun_Base::base_datetimeformat, $start_date),
											date(Billrun_Base::base_datetimeformat, $end_date) );
	}

	public static function calcFractionOfMonth($billrunKey, $start_date, $end_date) {
		$billing_start_date = Billrun_Billingcycle::getStartTime($billrunKey);
		$billing_end_date = Billrun_Billingcycle::getEndTime($billrunKey);
		$days_in_month = (int) date('t', $billing_start_date);
		$temp_start = strtotime($start_date);
		$temp_end = is_null($end_date) ? PHP_INT_MAX : strtotime($end_date);
		$start = $billing_start_date > $temp_start ? $billing_start_date : $temp_start;
		$end = $billing_end_date < $temp_end ? $billing_end_date : $temp_end;
		if ($end < $start) {
			return 0;
		}
		$start_day = date('j', $start);
		$end_day = date('j', $end);
		$start_month = date('F', $start);
		$end_month = date('F', $end);

		if ($start_month == $end_month) {
			$days_in_plan = (int) $end_day - (int) $start_day + 1;
		} else {
			$days_in_plan = $days_in_month - (int) $start_day + 1;
		}

		$fraction = $days_in_plan / $days_in_month;
		return $fraction;
	}

	public function getFieldsForLine() {
		return Billrun_Factory::config()->getConfigValue('plans.lineFields', array());
	}

}

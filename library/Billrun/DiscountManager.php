<?php

/**
 * Discount management
 */
class Billrun_DiscountManager {

	protected $startTime = null;
	protected $endTime = null;
	protected $eligibleDiscounts = [];
	protected static $discounts = [];
	protected static $discountsFields = [];

	public function __construct($accountRevisions, $subscribersRevisions = [], $params = []) {
		$time = Billrun_Util::getIn($params, 'time', time());
		$billrunKey = Billrun_Billingcycle::getBillrunKeyByTimestamp($time);
		$this->startTime = Billrun_Billingcycle::getStartTime($billrunKey);
		$this->endTime = Billrun_Billingcycle::getEndTime($billrunKey);
		$this->loadEligibleDiscounts($accountRevisions, $subscribersRevisions);
	}

	/**
	 * loads account's discount eligibilities
	 * 
	 * @param array $accountRevisions
	 * @param array $subscribersRevisions
	 */
	protected function loadEligibleDiscounts($accountRevisions, $subscribersRevisions = []) {
		$this->eligibleDiscounts = [];
		$accountDiscountsFields = self::getDiscountsFields('account');
		$subscrbierDiscountsFields = self::getDiscountsFields('subscriber');
		$accountRevisions = self::prepareEntityRevisions($accountRevisions, $accountDiscountsFields);
		$subscribersRevisions = array_map([$this, 'prepareEntityRevisions', [$subscrbierDiscountsFields]], $subscribersRevisions);
		
		foreach (self::getDiscounts() as $discount) {
			$eligibilityDates = $this->getDiscountEligibility($discount, $accountRevisions, $subscribersRevisions);
			if (!empty($eligibilityDates)) {
				$this->eligibleDiscounts[$discount['key']] = [
					'discount' => $discount,
					'eligibility' => $eligibilityDates,
				];
			}
		}
	}
	
	/**
	 * splits existing revisions' ranges fields
	 * 
	 * @param type $entityRevisions
	 * @param array $fields - specific fields to split, if null received - splits all fields
	 * @return array
	 * @todo implement
	 */
	public static function prepareEntityRevisions($entityRevisions, $fields = null) {
		return $entityRevisions;
	}

	/**
	 * Get eligible discounts for account
	 * 
	 * @return array - array of discounts for the account
	 */
	public function getEligibleDiscounts($discountsOnly = false) {
		if ($discountsOnly) {
			return array_column($this->eligibleDiscounts, 'discount');
		}
		
		return $this->eligibleDiscounts;
	}

	/**
	 * get all active discounts in the system
	 * uses internal static cache
	 * 
	 * @return array
	 */
	public static function getDiscounts($query = [], $time = null) {
		if (empty(self::$discounts)) {
			$cycleEndTime = new MongoDate(is_null($time) ? $this->endTime : $time);
			$basicQuery = [
				'params' => [
					'$exists' => 1,
				],
				'from' => [
					'$lt' => $cycleEndTime,
				],
				'to' => [
					'$gt' => $cycleEndTime,
				],
			];
			$discountColl = Billrun_Factory::db()->discountsCollection();
			$loadedDiscounts = $discountColl->query(array_merge($basicQuery, $query))->cursor();
			self::$discounts = [];
			foreach ($loadedDiscounts as $discount) {
				self::$discounts[$discount['key']] = $discount;
			}
		}

		return self::$discounts;
	}

	/**
	 * get all fields used by discount for the given $type
	 * uses internal static cache
	 * 
	 * @param string $type
	 * @return array
	 */
	public static function getDiscountsFields($type) {
		if (empty(self::$discountsFields[$type])) {
			self::$discountsFields[$type] = [];
			foreach (self::getDiscounts() as $discount) {
				foreach (Billrun_Util::getIn($discount, ['params', 'conditions'], []) as $condition) {
					if (!isset($condition[$type])) {
						continue;
					}
					
					foreach (Billrun_Util::getIn($condition, [$type, 'fields'], []) as $field) {
						self::$discountsFields[$type][] = $field['field_name'];
					}
				}
			}
			
			self::$discountsFields[$type] = array_unique(self::$discountsFields[$type]);
		}

		return self::$discountsFields[$type];
	}
	
	/**
	 * Get sorted time intervals when the account is eligible for the given discount 
	 * 
	 * @param array $conditions
	 * @param array $accountRevisions
	 * @param array $subscribersRevisions
	 * @return array of intervals
	 */
	protected function getDiscountEligibility($discount, $accountRevisions, $subscribersRevisions = []) {
		$conditions = Billrun_Util::getIn($discount, 'conditions', []);
		if (empty($conditions)) { // no conditions means apply to all entities
			return [
				$this->getAllCycleInterval(),
			];
		}
		
		$minSubscribers = Billrun_Util::getIn($discount, 'params.min_subscribers', 1);
		$maxSubscribers = Billrun_Util::getIn($discount, 'params.max_subscribers', null);
		$eligibility = [];
		
		if (count($subscribersRevisions) < $minSubscribers) { // skip conditions check if there are not enough subscribers
			return false;
		}
		
		foreach ($conditions as $condition) { // OR logic
			$conditionEligibility = $this->getConditionEligibility($condition, $accountRevisions, $subscribersRevisions, $minSubscribers, $maxSubscribers);
			
			if (empty($conditionEligibility)) {
				continue;
			}
			
			$eligibility = array_merge($eligibility, $conditionEligibility);
		}
		
		return Billrun_Utils_Time::mergeTimeIntervals($eligibility);
	}
	
	/**
	 * Get time intervals when the given condition is met for the account
	 * 
	 * @param array $conditions
	 * @param array $accountRevisions
	 * @param array $subscribersRevisions
	 * @param int $minSubscribers
	 * @param int $maxSubscribers - or null if no maximum is set
	 * @return array of intervals
	 */
	protected function getConditionEligibility($condition, $accountRevisions, $subscribersRevisions = [], $minSubscribers = 1, $maxSubscribers = null) {
		$accountEligibility = [];
		$subsEligibility = [];
		
		$accountConditions = Billrun_Util::getIn($condition, 'account.fields', []);
		
		if (empty($accountConditions)) {
			$accountEligibility[] = $this->getAllCycleInterval();
		}
		
		foreach ($accountConditions as $accountCondition) { // AND logic
			$eligibility = $this->getConditionEligibilityForEntity($accountCondition, $accountRevisions);
			if (empty($eligibility)) {
				return false; // account conditions must match
			}
			$accountEligibility = array_merge($accountEligibility, $eligibility);
		}
		
		$accountEligibility = Billrun_Utils_Time::mergeTimeIntervals($accountEligibility);

		foreach ($subscribersRevisions as $subscriberRevisions) {
			$subscribersConditions = Billrun_Util::getIn($condition, 'subscriber.fields', []);
		
			if (empty($subscribersConditions)) {
				$subsEligibility[$subscriberRevisions[0]['sid']] = [
					$this->getAllCycleInterval(),
				];
			}
			
			foreach ($subscribersConditions as $subscribersCondition) { // AND logic
				$eligibility = $this->getConditionEligibilityForEntity($subscribersCondition, $subscriberRevisions);
				if (empty($eligibility)) {
					continue 2; // if the current subscriber does not match, check other subscribers
				}
				
				if (!isset($subsEligibility[$subscriberRevisions['sid']])) {
					$subsEligibility[$subscriberRevisions['sid']] = [];
				}
				
				$subsEligibility[$subscriberRevisions['sid']] = array_merge($subsEligibility[$subscriberRevisions['sid']], $eligibility);
			}
		}
		
		$subsEligibility = array_map([Billrun_Utils_Time, 'mergeTimeIntervals'], $subsEligibility);
		
		$ret = [];
		// goes only over accout's eligibility because it must met
		foreach ($accountEligibility as $accountEligibilityInterval) {
			// check eligibility day by day
			for ($day = $accountEligibilityInterval['from']; $day <= $accountEligibilityInterval['to']; $i = strtotime('+1 day', $i)) {
				$eligibleSubsInDay = 0;
				$dayFrom = strtotime('midnight', $day);
				$dayTo = strtotime('+1 day', $dayFrom);
				foreach ($subsEligibility as $subEligibility) {
					foreach ($subEligibility as $subEligibilityIntervals) {
						if ($subEligibilityIntervals['from'] <= $day && $subEligibilityIntervals['to'] >= $day) {
							$eligibleSubsInDay++;
							
							if (!is_null($maxSubscribers) && $eligibleSubsInDay > $maxSubscribers) { // passed max subscribers in current day
								continue 3; // check next day
							}
							
							if (is_null($maxSubscribers) && $eligibleSubsInDay >= $minSubscribers) { // passed min subscribers, and no max is defined
								$ret[] = [
									'from' => $dayFrom,
									'to' => $dayTo,
								];
								continue 3; // check next day
							}
							
							continue 2; // check next subscriber
						}
						
						if ($subEligibilityIntervals['from'] > $day) {
							continue 2; // intervals are sorted, check next subscriber
						}
					}
				}
				
				if ($eligibleSubsInDay >= $minSubscribers) { // account is eligible for the discount in current day
					$ret[] = [
						'from' => $dayFrom,
						'to' => $dayTo,
					];
				}
			}
		}
		
		return Billrun_Utils_Time::mergeTimeIntervals($ret);
	}

	/**
	 * get array of intervals on which the entity meets the condition
	 * 
	 * @param array $condition
	 * @param array $entityRevisions
	 * @return array of intervals
	 * @todo implement
	 */
	protected function getConditionEligibilityForEntity($condition, $entityRevisions) {
		// TODO: implement
	}
	
	/**
	 * gets intervals covers entire cycle
	 * 
	 * @return array
	 */
	protected function getAllCycleInterval() {
		return [
			'from' => $this->startTime,
			'to' => $this->endTime,
		];
	}

}

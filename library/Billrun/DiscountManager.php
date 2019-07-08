<?php

/**
 * Discount management
 */
class Billrun_DiscountManager {
	use Billrun_Traits_ConditionsCheck;

    protected $billrunKey = '';
    protected $startTime = null;
	protected $endTime = null;
	protected $eligibleDiscounts = [];
	
	protected static $discounts = [];
	protected static $discountsDateRangeFields = [];

	public function __construct($accountRevisions, $subscribersRevisions = [], $params = []) {
		$this->billrunKey = Billrun_Util::getIn($params, 'billrun_key', '');
        if (empty($this->billrunKey)) {
            $time = Billrun_Util::getIn($params, 'time', time());
            $this->billrunKey = Billrun_Billingcycle::getBillrunKeyByTimestamp($time);
        }
		$this->startTime = Billrun_Billingcycle::getStartTime($this->billrunKey);
		$this->endTime = Billrun_Billingcycle::getEndTime($this->billrunKey);
		$this->prepareRevisions($accountRevisions, $subscribersRevisions);
		$this->loadEligibleDiscounts($accountRevisions, $subscribersRevisions);
	}
	
	/**
	 * prepare revisions for discount calculation
	 * 
	 * @param array $accountRevisions - by reference
	 * @param array $subscribersRevisions - by reference
	 */
	protected function prepareRevisions(&$accountRevisions, &$subscribersRevisions) {
		$accountRevisions = $this->getEntityRevisions($accountRevisions, 'account');
		foreach ($subscribersRevisions as &$subscriberRevisions) {
			$subscriberRevisions = $this->getEntityRevisions($subscriberRevisions, 'subscriber');
		}
	}
	
	/**
	 * get revisions used for discount calculation
	 * 
	 * @param array $entityRevisions
	 * @param string $type
	 * @return array
	 */
	protected function getEntityRevisions($entityRevisions, $type) {
		$ret = [];
		$dateRangeDiscoutnsFields = self::getDiscountsDateRangeFields($this->billrunKey, $type);
		if (empty($dateRangeDiscoutnsFields)) {
			return $entityRevisions;
		}
		
		foreach ($entityRevisions as $entityRevision) {
			$splittedRevisions = $this->splitRevisionByFields($entityRevision, $dateRangeDiscoutnsFields);
			$ret = array_merge($ret, $splittedRevisions);
		}
		
		return $ret;
	}
	
	/**
	 * split revision to revisions by given date range fields
	 * 
	 * @param array $revision
	 * @param array $fields
	 * @return array
	 */
	protected function splitRevisionByFields($revision, $fields) {
		$ret = [];
		$revisionFrom = $revision['from']->sec;
		$revisionTo = $revision['to']->sec;
		$froms = [$revisionFrom];
		$tos = [$revisionTo];
		
		foreach ($fields as $field) {
			if (!isset($revision[$field])) {
				continue;
			}
			
			foreach ($revision[$field] as $interval) {
				$from = $interval['from'];
				$to = $interval['to'];
				
				if ($from > $revisionFrom) {
					$froms[] = $from;
				}
				
				if ($to < $revisionTo) {
					$tos[] = $to;
				}
			}
		}
		
		$intervals = array_unique(array_merge($froms, $tos));
		sort($intervals);
		
		for ($i = 1; $i < count($intervals); $i++) {
			$newRevision = $revision;
			$from = $intervals[$i - 1];
			$to = $intervals[$i];
			$newRevision['from'] = new MongoDate($from);
			$newRevision['to'] = new MongoDate($to);
			
			foreach ($fields as $field) {
				if (!isset($newRevision[$field])) {
					continue;
				}
				
				$oldIntervals = $newRevision[$field];
				$newRevision[$field] = [];
				
				foreach ($oldIntervals as $interval) {
					if (($interval['from'] <= $from && $interval['to'] > $from) ||
							($interval['from'] <= $to && $interval['to'] > $to)) {
						$newIntervalFrom = max($from, $interval['from']);
						$newIntervalTo = min($to, $interval['to']);
						if ($newIntervalTo > $newIntervalFrom) {
							$newRevision[$field][] = [
								'from' => $newIntervalFrom,
								'to' => $newIntervalTo,
							];
							break;
						}
					}
				}
				
				if (empty($newRevision[$field])) {
					unset($newRevision[$field]);
				}
				
			}
			
			$ret[] = $newRevision;
		}
		
		return $ret;
	}

	/**
	 * loads account's discount eligibilities
	 * 
	 * @param array $accountRevisions
	 * @param array $subscribersRevisions
	 */
	protected function loadEligibleDiscounts($accountRevisions, $subscribersRevisions = []) {
		$this->eligibleDiscounts = [];
		
		foreach (self::getDiscounts($this->billrunKey) as $discount) {
			$eligibilityDates = $this->getDiscountEligibility($discount, $accountRevisions, $subscribersRevisions);
			if (!empty($eligibilityDates)) {
				$this->eligibleDiscounts[$discount['key']] = [
					'eligibility' => $eligibilityDates,
				];
			}
		}
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
	 * @param unixtimestamp $time
	 * @param array $query
	 * @return array
	 */
	public static function getDiscounts($billrunKey, $query = []) {
		if (empty(self::$discounts[$billrunKey])) {
			$basicQuery = [
				'params' => [
					'$exists' => 1,
				],
                'from' => [
                    '$lte' => new MongoDate(Billrun_Billingcycle::getEndTime($billrunKey)),
                ],
                'to' => [
                    '$gte' => new MongoDate(Billrun_Billingcycle::getStartTime($billrunKey)),
                ],
			];
            
            $sort = [
                'to' => -1,
            ];
			
            $discountColl = Billrun_Factory::db()->discountsCollection();
			$loadedDiscounts = $discountColl->query(array_merge($basicQuery, $query))->cursor()->sort($sort);
			self::$discounts = [];
			
            foreach ($loadedDiscounts as $discount) {
                if (isset(self::$discounts[$billrunKey][$discount['key']]) &&
                    self::$discounts[$billrunKey][$discount['key']]['from'] == $discount['to']) {
                    self::$discounts[$billrunKey][$discount['key']]['from'] = $discount['from'];
                } else {
                    self::$discounts[$billrunKey][$discount['key']] = $discount;
                }
			}
		}

		return self::$discounts[$billrunKey];
	}
	
	/**
	 * manually set discounts
	 * 
	 * @param array $discounts
	 */
	public static function setDiscounts($discounts, $billrunKey) {
		self::$discounts[$billrunKey] = $discounts;
	}

	/**
	 * get all date range fields used by discount for the given $type
	 * uses internal static cache
	 * 
	 * @param string $billrunKey
	 * @param string $type
	 * @return array
	 */
	public static function getDiscountsDateRangeFields($billrunKey, $type) {
		if (empty(self::$discountsDateRangeFields[$billrunKey][$type])) {
			self::$discountsDateRangeFields[$billrunKey][$type] = [];
			foreach (self::getDiscounts($billrunKey) as $discount) {
				foreach (Billrun_Util::getIn($discount, ['params', 'conditions'], []) as $condition) {
					if (!isset($condition[$type])) {
						continue;
					}
					
					$typeConditions = Billrun_Util::getIn($condition, $type, []);
					if (Billrun_Util::isAssoc($typeConditions)) { // handle account/subscriber structure
						$typeConditions = [$typeConditions];
					}
					
					foreach ($typeConditions as $typeCondition) {
						foreach (Billrun_Util::getIn($typeCondition, 'fields', []) as $field) {
							if (in_array($field['value'], ['isActive'])) {
								self::$discountsDateRangeFields[$billrunKey][$type][] = $field['field'];
							}
						}
					}
				}
			}
			
			self::$discountsDateRangeFields[$billrunKey][$type] = array_unique(self::$discountsDateRangeFields[$billrunKey][$type]);
		}

		return self::$discountsDateRangeFields[$billrunKey][$type];
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
        $discountFrom = max($discount['from']->sec, $this->startTime);
        $discountTo = min($discount['to']->sec, $this->endTime);
		$conditions = Billrun_Util::getIn($discount, 'params.conditions', []);
		if (empty($conditions)) { // no conditions means apply to all entities
			return [
                [
                    'from' => $discountFrom,
                    'to' => $discountTo,
                ]
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
		
		$eligibility = Billrun_Utils_Time::mergeTimeIntervals($eligibility);
		
		foreach ($eligibility as $i => &$eligibilityInterval) {
			$eligibilityInterval['to']--; // intervals are calculated until start of next day so merge will be available
            
            // limit eligibility to discount revision (from/to)
            if ($eligibilityInterval['from'] < $discountFrom) {
                if ($eligibilityInterval['to'] <= $discountFrom) {
                    unset($eligibility[$i]);
                } else {
                    $eligibilityInterval['from'] = $discountFrom;
                }
            }
            if ($eligibilityInterval['to'] > $discountTo) {
                if ($eligibilityInterval['from'] >= $discountTo) {
                    unset($eligibility[$i]);
                } else {
                    $eligibilityInterval['to'] = $discountTo;
                }
            }
		}
		
		return $eligibility;
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
		} else {
			$accountEligibility = $this->getConditionsEligibilityForEntity($accountConditions, $accountRevisions);
			if (empty($accountEligibility)) {
				return false; // account conditions must match
			}
			$accountEligibility = Billrun_Utils_Time::mergeTimeIntervals($accountEligibility);
		}
		
		$subscribersConditions = Billrun_Util::getIn($condition, 'subscriber.0.fields', []); // currently supports 1 condtion's type

		foreach ($subscribersRevisions as $subscriberRevisions) {
			if (empty($subscribersConditions)) {
				$subsEligibility[$subscriberRevisions[0]['sid']] = [
					$this->getAllCycleInterval(),
				];
			} else {			
				$subEligibility = $this->getConditionsEligibilityForEntity($subscribersConditions, $subscriberRevisions);
				if (empty($subEligibility)) {
					continue; // if the current subscriber does not match, check other subscribers
				}
				$subsEligibility[$subscriberRevisions[0]['sid']] = Billrun_Utils_Time::mergeTimeIntervals($subEligibility);
			}
		}
		
		$ret = [];
		// goes only over accout's eligibility because it must met
		foreach ($accountEligibility as $accountEligibilityInterval) {
			// check eligibility day by day
			for ($day = $accountEligibilityInterval['from']; $day <= $accountEligibilityInterval['to']; $day = strtotime('+1 day', $day)) {
				$eligibleSubsInDay = 0;
				$dayFrom = strtotime('midnight', $day);
				$dayTo = strtotime('+1 day', $dayFrom);
				foreach ($subsEligibility as $subEligibility) {
					foreach ($subEligibility as $subEligibilityIntervals) {
						if ($subEligibilityIntervals['from'] <= $day && $subEligibilityIntervals['to'] > $day) {
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
	 * get array of intervals on which the entity meets the conditions
	 * 
	 * @param array $conditions
	 * @param array $entityRevisions
	 * @return array of intervals
	 */
	protected function getConditionsEligibilityForEntity($conditions, $entityRevisions) {
		$eligibility = [];
		foreach ($entityRevisions as $entityRevision) {
			if ($this->isConditionsMeet($entityRevision, $conditions)) {
				$eligibility[] = [
					'from' => $entityRevision['from']->sec,
					'to' => $entityRevision['to']->sec,
				];
			}
				
		}
		
		return $eligibility;
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

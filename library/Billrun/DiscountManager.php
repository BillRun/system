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
	protected static $subscribersDiscounts = [];
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
			$val = Billrun_Util::getIn($revision, $field, null);
			if (is_null($val)) {
				continue;
			}
			
			foreach ($val as $interval) {
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
				$val = Billrun_Util::getIn($newRevision, $field, null);
				if (is_null($val)) {
					continue;
				}
				
				$oldIntervals = $val;
				Billrun_Util::setIn($newRevision, $field, []);
				$newIntervals = [];
				
				foreach ($oldIntervals as $interval) {
					if (($interval['from'] <= $from && $interval['to'] > $from) ||
							($interval['from'] <= $to && $interval['to'] > $to)) {
						$newIntervalFrom = max($from, $interval['from']);
						$newIntervalTo = min($to, $interval['to']);
						if ($newIntervalTo > $newIntervalFrom) {
							$newIntervals[] = [
								'from' => $newIntervalFrom,
								'to' => $newIntervalTo,
							];
							break;
						}
					}
				}
				
				if (empty($newIntervals)) {
					Billrun_Util::unsetIn($newRevision, $field);
				} else {
					Billrun_Util::setIn($newRevision, $field, $newIntervals);
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
			$eligibility = $this->getDiscountEligibility($discount, $accountRevisions, $subscribersRevisions);
			if (!empty(Billrun_Util::getIn($eligibility, 'eligibility', []))) {
				$this->eligibleDiscounts[$discount['key']] = $eligibility;
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
				'priority' => -1,
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
		usort($discounts, function ($a, $b) {
			return Billrun_Util::getIn($b, 'priority', 0) > Billrun_Util::getIn($a, 'priority', 0);
		});
		self::$discounts[$billrunKey] = $discounts;
	}
	
	/**
	 * manually set subscriber discount
	 * 
	 * @param array $discount
	 * @param string $billrunKey
	 */
	protected static function setSubscriberDiscount($discount, $billrunKey) {
		self::$subscribersDiscounts[$billrunKey][$discounts['key']] = $discount;
	}
	
	/**
	 * get discount object by key
	 * 
	 * @param string $discountKey
	 * @param string $billrunKey
	 * @return discount object if found, false otherwise
	 */
	public static function getDiscount($discountKey, $billrunKey) {
		if (isset(self::$discounts[$billrunKey][$discountKey])) {
			return self::$discounts[$billrunKey][$discountKey];
		}

		if (isset(self::$subscribersDiscounts[$billrunKey][$discountKey])) {
			return self::$subscribersDiscounts[$billrunKey][$discountKey];
		}
		
		return false;
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
                'eligibility' => [
					[
						'from' => $discountFrom,
						'to' => $discountTo,
					],
                ],
            ];
        }
		
		$minSubscribers = Billrun_Util::getIn($discount, 'params.min_subscribers', 1);
		$maxSubscribers = Billrun_Util::getIn($discount, 'params.max_subscribers', null);
		$eligibility = [];
		$servicesEligibility = [];
		
		if (count($subscribersRevisions) < $minSubscribers) { // skip conditions check if there are not enough subscribers
			return false;
		}
		
		foreach ($conditions as $condition) { // OR logic
			$conditionEligibility = $this->getConditionEligibility($condition, $accountRevisions, $subscribersRevisions, $minSubscribers, $maxSubscribers);
			
			if (empty($conditionEligibility) || empty($conditionEligibility['eligibility'])) {
				continue;
			}
			
			$eligibility = array_merge($eligibility, $conditionEligibility['eligibility']);
			
			foreach ($conditionEligibility['services'] as $sid => $subServicesEligibility) {
				if (isset($servicesEligibility[$sid])) {
					$servicesEligibility[$sid] = array_merge($servicesEligibility[$sid], $subServicesEligibility);
				} else {
					$servicesEligibility[$sid] = $subServicesEligibility;
				}
			}
		}
		
		$eligibility = $this->getFinalEligibility($eligibility, $discountFrom, $discountTo);
		
		foreach ($servicesEligibility as &$subServicesEligibility) {
			foreach ($subServicesEligibility as &$subServiceEligibility) {
				$subServiceEligibility = $this->getFinalEligibility($subServiceEligibility, $discountFrom, $discountTo);
			}
		}
		
		return [
			'eligibility' => $eligibility,
			'services' => $servicesEligibility,
		];
	}
	
	/**
	 * fix eligibility to be best represents by intervals + align from/to according to discount's from/to
	 * 
	 * @param array $eligibility
	 * @param unixtimestamp $discountFrom
	 * @param unixtimestamp $discountTo
	 * @return array
	 */
	protected function getFinalEligibility($eligibility, $discountFrom, $discountTo) {
		$finalEligibility = Billrun_Utils_Time::mergeTimeIntervals($eligibility);
		
		foreach ($finalEligibility as $i => &$eligibilityInterval) {
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
		
		return $finalEligibility;
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
		$servicesEligibility = [];
		
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
		$subscribersServicesConditions = Billrun_Util::getIn($condition, 'subscriber.0.service.any', []); // currently supports 1 condtion's type

		foreach ($subscribersRevisions as $subscriberRevisions) {
			$sid = $subscriberRevisions[0]['sid'];
			if (empty($subscribersConditions)) {
				$subsEligibility[$sid] = [
					$this->getAllCycleInterval(),
				];
			} else {			
				$subEligibility = $this->getConditionsEligibilityForEntity($subscribersConditions, $subscriberRevisions);
				if (empty($subEligibility)) {
					continue; // if the current subscriber does not match, check other subscribers
				}
				
				if (!empty($subscribersServicesConditions)) {
					$subServicesEligibility = $this->getServicesEligibility($subscribersServicesConditions, $subscriberRevisions);
					$servicesEligibilityIntervals = Billrun_Util::getIn($subServicesEligibility, 'eligibility', []);
					if (empty($servicesEligibilityIntervals)) {
						continue; // if the current subscriber's services does not match, check other subscribers
					}

					$subEligibility = Billrun_Utils_Time::getIntervalsIntersections($subEligibility, $servicesEligibilityIntervals); // reduce subscriber eligibility to services eligibility intersection
					$servicesEligibility[$sid] = Billrun_Util::getIn($subServicesEligibility, 'services', []);
				}
				
				$subsEligibility[$sid] = Billrun_Utils_Time::mergeTimeIntervals($subEligibility);
			}
		}
		
		$totalEligibility = [];
		$eligibilityBySubs = [];
		
		// goes only over accout's eligibility because it must met
		foreach ($accountEligibility as $accountEligibilityInterval) {
			// check eligibility day by day
			for ($day = $accountEligibilityInterval['from']; $day <= $accountEligibilityInterval['to']; $day = strtotime('+1 day', $day)) {
				$eligibleSubsInDay = [];
				$dayFrom = strtotime('midnight', $day);
				$dayTo = strtotime('+1 day', $dayFrom);
				foreach ($subsEligibility as $sid => $subEligibility) {
					foreach ($subEligibility as $subEligibilityIntervals) {
						if ($subEligibilityIntervals['from'] <= $day && $subEligibilityIntervals['to'] > $day) {
							$eligibleSubsInDay[] = $sid;
							
							if (!is_null($maxSubscribers) && count($eligibleSubsInDay) > $maxSubscribers) { // passed max subscribers in current day
								continue 3; // check next day
							}
							
							continue 2; // check next subscriber
						}
						
						if ($subEligibilityIntervals['from'] > $day) {
							continue 2; // intervals are sorted, check next subscriber
						}
					}
				}
				
				if (count($eligibleSubsInDay) >= $minSubscribers) { // account is eligible for the discount in current day
					$totalEligibility[] = [
						'from' => $dayFrom,
						'to' => $dayTo,
					];
					
					foreach ($eligibleSubsInDay as $eligibleSubInDay) {
						if (empty($eligibilityBySubs[$eligibleSubInDay])) {
							$eligibilityBySubs[$eligibleSubInDay] = [];
						}
						$eligibilityBySubs[$eligibleSubInDay][] = [
							'from' => $dayFrom,
							'to' => $dayTo,
						];
					}
				}
			}
		}
		
		foreach ($servicesEligibility as $sid => &$subServicesEligibility) {
			foreach ($subServicesEligibility as $service => &$serviceEligibility) {
				$serviceEligibility = Billrun_Utils_Time::getIntervalsIntersections($serviceEligibility, $eligibilityBySubs[$sid]);
			}
		}
		
		return [
			'eligibility' => Billrun_Utils_Time::mergeTimeIntervals($totalEligibility),
			'services' => $servicesEligibility,
		];
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
	 * get array of intervals on which the entity meets the conditions
	 * 
	 * @param array $conditions
	 * @param array $entityRevisions
	 * @return array of intervals
	 */
	protected function getServicesEligibility($conditions, $subscriberRevisions) {
		$eligibility = null;
		$servicesEligibility = [];
		
		foreach ($conditions as $condition) { // AND logic
			$conditionEligibility = [];
			$conditionFields = Billrun_Util::getIn($condition, 'fields', []);
			foreach ($subscriberRevisions as $subscriberRevision) { // OR logic
				foreach (Billrun_Util::getIn($subscriberRevision, 'services', []) as $subscriberService) { // OR logic
					$serviceFrom = max($subscriberRevision['from']->sec, $subscriberService['from']->sec);
					$serviceTo = min($subscriberRevision['to']->sec, $subscriberService['to']->sec);
					if ($this->isConditionsMeet($subscriberService, $conditionFields)) {
						$conditionEligibility[] = [
							'from' => $serviceFrom,
							'to' => $serviceTo,
						];
						if (empty($servicesEligibility[$subscriberService['key']])) {
							$servicesEligibility[$subscriberService['key']] = [];
						}
						$servicesEligibility[$subscriberService['key']][] = [
							'from' => $serviceFrom,
							'to' => $serviceTo,
						];
					}
				}
			}
			
			if (empty($conditionEligibility)) { // one of the conditions does not meet
				return [
					'eligibility' => [],
					'services' => [],
				];
			}
			
			if (is_null($eligibility)) { // empty is not good enough because intersection might cause empty array
				$eligibility = $conditionEligibility;
			} else {
				$eligibility = Billrun_Utils_Time::getIntervalsIntersections($eligibility, $conditionEligibility);
			}
		}
		
		$eligibility = Billrun_Utils_Time::mergeTimeIntervals($eligibility);
		
		foreach ($servicesEligibility as &$serviceEligibility) {
			$serviceEligibility = Billrun_Utils_Time::getIntervalsIntersections($eligibility, $serviceEligibility);
			$serviceEligibility = Billrun_Utils_Time::mergeTimeIntervals($serviceEligibility);
		}
		
		return [
			'eligibility' => $eligibility,
			'services' => $servicesEligibility,
		];
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

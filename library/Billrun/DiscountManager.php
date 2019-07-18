<?php

/**
 * Discount management
 */
class Billrun_DiscountManager {
	use Billrun_Traits_ConditionsCheck;

    protected $cycle = null;
	protected $eligibleDiscounts = [];
	
	protected static $discounts = [];
	protected $subscribersDiscounts = [];
	protected static $discountsDateRangeFields = [];
	
	public function __construct($accountRevisions, $subscribersRevisions = [], Billrun_DataTypes_CycleTime $cycle, $params = []) {
		$this->cycle = $cycle;
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
		$dateRangeDiscoutnsFields = self::getDiscountsDateRangeFields($this->cycle->key(), $type);
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
		
		foreach (self::getDiscounts($this->cycle->key()) as $discount) {
			$eligibility = $this->getDiscountEligibility($discount, $accountRevisions, $subscribersRevisions);
			$this->setDiscountEligibility($discount, $eligibility);
		}
		
		// handle subscribers' level revisions
		foreach ($subscribersRevisions as $subscriberRevisions) {
			foreach ($subscriberRevisions as $subscriberRevision) {
				$subDiscounts = Billrun_Util::getIn($subscriberRevision, 'discounts', []);
				foreach ($subDiscounts as $subDiscount) {
					$eligibility = $this->getDiscountEligibility($subDiscount, $accountRevisions, [$subscriberRevisions]);
					$this->setDiscountEligibility($subDiscount, $eligibility);
					$this->setSubscriberDiscount($subDiscount, $this->cycle->key());
				}
			}
		}
		
		$this->handleConflictingDiscounts();
	}
	
	/**
	 * set eligibility for discount
	 * 
	 * @param array $discount
	 * @param array $eligibility
	 */
	protected function setDiscountEligibility($discount, $eligibility) {
		if (empty(Billrun_Util::getIn($eligibility, 'eligibility', []))) {
			return;
		}
		
		$discountKey = $discount['key'];
		if (isset($this->eligibleDiscounts[$discountKey])) {
			$timeEligibility = Billrun_Utils_Time::mergeTimeIntervals(array_merge($this->eligibleDiscounts[$discountKey]['eligibility']), Billrun_Util::getIn($eligibility, 'eligiblity', []));

			$servicesEligibility = $this->eligibleDiscounts[$discountKey]['services'];
			foreach (Billrun_Util::getIn($eligibility, 'services', []) as $serviceEligibility) {
				foreach ($serviceEligibility as $sid => $subServiceEligibility) {
					foreach ($subServiceEligibility as $serviceKey => $currServiceEligibility) {
						$serviceNewEligibility = Billrun_Utils_Time::mergeTimeIntervals(array_merge(Billrun_Util::getIn($this->eligibleDiscounts, [$discountKey, 'services', $sid, $serviceKey], []), $currServiceEligibility));
						Billrun_Util::setIn($servicesEligibility, ['services', $sid, $serviceKey], $serviceNewEligibility);
					}
				}
			}

			$plansEligibility = $this->eligibleDiscounts[$discountKey]['plans'];
			foreach (Billrun_Util::getIn($eligibility, 'plans', []) as $plansEligibility) {
				foreach ($plansEligibility as $sid => $subPlanEligibility) {
					foreach ($subPlanEligibility as $planKey => $currPlanEligibility) {
						$planNewEligibility = Billrun_Utils_Time::mergeTimeIntervals(array_merge(Billrun_Util::getIn($this->eligibleDiscounts, [$discountKey, 'plans', $sid, $planKey], []), $currPlanEligibility));
						Billrun_Util::setIn($plansEligibility, ['plans', $sid, $planKey], $planNewEligibility);
					}
				}
			}
			
			$eligibility = [
				'eligibility' => $timeEligibility,
				'services' => $servicesEligibility,
				'plans' => $plansEligibility,
			];
		}
		
		$this->eligibleDiscounts[$discountKey] = $eligibility;
	}
	
	/**
	 * fix conflicting caused by discounts with lower priority
	 */
	protected function handleConflictingDiscounts() {
		foreach ($this->eligibleDiscounts as $eligibleDiscount => $eligibilityData) {
			$discount = $this->getDiscount($eligibleDiscount, $this->cycle->key());
			foreach (Billrun_Util::getIn($discount, 'excludes', []) as $discountToExclude) {
				if (isset($this->eligibleDiscounts[$discountToExclude])) {
					$this->eligibleDiscounts[$discountToExclude]['eligibility'] = Billrun_Utils_Time::getIntervalsDifference($this->eligibleDiscounts[$discountToExclude]['eligibility'], $eligibilityData['eligibility']);
					if (empty($this->eligibleDiscounts[$discountToExclude]['eligibility'])) {
						unset($this->eligibleDiscounts[$discountToExclude]);
					}
					
					foreach ($this->eligibleDiscounts[$discountToExclude]['services'] as $sid => $services) {
						foreach ($services as $serviceKey => $serviceEligibility) {
							$this->eligibleDiscounts[$discountToExclude]['services'][$sid][$serviceKey] = Billrun_Utils_Time::getIntervalsDifference($serviceEligibility, $eligibilityData['eligibility']);
							if (empty($this->eligibleDiscounts[$discountToExclude]['services'][$sid][$serviceKey])) {
								unset($this->eligibleDiscounts[$discountToExclude]['services'][$sid][$serviceKey]);
							}
						}
						
						if (empty($this->eligibleDiscounts[$discountToExclude]['services'][$sid])) {
							unset($this->eligibleDiscounts[$discountToExclude]['services'][$sid]);
						}
					}
				}
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
		self::$discounts[$billrunKey] = [];
		usort($discounts, function ($a, $b) {
			return Billrun_Util::getIn($b, 'priority', 0) > Billrun_Util::getIn($a, 'priority', 0);
		});
		
		foreach ($discounts as $discount) {
			self::$discounts[$billrunKey][$discount['key']] = $discount;
		}
	}
	
	/**
	 * manually set subscriber discount
	 * 
	 * @param array $discount
	 * @param string $billrunKey
	 */
	protected function setSubscriberDiscount($discount, $billrunKey) {
		$this->subscribersDiscounts[$billrunKey][$discount['key']] = $discount;
	}
	
	/**
	 * get discount object by key
	 * 
	 * @param string $discountKey
	 * @param string $billrunKey
	 * @return discount object if found, false otherwise
	 */
	public function getDiscount($discountKey, $billrunKey) {
		if (isset(self::$discounts[$billrunKey][$discountKey])) {
			return self::$discounts[$billrunKey][$discountKey];
		}

		if (isset($this->subscribersDiscounts[$billrunKey][$discountKey])) {
			return $this->subscribersDiscounts[$billrunKey][$discountKey];
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
							if (in_array($field['value'], ['active', 'notActive'])) {
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
        $discountFrom = max($discount['from']->sec, $this->cycle->start());
        $discountTo = min($discount['to']->sec, $this->cycle->end());
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
		$cycles = Billrun_Util::getIn($discount, 'params.cycles', null);
		$eligibility = [];
		$servicesEligibility = [];
		$plansEligibility = [];
		
		if (count($subscribersRevisions) < $minSubscribers) { // skip conditions check if there are not enough subscribers
			return false;
		}
		
		$params = [
			'min_subscribers' => $minSubscribers,
			'max_subscribers' => $maxSubscribers,
			'cycles' => $cycles,
		];
		
		foreach ($conditions as $condition) { // OR logic
			$conditionEligibility = $this->getConditionEligibility($condition, $accountRevisions, $subscribersRevisions, $params);
			
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
			
			foreach ($conditionEligibility['plans'] as $sid => $subPlansEligibility) {
				if (isset($plansEligibility[$sid])) {
					$plansEligibility[$sid] = array_merge($plansEligibility[$sid], $subPlansEligibility);
				} else {
					$plansEligibility[$sid] = $subPlansEligibility;
				}
			}
		}
		
		$eligibility = $this->getFinalEligibility($eligibility, $discountFrom, $discountTo);
		
		foreach ($servicesEligibility as &$subServicesEligibility) {
			foreach ($subServicesEligibility as &$subServiceEligibility) {
				$subServiceEligibility = $this->getFinalEligibility($subServiceEligibility, $discountFrom, $discountTo);
			}
		}
		
		foreach ($plansEligibility as &$subPlansEligibility) {
			foreach ($subPlansEligibility as &$subPlanEligibility) {
				$subPlanEligibility = $this->getFinalEligibility($subPlanEligibility, $discountFrom, $discountTo);
			}
		}
		
		return [
			'eligibility' => $eligibility,
			'services' => $servicesEligibility,
			'plans' => $plansEligibility,
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
	 * @param array $params
	 * @return array of intervals
	 */
	protected function getConditionEligibility($condition, $accountRevisions, $subscribersRevisions = [], $params = []) {
		$accountEligibility = [];
		$subsEligibility = [];
		$servicesEligibility = [];
		$plansEligibility = [];
		$minSubscribers = $params['min_subscribers'] ?? 1;
		$maxSubscribers = $params['max_subscribers'] ?? null;
		$cycles = $params['cycles'] ?? null;
		
		$accountConditions = Billrun_Util::getIn($condition, 'account.fields', []);
		
		if (empty($accountConditions)) {
			$accountEligibility[] = $this->getAllCycleInterval();
		} else {
			$accountEligibility = $this->getAccountEligibility($accountConditions, $accountRevisions);
			if (empty($accountEligibility)) {
				return false; // account conditions must match
			}
			$accountEligibility = Billrun_Utils_Time::mergeTimeIntervals($accountEligibility);
		}
		
		$subscribersConditions = Billrun_Util::getIn($condition, 'subscriber.0.fields', []); // currently supports 1 condtion's type
		$subscribersServicesConditions = Billrun_Util::getIn($condition, 'subscriber.0.service.any', []); // currently supports 1 condtion's type
		$hasPlanConditions = $this->hasPlanCondition($subscribersConditions);
		$hasServiceConditions = $this->hasServicesCondition($subscribersServicesConditions);

		foreach ($subscribersRevisions as $subscriberRevisions) {
			$sid = $subscriberRevisions[0]['sid'];
			if (empty($subscribersConditions) && empty($subscribersServicesConditions)) {
				$subsEligibility[$sid] = [
					$this->getAllCycleInterval(),
				];
			} else {
				$subCycles = $hasServiceConditions ? null : $cycles; // in case of services conditions, will check as part of services eligibility
				$subEligibilityRet = $this->getSubscriberEligibility($subscribersConditions, $subscriberRevisions, $subCycles);
				$subEligibility = $subEligibilityRet['eligibility'];
				if (empty($subEligibility)) {
					continue; // if the current subscriber does not match, check other subscribers
				}
				
				$subPlansEligibility = Billrun_Util::getIn($subEligibilityRet, 'plans', []);
				if (!empty($subPlansEligibility)) {
					$plansEligibility[$sid] = $subPlansEligibility;
				}
				
				if (!empty($subscribersServicesConditions)) {
					$subServicesEligibility = $this->getServicesEligibility($subscribersServicesConditions, $subscriberRevisions, $hasPlanConditions, $cycles);
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
			for ($day = $accountEligibilityInterval['from']; $day < $accountEligibilityInterval['to']; $day = strtotime('+1 day', $day)) {
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
		
		foreach ($plansEligibility as $sid => &$subPlansEligibility) {
			foreach ($subPlansEligibility as $plan => &$planEligibility) {
				$planEligibility = Billrun_Utils_Time::getIntervalsIntersections($planEligibility, $eligibilityBySubs[$sid]);
			}
		}
		
		return [
			'eligibility' => Billrun_Utils_Time::mergeTimeIntervals($totalEligibility),
			'services' => $servicesEligibility,
			'plans' => $plansEligibility,
		];
	}

	/**
	 * get array of intervals on which the account meets the conditions
	 * 
	 * @param array $conditions
	 * @param array $entityRevisions
	 * @return array of intervals
	 */
	protected function getAccountEligibility($conditions, $accountRevisions) {
		$eligibility = [];
		foreach ($accountRevisions as $accountRevision) {
			$from = $accountRevision['from']->sec;
			$to = $accountRevision['to']->sec;
			
			if ($this->isConditionsMeet($accountRevision, $conditions)) {
				if ($from < $to) {				
					$eligibility[] = [
						'from' => $from,
						'to' => $to,
					];
				}
			}
				
		}
		
		return $eligibility;
	}

	/**
	 * get array of intervals on which the entity meets the conditions
	 * 
	 * @param array $conditions
	 * @param array $entityRevisions
	 * @param int $cycles
	 * @return array of intervals
	 */
	protected function getSubscriberEligibility($conditions, $subscriberRevisions, $cycles = null) {
		$eligibility = [];
		$plansEligibility = [];
		$hasPlansConditions = $this->hasPlanCondition($conditions);
		
		foreach ($subscriberRevisions as $subscriberRevision) {
			$cyclesEligibilityEnd = !is_null($cycles) ? strtotime("+{$cycles} months", $subscriberRevision['plan_activation']->sec) : null;
			
			$from = $subscriberRevision['from']->sec;
			$to = $subscriberRevision['to']->sec;
			
			if (!is_null($cyclesEligibilityEnd) && $cyclesEligibilityEnd <= $from) {
				continue;
			}
			
			if ($this->isConditionsMeet($subscriberRevision, $conditions)) {
				if (!is_null($cyclesEligibilityEnd) && $cyclesEligibilityEnd < $to) {
					$to = $cyclesEligibilityEnd;
				}
				
				if ($from < $to) {				
					$eligibility[] = [
						'from' => $from,
						'to' => $to,
					];
					
					if ($hasPlansConditions) {
						$plan = $subscriberRevision['plan'];
						if (empty($plansEligibility[$plan])) {
							$plansEligibility[$plan] = [];
						}
						$plansEligibility[$plan][] = [
							'from' => $from,
							'to' => $to,
						];
					}
				}
			}
				
		}
		
		return [
			'eligibility' => $eligibility,
			'plans' => $plansEligibility,
		];
	}

	/**
	 * get array of intervals on which the entity meets the conditions
	 * 
	 * @param array $conditions
	 * @param array $subscriberRevisions
	 * @param bool $hasPlanConditions
	 * @param int $cycles
	 * @return array of intervals
	 */
	protected function getServicesEligibility($conditions, $subscriberRevisions, $hasPlanConditions = false, $cycles = null) {
		$eligibility = null;
		$servicesEligibility = [];
		
		foreach ($conditions as $condition) { // AND logic
			$conditionEligibility = [];
			$conditionFields = Billrun_Util::getIn($condition, 'fields', []);
			foreach ($subscriberRevisions as $subscriberRevision) { // OR logic
				if (empty($conditionFields)) {
					$conditionEligibility[] = [
						'from' => $subscriberRevision['from']->sec,
						'to' => $subscriberRevision['to']->sec,
					];
					continue;
				}
				if ($hasPlanConditions && !is_null($cycles)) {
					$planEligibilityEnd = strtotime("+{$cycles} months", $subscriberRevision['plan_activation']->sec);
				} else {
					$planEligibilityEnd = null;
				}
				
				foreach (Billrun_Util::getIn($subscriberRevision, 'services', []) as $subscriberService) { // OR logic
					$serviceFrom = max($subscriberRevision['from']->sec, $subscriberService['from']->sec);
					$serviceTo = min($subscriberRevision['to']->sec, $subscriberService['to']->sec);
					if (!is_null($cycles)) {
						$serviceEligibilityEnd = strtotime("+{$cycles} months", $subscriberService['service_activation']->sec);
						if (!is_null($planEligibilityEnd)) {	
							$serviceEligibilityEnd = max($planEligibilityEnd, $serviceEligibilityEnd);
						}
						
						if ($serviceEligibilityEnd < $serviceFrom) {
							continue 2;
						}
						
						if ($serviceEligibilityEnd < $serviceTo) {
							$serviceTo = $serviceEligibilityEnd;
						}
					}
					if ($this->isConditionsMeet($subscriberService, $conditionFields)) {
						if ($serviceFrom < $serviceTo) {
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
	 * checks if conditions set has a condition on plan
	 * 
	 * @param array $conditions
	 * @return boolean
	 */
	protected function hasPlanCondition($conditions) {
		foreach ($conditions as $condition) {
			if (in_array($condition['field'], ['plan', 'plan_activation', 'plan_deactivation'])) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * checks if conditions set has a condition on service
	 * 
	 * @param array $conditions
	 * @return boolean
	 */
	protected function hasServicesCondition($conditions) {
		foreach ($conditions as $condition) {
			if (!empty(Billrun_Util::getIn($condition, 'fields', []))) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * gets intervals covers entire cycle
	 * 
	 * @return array
	 */
	protected function getAllCycleInterval() {
		return [
			'from' => $this->cycle->start(),
			'to' => $this->cycle->end(),
		];
	}
	
	/**
	 * see Billrun_Traits_ValueTranslator::getTranslationMapping
	 */
	public function getTranslationMapping($params = []) {
		return [
			'@cycle_end_date@' => [
				'hard_coded' => $this->cycle->end(),
			],
			'@cycle_start_date@' => [
				'hard_coded' => $this->cycle->start(),
			],
			'@plan_activation@' => [
				'field' => 'plan_activation',
				'format' => [
					'date' => 'unixtimestamp',
				]
			],
			'@plan_deactivation@' => [
				'field' => 'plan_deactivation',
				'format' => [
					'date' => 'unixtimestamp',
				]
			],
			'@activation_date@' => [
				'field' => 'activation_date',
				'format' => [
					'date' => 'unixtimestamp',
				]
			],
			'@deactivation_date@' => [
				'field' => 'deactivation_date',
				'format' => [
					'date' => 'unixtimestamp',
				]
			],
		];
	}

}

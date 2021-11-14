<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Customer Portal subscriber actions
 * 
 * @package  Billing
 * @since    5.14
 */
class Portal_Actions_Subscriber extends Portal_Actions {
        
    /**
     * get subscriber by given query
	 * using BillApi
	 *
     * @param  array $params
     * @return array
     */
    public function get($params = []) {
		$query = $params['query'] ?? [];

		if ($this->loginLevel !== self::LOGIN_LEVEL_SUBSCRIBER && empty($query)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "query"');
		}

		if ($this->loginLevel === self::LOGIN_LEVEL_SUBSCRIBER) {
			$query['sid'] = $this->loggedInEntity['sid'];
			$query['aid'] = $this->loggedInEntity['aid'];
		} else if ($this->loginLevel === self::LOGIN_LEVEL_ACCOUNT) {		
			$query['aid'] = $this->loggedInEntity['aid'];
		}
		
		$billapiParams = $this->getBillApiParams('subscribers', 'uniqueget', $query);
		$res = $this->runBillApi($billapiParams);
		if ($res === false) {
			throw new Portal_Exception('subscriber_get_failure');
		}
		
		return $this->getDetails(current($res));
    }

	/**
     * update subscriber by given query and update
	 * using BillApi
     *
     * @param  array $params
     * @return array subscriber updated details
     */
    public function update($params = []) {
		$query = $params['query'] ?? [];

		if ($this->loginLevel !== self::LOGIN_LEVEL_SUBSCRIBER && empty($query)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "query"');
		}

		$query['type'] = 'subscriber';
		if ($this->loginLevel === self::LOGIN_LEVEL_SUBSCRIBER) {
			$query['sid'] = $this->loggedInEntity['sid'];
			$query['aid'] = $this->loggedInEntity['aid'];
		} else if ($this->loginLevel === self::LOGIN_LEVEL_ACCOUNT) {		
			$query['aid'] = $this->loggedInEntity['aid'];
		}

		if (empty($query['effective_date'])) {
			$query['effective_date'] = date(self::DATETIME_FORMAT);
		}
		
		$update = $params['update'] ?? [];
		if (empty($update)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "update"');
		}

		if (empty($update['from'])) {
			$update['from'] = $query['effective_date'];
		}
		
		$billapiParams = $this->getBillapiParams('subscribers', 'permanentchange', $query, $update);
		$res = $this->runBillApi($billapiParams);
		
		if ($res === false) {
			throw new Portal_Exception('subscriber_update_failure');
		}

		$subscriber = $this->get($params);
		return $this->getDetails($subscriber, $params);
	}
	
	/**
	 * format subscriber details to return
	 *
	 * @param  array $subscriber
	 * @param  array $params
	 * @return array
	 */
	protected function getDetails($subscriber, $params = []) {
		$subscriber = parent::getDetails($subscriber);
		if ($subscriber === false ) {
			return false;
		}
                $this->addServicesDetails($subscriber, $params);

		
		unset($subscriber['_id']);
		return $subscriber;
	}
        
        
        /**
	 * add to subscriber services details (service include + service used + service left)
	 *
	 * @param  array $subscriber
	 * @param  array $params
	 */
        protected function addServicesDetails(&$subscriber, $params) {
            $services = $subscriber['services'] ?? [];
            foreach ($services as $index => $service) {
                $service = new Billrun_Service(['name' => $subscriber['services'][$index]['name'], 'time'=> strtotime($subscriber['services'][$index]['from'])]);
		$subscriber['services'][$index]['include'] =  $service->get('include');
                $includeUsages = $params['include_usages'] ?? true;
		if ($includeUsages) {
//			$subscriber['services'][$index]['used'] = 
//                      $subscriber['services'][$index]['left'] = 
		}
            }
        }
	
	/**
	 * get subscriber aggregated usages
	 *
	 * @param  mixed $subscriber
	 * @return array
	 */
	protected function getAggregatedUsages($subscriber) {
		$endOfCycle = date(DATE_ISO8601, (int) Billrun_Billingcycle::getEndTime(Billrun_Billingcycle::getBillrunKeyByTimestamp()));
		$totals = $this->getTotalUsages($subscriber);
		$used = [];
		$balances = $this->getBalances($subscriber);

		foreach ($balances as $balance) {
            foreach ($balance['balance']['totals'] as $usageType => $values) {
				$balanceValidity = isset($balance['to']) ? $balance['to'] : $endOfCycle;
				if (!isset($used[$usageType])) {
					$used[$usageType] = [
						'usage' => 0,
						'validity' => $balanceValidity,
					];
				}

				$used[$usageType]['usage'] += $values['usagev'] ?? 0;
				$used[$usageType]['validity'] = min($used[$usageType]['validity'], $balanceValidity);
            }
        }
		
		$ret = [];
		foreach ($totals as $usageType => $total) {
			$ret[$usageType] = [
				'used' => $used[$usageType]['usage'] ?? 0,
				'total' => $total,
				'validity' => $used[$usageType]['validity'] ?? $endOfCycle,
			];
		}

		return $ret;
	}
	
	/**
	 * get subscriber active balances
	 *
	 * @param  mixed $subscriber
	 * @return array
	 */
	protected function getBalances($subscriber) {
		$time = date(DATE_ISO8601);
		$query = [
			'aid' => $subscriber['aid'],
			'sid' => $subscriber['sid'],
			'from' => [
				'$lte' => $time,
			],
			'to' => [
				'$gt' => $time,
			],
		];

		$params = $this->getBillApiParams('balances', 'get', $query);
		$balances = $this->runBillApi($params);
		return $balances ?? [];
	}
	
	/**
	 * get subscriber's total usages
	 * takes into account plan included services and subscriber's related services
	 *
	 * @param  mixed $subscriber
	 * @return array
	 */
	protected function getTotalUsages($subscriber) {
		$totals = [];
		$services = $subscriber['services'] ?? [];
		$plan = new Billrun_Plan(['name' => $subscriber['plan'], 'time' => time()]);
		if ($plan) {
			$cycleStart = (int) Billrun_Billingcycle::getStartTime(Billrun_Billingcycle::getBillrunKeyByTimestamp());
			foreach ($plan->get('include')['services'] ?? [] as $includedService) {
				$services[] = [
					'name' => $includedService,
					'from' => $cycleStart,
				];
			}
		}

		foreach ($services as $serviceData) {
			$service = new Billrun_Service(['name' => $serviceData['name'], 'time' => time()]);
			if (!$service) {
				Billrun_Factory::log("Cannot get service ${$serviceData['name']} for subscriber {$subscriber['sid']}", Billrun_Log::ERR);
				continue;
			}

			if ($service->isExhausted(Billrun_Utils_Time::getTime($serviceData['from']))) {
				continue;
			}
			
			foreach ($service->get('include')['groups'] ?? [] as $group) {
				foreach (array_keys($group['usage_types'] ?? []) as $usageType) {
					if (!isset($totals[$usageType])) {
						$totals[$usageType] = 0;
					}
					
					$totals[$usageType] += $group['value'] ?? 0;
				}
			}
		}

		return $totals;
	}
	
	/**
	 * get subscriber usages (lines) 
	 *
	 * @param  array $params
	 * @return array
	 */
	public function usages($params = []) {
		$query = $params['query'] ?? [];

		if ($this->loginLevel !== self::LOGIN_LEVEL_SUBSCRIBER && empty($query) || empty($query['sid'])) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "query"');
		}

		if ($this->loginLevel === self::LOGIN_LEVEL_SUBSCRIBER) {
			$query['sid'] = $this->loggedInEntity['sid'];
			$query['aid'] = $this->loggedInEntity['aid'];
		} else if ($this->loginLevel === self::LOGIN_LEVEL_ACCOUNT) {		
			$query['aid'] = $this->loggedInEntity['aid'];
		}
		
		$billapiParams = $this->getBillApiParams('lines', 'get', $query);
		return $this->runBillApi($billapiParams);
	}

	/**
	 * Authorize the request.
	 *
	 * @param  string $action
	 * @param  array $params
	 * @return boolean
	 */
    protected function authorize($action, &$params = []) {
		if (!parent::authorize($action, $params)) {
			return false;
		}

		return in_array($this->loginLevel, [self::LOGIN_LEVEL_ACCOUNT, self::LOGIN_LEVEL_SUBSCRIBER]);
	}

}

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

	use Billrun_Traits_Api_Pagination;

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
		if ($subscriber === false) {
			return false;
		}
		$this->addPlanDetails($subscriber, $params);
		$this->addServicesDetails($subscriber, $params);
		$this->addOutGroupUsage($subscriber, $params);
		unset($subscriber['_id']);
		return $subscriber;
	}

	/**
	 * add plan details to subscriber
	 *
	 * @param  array $subscriber
	 * @param  array $params
	 */
	protected function addPlanDetails(&$subscriber, $params) {
		$plan = Billrun_Factory::plan(['name' => $subscriber['plan'], 'time' => strtotime(date('Y-m-d H:00:00'))]);
		$subscriber['plan_description'] = $plan->get('description');
		$servicesIncludeInPlan = $plan->get('include')['services'] ?? [];
		foreach ($servicesIncludeInPlan as $index => $serviceIncludeInPlan) {
			$subscriber['include']['services'][$index]['name'] = $serviceIncludeInPlan;
		}
	}

	/**
	 * add services details to subscriber
	 *
	 * @param  array $subscriber
	 * @param  array $params
	 */
	protected function addServicesDetails(&$subscriber, $params) {
		if (isset($subscriber['services'])) {
			foreach ($subscriber['services'] as &$subscriberService) {
				$this->addServiceDetails($subscriberService, $params, $subscriber);
			}
		}
		if (isset($subscriber['include']['services'])) {
			foreach ($subscriber['include']['services'] as &$subscriberService) {
				$this->addServiceDetails($subscriberService, $params, $subscriber);
			}
		}
	}
	
	/**
	 * add balance out\over group usage to subscriber
	 *
	 * @param  array $subscriber
	 * @param  array $params
	 */
	protected function addOutGroupUsage(&$subscriber, $params) {
		$balances = $this->getBalances($subscriber);
		if (!empty($balances)) {
			foreach ($balances as $balance) {
				$balance_totals = Billrun_Util::getIn($balance, 'balance.totals', []);
				foreach ($balance_totals as $usaget => $balance_total) {
					if(!empty($balance_total['out_group']) || !empty($balance_total['over_group'])) {
						$subscriber['out_group'][$usaget] = $balance_total;
					}
				}
			}
		}
	}

	/**
	 * Add service groups usages 
	 * @param type $service
	 */
	protected function addServiceGroupsUsages(&$service, $subscriber) {
		$balances = $this->getBalances($subscriber);
		if (isset($service['include']['groups'])) {
			foreach ($service['include']['groups'] as $serviceGroupName => &$serviceGroup) {
				foreach ($balances as $balance) {
					if (isset($balance['balance']['groups'][$serviceGroupName])) {
						$serviceGroup['usage']['used'] = $balance['balance']['groups'][$serviceGroupName]['usagev'];
						$serviceGroup['usage']['total'] = (isset($serviceGroup['value']) && $serviceGroup['value'] == 'UNLIMITED') ? 'UNLIMITED' : $balance['balance']['groups'][$serviceGroupName]['total'];
						break;
					}
				}
				if (!isset($serviceGroup['usage']['used'])) {
					$serviceGroup['usage']['used'] = 0;
				}
				if (!isset($serviceGroup['usage']['total'])) {
					if (isset($serviceGroup['value'])) {
						$serviceGroup['usage']['total'] = $serviceGroup['value'];
					} else {
						//TODO:: support Monetary based (cost)
						unset($serviceGroup['usage']['used']);
						$serviceGroup['usage']['display'] = false;
					}
				}
			}
		}
	}

	/**
	 * add service details to subscriber
	 * @param array $subscriberServices - the services we will add the details
	 * @param array $params
	 */
	protected function addServiceDetails(&$subscriberService, $params, $subscriber) {
		$service = Billrun_Factory::service(['name' => $subscriberService['name'], 'time' => strtotime(date('Y-m-d H:00:00'))]);
		$subscriberService['description'] = $service->get('description');
		$include = $service->get('include');
		if (isset($include)) {
			$subscriberService['include'] = $include;
		}
		$includeUsages = $params['include_usages'] ?? true;
		if ($includeUsages) {
			$this->addServiceGroupsUsages($subscriberService, $subscriber);
		}
	}

	/**
	 * get subscriber active balances
	 *
	 * @return array
	 */
	protected function getBalances($subscriber) {
		$time = date(DATE_ISO8601);
		$query = [
			'aid' => $this->loggedInEntity['aid'],
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
	 * get subscriber usages (lines) 
	 *
	 * @param  array $params
	 * @return array
	 */
	public function usages($params = []) {
		$query = $params['query'] ?? [];
		$page = $params['page'] ?? -1;
		$size = $params['size'] ?? -1;

		if ($this->loginLevel !== self::LOGIN_LEVEL_SUBSCRIBER && empty($query) || empty($query['sid'])) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "query"');
		}

		if ($this->loginLevel === self::LOGIN_LEVEL_SUBSCRIBER) {
			$query['sid'] = $this->loggedInEntity['sid'];
			$query['aid'] = $this->loggedInEntity['aid'];
		} else if ($this->loginLevel === self::LOGIN_LEVEL_ACCOUNT) {
			$query['aid'] = $this->loggedInEntity['aid'];
		}
		$usages_months_limit = isset($this->params['usages_months_limit']) &&
				is_integer($this->params['usages_months_limit']) &&
				intval($this->params['usages_months_limit']) > 0 ? $this->params['usages_months_limit'] : 24;

		if (!isset($query['urt']['$gte']) || strtotime($usages_months_limit . " months ago") > strtotime($query['urt']['$gte'])) {
			$query['urt']['$gte'] = new Mongodloid_Date(strtotime($usages_months_limit . " months ago"));
		}

		$sort = array('urt' => -1);
		$billapiParams = $this->getBillApiParams('lines', 'get', $query, [], $sort);
		return $this->filterEntitiesByPagination($this->runBillApi($billapiParams), $page, $size);
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

	/**
	 * add fields to response
	 *
	 * @param  array response
	 * @return array the updated response
	 */
	protected function addToResponse($response) {
		if ($this->paginationRequest()) {
			$response['total_pages'] = $this->getTotalPages();
		}
		return $response;
	}

}

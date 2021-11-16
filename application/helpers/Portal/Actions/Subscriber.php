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
                $this->addPlanDetails($subscriber, $params);
                $this->addServicesDetails($subscriber, $params);
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
            $plan = new Billrun_Plan(['name' => $subscriber['plan']]);
            $subscriber['plan_description'] =  $plan->get('description');
            $servicesIncludeInPlan = $plan->get('include')['services'] ?? [];
            foreach ($servicesIncludeInPlan as $index => $serviceIncludeInPlan){
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
            if(isset($subscriber['services'])){
                foreach ($subscriber['services'] as &$subscriberService) {
                    $this->addServiceDetails($subscriberService, $params);
                   
                }
            }
            if(isset($subscriber['include']['services'])){
                foreach ($subscriber['include']['services'] as &$subscriberService) {
                    $this->addServiceDetails($subscriberService, $params);
                 
                }
            }
        }
        
        /**
         * Add service groups usages 
         * @param type $service
         */
        protected function addServiceGroupsUsages(&$service) {
                $balances = $this->getBalances();
                if(isset($service['include']['groups'])){
                    foreach ($service['include']['groups'] as $serviceGroupName => &$serviceGroup)
                        foreach ($balances as $balance){
                            if(isset($balance['balance']['groups'][$serviceGroupName])){
                                $serviceGroup['usage']['used'] = $balance['balance']['groups'][$serviceGroupName]['usagev'];
                                $serviceGroup['usage']['total'] = $balance['balance']['groups'][$serviceGroupName]['total'];
                                break;
                            }
                        }
                        if(!isset($serviceGroup['usage']['used'])){
                            $serviceGroup['usage']['used'] = 0;
                        }
                        if(!isset($serviceGroup['usage']['total'])){
                            if(isset($serviceGroup['value'])){
                                $serviceGroup['usage']['total'] = $serviceGroup['value'];
                            }else{
                                //TODO:: support Monetary based (cost)
                                unset($serviceGroup['usage']['used']);
                                $serviceGroup['usage']['display'] = false;
                            }
                        }
                }         
        }
	
        /**
         * add service details to subscriber
         * @param array $subscriberServices - the services we will add the details
         * @param array $params
         */
        protected  function addServiceDetails(&$subscriberService, $params) {
            $service = new Billrun_Service(['name' => $subscriberService['name']]);
            $subscriberService['description'] = $service->get('description');
            $include = $service->get('include');
            if(isset($include)){
                $subscriberService['include'] = $include;
            } 
            $includeUsages = $params['include_usages'] ?? true;
            if ($includeUsages) {
                $this->addServiceGroupsUsages($subscriberService);
            }           
        }
	
	/**
	 * get subscriber active balances
	 *
	 * @return array
	 */
	protected function getBalances() {
		$time = date(DATE_ISO8601);
		$query = [
			'aid' => $this->loggedInEntity['aid'],
			'sid' => $this->loggedInEntity['sid'],
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

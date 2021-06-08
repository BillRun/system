<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Customer Portal account actions
 * 
 * @package  Billing
 * @since    5.14
 */
class Portal_Actions_Account extends Portal_Actions {

	const DATETIME_FORMAT = 'Y-m-d H:i:s';

	public function __construct($params = []) {
		parent::__construct($params);
		Yaf_Loader::getInstance(APPLICATION_PATH . '/application/modules/Billapi')->registerLocalNamespace("Models");
		Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/conf/modules/billapi/accounts.ini');
	}
        
    /**
     * get account by given query
	 * using BillApi
	 *
     * @param  array $params
     * @return array
     */
    public function get($params = []) {
		$query = $params['query'] ?? [];
		if (empty($query)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "query"');
		}
		$billapiParams = $this->getBillApiParams('uniqueget', $query);
		$res = $this->runBillApi($billapiParams);
		if ($res === false) {
			throw new Portal_Exception('account_get_failure');
		}
		
		return $this->getDetails(current($res));
    }
    
    /**
     * update account by given query and update
	 * using BillApi
     *
     * @param  array $params
     * @return array account updated details
     */
    public function update($params = []) {
		$query = $params['query'] ?? [];
		if (empty($query)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "query"');
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
		
		$billapiParams = $this->getBillapiParams('permanentchange', $query, $update);
		$res = $this->runBillApi($billapiParams);
		if ($res === false) {
			throw new Portal_Exception('account_update_failure');
		}

		$account = $this->get($params);
		return $this->getDetails($account);
	}
	
	/**
	 * get account invoices
	 *
	 * @param  array $params
	 * @return array
	 * @todo implement
	 */
	public function getInvoices($params = []) {
	}
	
	/**
	 * format account details to return
	 *
	 * @param  array $account
	 * @return array
	 */
	protected function getDetails($account) {
		unset($account['_id'], $account['payment_gateway']);
		return $account;
	}
	
	/**
	 * run BillApi action
	 *
	 * @param  array $params
	 * @return mixed
	 */
	protected function runBillApi($params) {
		try {
			$action = $params['request']['action'];
			switch ($action) {
				case 'uniqueget':
				case 'get':
					$modelAction = Models_Action::getInstance($params);
					return $modelAction->execute();
				default:
					$entityModel = Models_Entity::getInstance($params);
					return $entityModel->{$action}();
			}
		} catch (Exception $ex) {
            Billrun_Factory::log("Portal_Actions_Account::runBillApi got Error: {$ex->getCode()} - {$ex->getMessage()}", Billrun_Log::ERR);
            return false;
		}
	}
	
	/**
	 * get parameters required to run BillApi
	 *
	 * @param  mixed $action
	 * @param  mixed $query
	 * @param  mixed $update
	 * @return void
	 */
	protected function getBillApiParams($action, $query = [], $update = []) {
		$ret = [
			'collection' => 'accounts',
			'request' => [
				'collection' => 'accounts',
				'action' => $action,
			],
			'settings' => Billrun_Factory::config()->getConfigValue("billapi.accounts.{$action}", []),
		];

		if (!empty($query)) {
			$ret['request']['query'] = json_encode($query);
		}

		if (!empty($update)) {
			$ret['request']['update'] = json_encode($update);
		}

		return $ret;
	}

	/**
	 * Authenticate the request.
	 * Currently, all actions are on the account which logged-in so no need for further authentication
	 *
	 * @param  array $params
	 * @return boolean
	 */
    protected function authenticate($params = []) {
		return true;
	}

}

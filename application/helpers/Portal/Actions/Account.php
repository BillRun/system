<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

require_once APPLICATION_PATH . '/application/controllers/Action/Invoices.php';

/**
 * Customer Portal account actions
 * 
 * @package  Billing
 * @since    5.14
 */
class Portal_Actions_Account extends Portal_Actions {
        
    /**
     * get account by given query
	 * using BillApi
	 *
     * @param  array $params
     * @return array
     */
    public function get($params = []) {
		$query = $params['query'] ?? [];
		if (!empty($this->loggedInEntity)) {
			$query['aid'] = $this->loggedInEntity['aid'];
		}
		
		if (empty($query)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "query"');
		}
		
		$billapiParams = $this->getBillApiParams('accounts', 'uniqueget', $query);
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
		$query = [
			'aid' => $this->loggedInEntity['aid'],
			'type' => 'account',
		];

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
		
		$billapiParams = $this->getBillapiParams('accounts', 'permanentchange', $query, $update);
		$res = $this->runBillApi($billapiParams);
		if ($res === false) {
			throw new Portal_Exception('account_update_failure');
		}

		$account = $this->get($params);
		return $this->getDetails($account);
	}
	
	/**
	 * Update account's password
	 *
	 * @param  array $params
	 * @return boolean
	 */
	public function updatePassword($params = []) {
		$newPassword = $params['update']['password'] ?? '';
		$userId = $this->params['token_data']['user_id'] ?? '';
		
		if (empty($newPassword)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "password"');
		}

		$res = Billrun_Factory::oauth2()->getStorage('user_credentials')->setUser($userId, $newPassword);
		if ($res === false) {
			throw new Portal_Exception('account_update_failure');
		}
		
		return true;
	}
	
	/**
	 * get account invoices
	 *
	 * @param  array $params
	 * @return array
	 */
	public function invoices($params = []) {
		$query = $params['query'] ?? [];
		$query['aid'] = $this->loggedInEntity['aid'];
		$query['type'] = 'inv';
		
		$billapiParams = $this->getBillApiParams('bills', 'get', $query);
		$invoices = $this->runBillApi($billapiParams);
		
		foreach ($invoices as &$invoice) {
			$invoice = $this->getInvoiceDetails($invoice);
		}
		
		return $invoices;
	}
	
	/**
	 * Format invoice details
	 *
	 * @param  array $invoice
	 * @return array
	 */
	protected function getInvoiceDetails($invoice) {
		$invoice = parent::getDetails($invoice);
		$fieldsToHide = [
			'_id',
			'invoice_file',
		];
		
		foreach($fieldsToHide as $fieldToHide) {
			unset($invoice[$fieldToHide]);
		}

		return $invoice;
	}
	
	/**
	 * Download an invoice
	 * Will return invoice metadata as the response
	 *
	 * @param  array $params
	 * @return void
	 */
	public function downloadInvoice($params = []) {
		$invoiceParams = $params['invoice'];

		if (empty($invoiceParams['invoice_id'])) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "invoice_id"');
		}

		if (empty($invoiceParams['billrun_key'])) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "billrun_key"');
		}
		
		$request = [
			'aid' => $this->loggedInEntity['aid'],
			'iid' => $invoiceParams['invoice_id'],
			'billrun_key' => $invoiceParams['billrun_key'],
			'confirmed_only' => $invoiceParams['confirmed_only'] ?? false,
		];

		$invoicesAction = new AccountInvoicesAction();
		$invoicesAction->downloadPDF($request);
		throw new Portal_Exception('no_invoice'); // if we are here, that means that the invoice was not downloaded
	}
	
	/**
	 * format account details to return
	 *
	 * @param  array $account
	 * @return array
	 */
	protected function getDetails($account) {
		$account = parent::getDetails($account);
		if ($account === false ) {
			return false;
		}
		
		$account['subscribers'] = $this->getSubscribers($account);
		unset($account['_id'], $account['payment_gateway']);
		return $account;
	}
	
	/**
	 * get the subscribers related to the account
	 *
	 * @param  mixed $account
	 * @return array
	 */
	protected function getSubscribers($account) {
		$query = [
			'type' => 'subscriber',
			'aid' => $account['aid'],
		];

		$billapiParams = $this->getBillApiParams('subscribers', 'uniqueget', $query);
		$subscribers = $this->runBillApi($billapiParams);
		$subscriberFields = $this->getSubscriberFields();
		return array_map(function($subscriber) use ($subscriberFields) {
			return array_intersect_key($subscriber, array_flip($subscriberFields));
		}, $subscribers);
	}
	
	/**
	 * get the basic fields to show for a subscriber
	 *
	 * @return array
	 */
	protected function getSubscriberFields() {
		$customFields = array_merge(
			Billrun_Factory::config()->getConfigValue('subscribers.fields', []),
			Billrun_Factory::config()->getConfigValue('subscribers.subscriber.fields', [])
		);
		
		return array_column(array_filter($customFields, function($customField) {
			return !empty($customField['system']) || !empty($customField['show_in_list']) || !empty($customField['unique']);
		}), 'field_name');
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
		
		return $this->loginLevel === self::LOGIN_LEVEL_ACCOUNT;
	}

}

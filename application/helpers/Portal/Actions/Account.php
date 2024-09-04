<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Invoices.php';
require_once APPLICATION_PATH . '/application/controllers/Action/Bill.php';

/**
 * Customer Portal account actions
 * 
 * @package  Billing
 * @since    5.14
 */
class Portal_Actions_Account extends Portal_Actions {

	use Billrun_Traits_ConditionsCheck;

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
		$oldPassword = $params['update']['old_password'] ?? '';
		$newPassword = $params['update']['new_password'] ?? '';
		$userId = $this->params['token_data']['user_id'] ?? '';

		if (empty($oldPassword)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "old password"');
		}

		if (empty($newPassword)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "password"');
		}

		// validate old password
		$oldPasswordValidation = Billrun_Factory::oauth2()->getStorage('user_credentials')->checkUserCredentials($userId, $oldPassword);
		if ($oldPasswordValidation !== TRUE) {
			throw new Portal_Exception('password_old_failed_not_match');
		}

		// validate same old and new password
		$samePasswordValidation = Billrun_Factory::oauth2()->getStorage('user_credentials')->checkUserCredentials($userId, $newPassword);
		if ($samePasswordValidation == TRUE) {
			throw new Portal_Exception('password_old_failed_same');
		}

		$passwordStrengthValidation = Billrun_Utils_Security::validatePasswordStrength($newPassword, $params['change_password']['password_strength'] ?? []);
		if ($passwordStrengthValidation !== TRUE) {
			throw new Portal_Exception('password_strength_failed_' . abs($passwordStrengthValidation));
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

		if (empty($params['sort'])) {
			$params['sort'] = ['urt' => -1];
		}
		
		$billapiParams = $this->getBillApiParams('bills', 'get', $query, [], $params['sort']);
		$invoices = $this->runBillApi($billapiParams);

		foreach ($invoices as &$invoice) {
			$invoice = $this->getInvoiceDetails($invoice);
		}

		return $invoices;
	}

	/**
	 * get account debt
	 *
	 * @param  array $params - the api params
	 * @return array total account debt 
	 */
	public function debt($params = []) {
		$query = $params['query'] ?? [];
		$query['aids'] = json_encode([$this->loggedInEntity['aid']]);
		$only_debt = $query['only_debt'] ?? true;

		$billAction = new BillAction();
		$debt = $billAction->getCollectionDebt($query, $only_debt);

		return $debt;
	}

	/**
	 * get account outstanding balance (debt and future non-due invoices)
	 *
	 * @param  array $params - the api params
	 * @return array total account outstanding balance 
	 */
	public function outstanding($params = []) {
		$aid = (int) $this->loggedInEntity['aid'];
		if (empty($aid)) {
			return;
		}
		return Billrun_Bill::getTotalDueForAccount($aid);
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

		foreach ($fieldsToHide as $fieldToHide) {
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
		$invoiceParams = $params['query'];
		if (empty($invoiceParams)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "query"');
		}

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
		if ($account === false) {
			return false;
		}
		$account['subscribers'] = $this->getSubscribers($account);
		foreach ($account['subscribers'] as &$subscriber) {
			$this->addPlanDetails($subscriber);
		}
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
		return array_map(function ($subscriber) use ($subscriberFields) {
			return array_intersect_key($subscriber, array_flip($subscriberFields));
		}, $subscribers);
	}

	/**
	 * add plan details to subscriber
	 *
	 * @param  array $subscriber
	 */
	protected function addPlanDetails(&$subscriber) {
		$plan = Billrun_Factory::plan(['name' => $subscriber['plan'], 'time' => strtotime(date('Y-m-d H:00:00'))]);
		$subscriber['plan_description'] = $plan->get('description');
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

		return array_column(array_filter($customFields, function ($customField) {
					return !empty($customField['system']) || !empty($customField['show_in_list']) || !empty($customField['unique']);
				}), 'field_name');
	}

	/**
	 * get account charges (see statement)
	 * 
	 * @deprecated since version 5.17
	 * 
	 */
	public function charges($params = []) {
		return $this->statement($params);
	}

	/**
	 * get account statement (bills -> invoices & payments)
	 *
	 * @param  array $params - the api params
	 * 
	 * @return array the account charges
	 */
	public function statement($params = []) {
		$query = $params['query'] ?? [];
		$query['aid'] = $this->loggedInEntity['aid'];
		$type = $query['type'];
		if (empty($type)) {
			throw new Portal_Exception('missing_parameter', '', 'Missing parameter: "type"');
		}
		unset($query['type']);
		$billapiParams = $this->getBillApiParams('bills', 'get', $query, [], $params['sort'] ?? []);
		$bills = $this->runBillApi($billapiParams);
		$conditions = $this->convertQueryToConditions($this->buildBillQuery($type));
		$billsResult = [];
		foreach ($bills as $index => &$bill) {
			if (!$this->isConditionsMeet($bill, $conditions)) {
				continue;
			}
			$billsResult[] = $this->getChargesDetails($bill);
		}

		return $billsResult;
	}

	/**
	 * Build a query by the type
	 *
	 * @param  string $type
	 * @return array
	 */
	protected function buildBillQuery($type) {
		$nonRejectedOrCanceled = Billrun_Bill::getNotRejectedOrCancelledQuery();
		$notPandingBiils = array(
			'pending' => array('$ne' => true)
		);
		switch ($type) {
			case 'successful charges':
			case 'SUCCESSFUL_CHARGES':
				return array_merge($nonRejectedOrCanceled, $notPandingBiils, array('type' => 'rec'));
			case 'all charges':
			case 'ALL_CHARGES':
				return array('type' => 'rec');
			case 'successful charges and invoices':
			case 'SUCCESSFUL_CHARGES_AND_INVOICES':
				return array_merge($nonRejectedOrCanceled, $notPandingBiils);
			case 'all charges and invoices':
			case 'ALL_CHARGES_AND_INVOICES':
				return array();
			default :
				throw new Portal_Exception('unsupport_parameter_value', '', 'Unsupport parameter value: "type" : ' . $type);
		}
	}

	/**
	 * Convert query to conditions
	 * 
	 * @param array $query
	 * @return array
	 */
	protected function convertQueryToConditions($query) {//TODO:: convert more complicated query to conditions and insert to Billrun_Traits_ConditionsCheck
		$conditions = [];
		$index = 0;
		foreach ($query as $fieldname => $value) {
			if (is_array($value)) {
				foreach ($value as $op => $val) {//can by more then 
					$conditions[$index]['field'] = $fieldname;
					$conditions[$index]['op'] = $op;
					$conditions[$index]['value'] = $val;
					$index++;
				}
			} else {
				$conditions[$index]['field'] = $fieldname;
				$conditions[$index]['op'] = '$eq';
				$conditions[$index]['value'] = $value;
				$index++;
			}
		}
		return $conditions;
	}

	/**
	 * Format charges details
	 *
	 * @param  array $bill
	 * @return array
	 */
	protected function getChargesDetails($bill) {
		$bill = parent::getDetails($bill);
		$fieldsToHide = [
			'_id',
		];

		foreach ($fieldsToHide as $fieldToHide) {
			unset($bill[$fieldToHide]);
		}

		return $bill;
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

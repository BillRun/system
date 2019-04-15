<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Collect.php';

/**
 * Pay action class
 *
 * @package  Action
 * @since    0.5
 */
class PayAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;
	
	public function execute() {
		$this->allowed();
		$request = $this->getRequest();
		Billrun_Factory::log()->log('Pay API call with params: ' . print_r($request->getRequest(), 1), Zend_Log::INFO);
		$method = $request->get('method');
		$action = $request->get('action');
		$jsonPayments = $request->get('payments');

		if (!$method) {
			return $this->setError('No method found', $request->getPost());
		}
		if (empty($action) && !(($paymentsArr = json_decode($jsonPayments, TRUE)) && (json_last_error() == JSON_ERROR_NONE) && is_array($paymentsArr))) {
			return $this->setError('No payments found', $request->getPost());
		}
		try {
			if ($action == 'split_bill') {
				$this->executeSplitBill($request);
				return;
			}
			if ($method == 'installment_agreement') {
				throw new Exception("Method installment_agreement must be transferred with action split_bill");
			}
			$payments = Billrun_Bill::pay($method, $paymentsArr);
			$emailsToSend = array();
			foreach ($payments as $payment) {
				$method = $payment->getPaymentMethod();
				if (in_array($method, array('wire_transfer', 'cheque')) && $payment->getDir() == 'tc') {
					if (!isset($emailsToSend[$method])) {
						$emailsToSend[$method] = array(
							'operation' => $method . '_refund',
							'entities' => array(),
						);
					}
					$entity = array(
						'aid' => $payment->getAccountNo(),
						'amount' => $payment->getAmount(),
						'BIC' => $payment->getBIC(),
						'IBAN' => $payment->getIBAN(),
						'bank_name' => $payment->getBankName(),
						'date' => date(Billrun_Base::base_datetimeformat, $payment->getTime()->sec),
					);
					$emailsToSend[$method]['entities'][] = $entity;
				}
			}
			if ($emailsToSend) {
				$subscriber = Billrun_Factory::subscriber();
				foreach ($emailsToSend as $method => $data) {
					$emailsResult = $subscriber->sendBillingOperationsNotifications($data);
					if (isset($emailsResult['status']) && $emailsResult['status'] == 1) {
						Billrun_Factory::log()->log($method . ' refund: ' . $emailsResult['emails_sent'] . ' emails queued for sending.', Zend_Log::INFO);
					} else {
						Billrun_Factory::log()->log('CRM returned with error when trying to send emails (' . $method . ' refund)', Zend_Log::ALERT);
					}
				}
			}
			$this->getController()->setOutput(array(array(
					'status' => 1,
					'desc' => 'success',
					'input' => $request->getPost(),
					'details' => array(
						'payments_received' => count($paymentsArr),
						'payments_saved' => count($payments),
					),
			)));
		} catch (Exception $ex) {
			$this->setError($ex->getMessage(), $request->getPost());
			return;
		}
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
	}
		
	/**
	 * Creates rec with mwthod installment_agreement and splits it to installments.
	 * @param array $params - parameters for split bill action.
	 * 
	 */
	protected function executeSplitBill($request) {
		$params['amount'] = !empty($request->get('amount')) ? floatval($request->get('amount')) : 0;
		$params['installments_num'] = !empty($request->get('installments_num')) ?  $request->get('installments_num') : 0;
		$params['first_due_date'] = !empty($request->get('first_due_date')) ?  $request->get('first_due_date') : '';
		$installments = !empty($request->get('installments')) ?  $request->get('installments') : array();
		if(!empty($installments)) {
			$params['installments_agreement'] = json_decode($installments, true);
			$amountsArray = array_column($params['installments_agreement'], 'amount');
			if (!empty($amountsArray) && !Billrun_Util::isEqual(array_sum($amountsArray), $params['amount'], Billrun_Bill::precision)) {				
				throw new Exception('Sum of amounts in installments array must be equal to total amount');
			}
		}
		$params['aid'] = !empty($request->get('aid')) ? intval($request->get('aid')) : '';
		if (empty($params['amount']) || empty($params['aid'])) {
			throw new Exception('In action split_bill must transfer amount and aid parameters');
		}
		if (!empty($params['installments_agreement']) && (!empty($params['installments_num']) || !empty($params['first_due_date']))) {
			throw new Exception('Passed parameters in contradiction');
		}
		if ((!empty($params['installments_num']) && empty($params['first_due_date'])) || (empty($params['installments_num']) && !empty($params['first_due_date']))) {
			throw new Exception("installment_num and first_due_date parameters must be passed together");
		}
		$customerDebt = Billrun_Bill::getTotalDueForAccount($params['aid']);
		if ($params['amount'] > $customerDebt['without_waiting']) {
			throw new Exception("Passed amount is bigger than the customer debt");
		}
		$success = Billrun_Bill_Payment::createInstallmentAgreement($params);
		
		$this->getController()->setOutput(array(array(
			'status' => $success ? 1 : 0,
			'desc' => $success ? '' : 'failure',
			'input' => $request->getPost(),
			'details' => $success ? 'created installments successfully' : 'failed creating installments',
		)));

	}
}

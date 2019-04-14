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
		$params['amount'] = !empty($request->get('amount')) ?  $request->get('amount') : 0;
		$params['installments_num'] = !empty($request->get('installments_num')) ?  $request->get('installments_num') : 0;
		$params['first_due_date'] = !empty($request->get('first_due_date')) ?  $request->get('first_due_date') : '';
		$params['installments'] = !empty($request->get('installments')) ?  $request->get('installments') : array();
		$params['aid'] = !empty($request->get('aid')) ?  $request->get('aid') : '';

		if (!$method) {
			return $this->setError('No method found', $request->getPost());
		}
		if (empty($action) && !(($paymentsArr = json_decode($jsonPayments, TRUE)) && (json_last_error() == JSON_ERROR_NONE) && is_array($paymentsArr))) {
			return $this->setError('No payments found', $request->getPost());
		}
		try {
			if ($this->isLegitSplitBillAction($action, $params)) {
				$this->executeSplitBill($params, $request);
				return;
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
	 * Validate action split bill.
	 * @param string $action - action to execute.
	 * @param array $params - parameters for split bill action.
	 * 
	 * @return true if legit split bill action.
	 */
	protected function isLegitSplitBillAction($action, $params) {
		if ($action != 'split_bill') {
			return false;
		}
		if (empty($params['amount']) || empty($params['aid'])) {
			throw new Exception('In action split_bill must transfer amount parameter and aid');
		}
		if (!empty($params['installments']) && (!empty($params['installments_num']) || !empty($params['first_due_date']))) {
			throw new Exception('Passed parameters in contradiction');
		}
		if ((!empty($params['installments_num'] && empty($params['first_due_date']))) || (empty($params['installments_num'] && !empty($params['first_due_date'])))) {
			throw new Exception("installment_num and first_due_date parameters must be passed together");
		}

		return true;
	}
		
	/**
	 * Creates rec with mwthod installment_agreement and splits it to installments.
	 * @param array $params - parameters for split bill action.
	 * 
	 */
	protected function executeSplitBill($params, $request) {
		$aid = intval($params['aid']);
		$amount = floatval($params['amount']);
		$customerDebt = Billrun_Bill::getTotalDueForAccount($aid);
		if ($amount > $customerDebt['without_waiting']) {
			throw new Exception("Passed amount is bigger than the customer debt");
		}
		$installmentAgreement = new Billrun_Bill_Payment_InstallmentAgreement($params);
		$installmentAgreement->splitBill();
		$this->getController()->setOutput(array(array(
			'status' => 1,
			'desc' => 'success',
			'input' => $request->getPost(),
			'details' => array(
				'split_bill id' => $installmentAgreement->getId(),
			),
		)));

	}
}

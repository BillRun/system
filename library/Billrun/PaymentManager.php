<?php

/**
 * Payment management - in charge of the integration between bills and payment gateways
 */
class Billrun_PaymentManager {

	use Billrun_Traits_ForeignFields;
	
	protected static $instance;

	public function __construct($params = []) {
		
	}

	/**
	 * Gets singleton instance of payment manager
	 * 
	 * @param array $params
	 * @return Billrun_PaymentManager
	 */
	public static function getInstance($params = []) {
		if (is_null(self::$instance)) {
			self::$instance = new Billrun_PaymentManager($params);
		}
		return self::$instance;
	}

	/**
	 * Handles payment (awaits response)
	 */
	public function pay($method, $paymentsData, $params = []) {
                Billrun_Factory::dispatcher()->trigger('beforePaymentManagerPay', array(&$method, &$paymentsData, &$params));
		if (!Billrun_Bill_Payment::validatePaymentMethod($method, $params)) {
			return $this->handleError("Unknown payment method {$method}");
		}

		$prePayments = $this->preparePayments($method, $paymentsData, $params);
		if (!$this->savePayments($prePayments)) {
			return $this->handleError('Error encountered while saving the payments');
		}

		$postPayments = $this->handlePayment($prePayments, $params);
		$this->handleSuccessPayments($postPayments, $params);
		$payments = $this->getInvolvedPayments($postPayments);
		return [
			'payment' => array_column($payments, 'payments'),
			'response' => $this->getResponsesFromGateways($postPayments),
			'payment_data' => array_column($payments, 'payment_data')
		];
	}

	/**
	 * prepare the data for making payments
	 * 
	 * @param string $method
	 * @param array $paymentsData
	 * @param array $params
	 * @returns array of pre-payment data for every payment
	 */
	protected function preparePayments($method, $paymentsData, $params = []) {
		$account = !empty($params['account']) ? $params['account'] : null;
		$prePayments = [];
		foreach ($paymentsData as $paymentData) {
			Billrun_Bill::convertRelatedBills($paymentData);
			$prePayment = new Billrun_DataTypes_PrePayment(array_merge($paymentData, $params), $method);
			$prePayment->setPayment($this->getPayment($method, $paymentData, $params));
			$this->handleInvoicesAndPaymentsAttachment($prePayment, $params);
			if (!is_null($account)) {
				$this->setPaymentForeignFields($prePayment, $account);
			}
			$this->setUserFields($prePayment);
			$prePayments[] = $prePayment;
		}

		return $prePayments;
	}

	/**
	 * get payment object from payment data
	 * 
	 * @param string $method
	 * @param array $paymentData
	 * @param array $params
	 * @return Billrun_Bill_Payment
	 */
	protected function getPayment($method, $paymentData, $params = []) {
		if (!empty($params['file_based_charge']) && isset($params['generated_pg_file_log'])) {
			$paymentData['generated_pg_file_log'] = $params['generated_pg_file_log'];
		}

		$payment = Billrun_Bill_Payment::getInstance($method, array_merge($paymentData, $params));
		if (!$payment) {
			return $this->handleError("Cannot get payment for {$method}. Payment data: " . print_R($paymentData, 1));
		}

		return $payment;
	}

	/**
	 * handles attachment of invoices to payments and vice versa
	 * 
	 * @param array $paymentData
	 * @param Billrun_DataTypes_PrePayment $prePayment - by reference
	 * @param array $params
	 */
	protected function handleInvoicesAndPaymentsAttachment(&$prePayment, $params = []) {
		$dir = $prePayment->getCustomerDirection();
		if (!in_array($dir, [Billrun_DataTypes_PrePayment::DIR_FROM_CUSTOMER, Billrun_DataTypes_PrePayment::DIR_TO_CUSTOMER]) && !is_null($dir)) {
			return;
		}

		$paymentData = $prePayment->getData();
		$paymentDir = $prePayment->getPaymentDirection();
		switch ($paymentDir) {
			case Billrun_DataTypes_PrePayment::PAY_DIR_PAYS:
			case Billrun_DataTypes_PrePayment::PAY_DIR_PAID_BY:
				$method = $prePayment->getMethod();
				$prePayment->setPayment($this->getPayment($method, $paymentData, $params));
				$this->attachInvoicesAndPayments(Billrun_DataTypes_PrePayment::BILL_TYPE_INVOICE, $prePayment, $params);
				$this->attachInvoicesAndPayments(Billrun_DataTypes_PrePayment::BILL_TYPE_RECEIPT, $prePayment, $params);
				break;
			default: // one of fc/tc
				$this->attachAllInvoicesAndPayments($prePayment, $dir, $params);
		}
	}

	/**
	 * Attach invoices to payments and vice versa
	 * 
	 * @param array $paymentData
	 * @param Billrun_DataTypes_PrePayment $prePayment - by reference
	 * @param array $params
	 */
	protected function attachInvoicesAndPayments($billType, &$prePayment, $params = []) {
		$billsToHandle = $prePayment->getBillsToHandle($billType);
		if (empty($billsToHandle)) {
			return;
		}
		$relatedBills = $prePayment->getRelatedBills($billType);
		if (count($relatedBills) != count($billsToHandle)) {
			return $this->handleError("Unknown {$prePayment->getDisplayType($billType)}/s for account {$prePayment->getAid()}");
		}

		if (($prePayment->getAmount() - array_sum($billsToHandle)) <= -Billrun_Bill::precision) {
			return $this->handleError("{$prePayment->getAid()}: Total to pay is less than the subtotals");
		}

		foreach ($relatedBills as $billData) {
			$bill = $prePayment->getBill($billType, $billData);
			if ($prePayment->getPaymentDirection() == Billrun_DataTypes_PrePayment::PAY_DIR_PAYS && $bill->isPaid()) {
				return $this->handleError("{$prePayment->getDisplayType($billType)} {$bill->getId()} already paid");
			}

			$billAmount = $prePayment->getBillAmount($billType, $bill->getId());
			if (!is_numeric($billAmount)) {
				return $this->handleError("Illegal amount for {$prePayment->getDisplayType($billType)} {$bill->getId()}");
			}

			$billAmount = floatval(Billrun_Bill::getRelatedBill($billsToHandle, $billType, $bill->getId())['amount']);
			$leftAmount = $prePayment->getBillAmountLeft($bill);
			if ($leftAmount < $billAmount && number_format($leftAmount, 2) != number_format($billAmount, 2)) {
				return $this->handleError("{$prePayment->getDisplayType($billType)} {$bill->getId()} cannot be overpaid");
			}

			$prePayment->addUpdatedBill($billType, $bill);
		}
	}

	/**
	 * Attach all invoices to payments and vice versa
	 * 
	 * @param Billrun_DataTypes_PrePayment $prePayment - by reference
	 * @param string $dir
	 * @param array $params
	 */
	protected function attachAllInvoicesAndPayments(&$prePayment, $dir, $params = []) {
		if (is_null($dir)) {
			return;
		}
		$method = $prePayment->getMethod();
		$leftToSpare = $prePayment->getAmount();
		$relatedBills = $prePayment->getRelatedBills();
		foreach ($relatedBills as $billData) {
			$bill = $prePayment->getBill('', $billData);
			$billAmountLeft = $prePayment->getBillAmountLeft($bill);
			$amount = min($billAmountLeft, $leftToSpare);
			if ($amount) {
				$billType = $bill->getType();
				$paymentDir = $dir == Billrun_DataTypes_PrePayment::DIR_FROM_CUSTOMER ? Billrun_DataTypes_PrePayment::PAY_DIR_PAYS : Billrun_DataTypes_PrePayment::PAY_DIR_PAID_BY;
				$billId = $bill->getId();
				$relatedBills = $prePayment->getData($paymentDir, []);
				Billrun_Bill::addRelatedBill($relatedBills, $billType, $billId, $amount);
				$prePayment->setData($paymentDir, $relatedBills);
				$paymentData = $prePayment->getData();
				$prePayment->setPayment($this->getPayment($method, $paymentData, $params));
				$leftToSpare -= $amount;
				$prePayment->addUpdatedBill($billType, $bill);
			}
		}
	}

	/**
	 * get aids involved in payments
	 * 
	 * @param array $prePayments - array of Billrun_DataTypes_PrePayment
	 * @return array
	 */
	protected function getInvolvedAccounts($prePayments) {
		$involvedAccounts = [];
		foreach ($prePayments as $prePayment) {
			$involvedAccounts[] = $prePayment->getAid();
		}

		return array_unique($involvedAccounts);
	}

	/**
	 * save payments to DB
	 * 
	 * @param array $prePayments - array of Billrun_DataTypes_PrePayment
	 * @return boolean
	 */
	protected function savePayments($prePayments) {
		$response = $this->getInvolvedPayments($prePayments);
		$payments = array_column($response, 'payments');
		$ret = Billrun_Bill_Payment::savePayments($payments);
		if (!$ret || empty($ret['ok'])) {
			return false;
		}

		return $ret;
	}

	/**
	 * get involved payments
	 * 
	 * @param array $prePayments - array of Billrun_DataTypes_PrePayment
	 * @return array
	 */
	protected function getInvolvedPayments($prePayments) {
		$payments = [];
		foreach ($prePayments as $prePayment) {
			$payment = $prePayment->getPayment();
			if ($payment) {
				$payments[] = ['payments' => $payment , 'payment_data' => $prePayment->getData()];
			}
		}

		return $payments;
	}

	/**
	 * get responses from payment gateways
	 * 
	 * @param array $postPayments - array of Billrun_DataTypes_PostPayment
	 * @return array
	 */
	protected function getResponsesFromGateways($postPayments) {
		$responses = [];
		foreach ($postPayments as $postPayment) {
			$responses[$postPayment->getTransactionId()] = $postPayment->getPgResponse();
		}

		return $responses;
	}

	/**
	 * handles payment against payment gateway (if exists)
	 * 
	 * @param array $prePayments - array of Billrun_DataTypes_PrePayment
	 * @param array $params
	 * @return array of Billrun_DataTypes_PostPayment - payments
	 */
	protected function handlePayment($prePayments, $params = []) {
		$ret = [];
		if (!$this->hasPaymentGateway($params)) { // no payment gateway - all payments are considered as successful
			foreach ($prePayments as $prePayment) {
				$postPayment = new Billrun_DataTypes_PostPayment($prePayment);
				Billrun_Factory::dispatcher()->trigger('afterPaymentHandeled', array(&$postPayment));
				$ret[] = $postPayment;
			}
			return $ret;
		}

		foreach ($prePayments as $prePayment) {
			$postPayment = new Billrun_DataTypes_PostPayment($prePayment);
			$payment = $prePayment->getPayment();
			$gatewayDetails = $payment->getPaymentGatewayDetails();
			$gatewayName = $gatewayDetails['name'];
			$gatewayInstanceName = $gatewayDetails['instance_name'];
			$gateway = Billrun_PaymentGateway::getInstance($gatewayInstanceName);

			if (is_null($gateway)) {
				Billrun_Factory::log("Illegal payment gateway object", Zend_Log::ALERT);
			} else {
				Billrun_Factory::log("Paying bills through " . $gatewayName, Zend_Log::INFO);
				Billrun_Factory::log("Charging payment gateway details: " . "name=" . $gatewayName . ", amount=" . $gatewayDetails['amount'] . ', charging account=' . $prePayment->getAid(), Zend_Log::DEBUG);
			}

			if (empty($params['single_payment_gateway'])) {
				try {
					$payment->setPending(true);
					$addonData = array('aid' => $payment->getAid(), 'txid' => $payment->getId());
					$paymentStatus = $gateway->makeOnlineTransaction($gatewayDetails, $addonData);
                                        $responseFromGateway = Billrun_PaymentGateway::checkPaymentStatus($paymentStatus['status'], $gateway, $paymentStatus['additional_params']);
				} catch (Exception $e) {
					$payment->setGatewayChargeFailure($e->getMessage());
					$responseFromGateway = array('status' => $e->getCode(), 'stage' => "Rejected");
					Billrun_Factory::log('Failed to pay bill: ' . $e->getMessage(), Zend_Log::ALERT);
				}
			} else {
				$paymentStatus = array(
					'status' => $payment->getSinglePaymentStatus(),
					'additional_params' => isset($params['additional_params']) ? $params['additional_params'] : array(),
				);
				if (empty($paymentStatus['status'])) {
					return $this->handleError("Missing status from gateway for single payment");
				}
                                $responseFromGateway = Billrun_PaymentGateway::checkPaymentStatus($paymentStatus['status'], $gateway, $paymentStatus['additional_params']);
			}
			$txId = $gateway->getTransactionId();
			$payment->updateDetailsForPaymentGateway($gatewayName, $txId);
			$postPayment->setTransactionId($txId);
			$postPayment->setPgResponse($responseFromGateway);
			Billrun_Factory::dispatcher()->trigger('afterPaymentHandeled', array(&$postPayment, $gateway));
			$ret[] = $postPayment;
		}

		return $ret;
	}

	protected function hasPaymentGateway($params) {
		return isset($params['payment_gateway']) && $params['payment_gateway'];
	}

	protected function isFileBasedCharge($params) {
		return isset($params['file_based_charge']) && $params['file_based_charge'];
	}

	protected function getSuccessPayments($postPayments, $params = []) {
		return array_filter($postPayments, function ($postPayment) {
			$pgResponse = $postPayment->getPgResponse();
			return Billrun_Util::getIn($pgResponse, 'stage', '') == 'Completed';
		});
	}

	/**
	 * handles success payment
	 * 
	 * @param array $postPayments - array of Billrun_DataTypes_PostPayment
	 * @param array $params
	 */
	protected function handleSuccessPayments($postPayments, $params = []) {
		foreach ($postPayments as $postPayment) {
                        $payment = $postPayment->getPayment();
			if (empty($payment)) {
				return $this->handleError("Cannot get payment");
			}
			$paymantData = $payment->getRawData();
			$transactionId = Billrun_Util::getIn($paymantData, 'payment_gateway.transactionId');
                        $pgResponse = $postPayment->getPgResponse();
			if (isset($paymantData['payment_gateway']) && empty($transactionId) && $pgResponse['stage'] != 'Rejected') {
				return $this->handleError('Illegal transaction id for aid ' . $paymantData['aid'] . ' in response from ' . $paymantData['name']);
			}

			$customerDir = $postPayment->getCustomerDirection();
			$gatewayDetails = $payment->getPaymentGatewayDetails();

			if (!empty($params['pretend_bills']) && $pgResponse && $pgResponse['stage'] != 'Pending') {
				$payment->setPending(false);
			}

			switch ($customerDir) {
				case Billrun_DataTypes_PrePayment::DIR_FROM_CUSTOMER:
				case Billrun_DataTypes_PrePayment::DIR_TO_CUSTOMER:
					$relatedBills = $postPayment->getRelatedBills();
					foreach ($relatedBills as $bill) {
						$billId = $bill['id'];
						$billType = $bill['type'];
						$amountPaid = $bill['amount'];
						if ($this->isFileBasedCharge($params) && $payment->isWaiting()) {
							$payment->setPending(true);
						}

						if ($pgResponse && $pgResponse['stage'] != 'Pending') {
							$payment->setPending(false);
						}
						$updatedBill = $postPayment->getUpdatedBill($billType, $billId);
						if ($customerDir === Billrun_DataTypes_PrePayment::DIR_FROM_CUSTOMER) {
							$updatedBill->attachPayingBill($payment, $amountPaid, (!empty($pgResponse) && empty($pgResponse['stage']) || ($this->isFileBasedCharge($params) && $payment->isWaiting())) ? 'Pending' : @$pgResponse['stage'])->save();
						} else {
							$updatedBill->attachPaidBill($payment->getType(), $payment->getId(), $amountPaid)->save();
						}
					}
					break;
				default:
					Billrun_Bill::payUnpaidBillsByOverPayingBills($payment->getAccountNo());
			}

			if (!empty($gatewayDetails)) {
				$gatewayAmount = isset($gatewayDetails['amount']) ? $gatewayDetails['amount'] : $gatewayDetails['transferred_amount'];
			} else {
				$gatewayAmount = 0;
				Billrun_Factory::log('No $gatewayDetails variable defined to rerive amount from, assuming the amount is : 0',Zend_Log::WARN);
			}

			if (!empty($pgResponse)) {
				$pgResponseStage = $pgResponse['stage'];
				if ($pgResponseStage == 'Completed') {
					if ($gatewayAmount < (0 - Billrun_Bill::precision)) {
						Billrun_Factory::dispatcher()->trigger('afterRefundSuccess', array($payment->getRawData()));
					} else if ($gatewayAmount > (0 + Billrun_Bill::precision)) {
						Billrun_Factory::dispatcher()->trigger('afterChargeSuccess', array($payment->getRawData()));
					}
				}
			} else if (!isset($params['file_based_charge']) && $payment->getAmount() > 0) { // offline payment
				Billrun_Factory::dispatcher()->trigger('afterChargeSuccess', array($payment->getRawData()));
			}
		}
	}

	protected function handleError($errorMessage, $logLevel = Billrun_Log::CRIT) {
		Billrun_Factory::log($errorMessage, $logLevel);
		throw new Exception($errorMessage);
	}

	protected function setUserFields (&$prePayment) {
		$payment = $prePayment->getPayment();
		$payment->setUserFields($prePayment->getData(), true);
	}


	protected function setPaymentForeignFields (&$payment, $account) {
		$foreignData = $this->getForeignFields(array('account' => $account ));
		$payment->getPayment()->setForeignFields($foreignData);
	}
	
	protected function getForeignFieldsEntity () {
		return 'bills';
	}
}

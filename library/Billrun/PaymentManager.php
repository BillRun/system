<?php

/**
 * Payment management - in charge of the integration between bills and payment gateways
 */
class Billrun_PaymentManager {

	use Billrun_Traits_Api_OperationsLock;
	use Billrun_Traits_ForeignFields;

	protected static $instance;
	public $account_involved_payments = [];

	/**
	 * Lock action name
	 * @var string
	 */
	protected $lock_action;

	/**
	 * Aid to lock
	 * @var int
	 */
	protected $locked_aid;

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
		Billrun_Factory::log("Payment manager 'pay' function was called", Zend_Log::DEBUG);
		Billrun_Factory::dispatcher()->trigger('beforePaymentManagerPay', array(&$method, &$paymentsData, &$params));
		if (!Billrun_Bill_Payment::validatePaymentMethod($method, $params)) {
			return $this->handleError("Unknown payment method {$method}");
		}
		Billrun_Factory::log("Payment method " . $method . " is valid. Preparing payments", Zend_Log::DEBUG);

		$prePayments = $this->preparePayments($method, $paymentsData, $params);
		Billrun_Factory::log("Saving " . count($prePayments) . " pre payments data", Zend_Log::DEBUG);
		if (!$this->savePayments($prePayments, $params)) {
			return $this->handleError('Error encountered while saving the payments');
		}
		Billrun_Factory::log("Successfully saved " . count($prePayments) . " pre payments data. Handling payments", Zend_Log::DEBUG);
		$postPayments = $this->handlePayment($prePayments, $params);
		Billrun_Factory::log("Handling success payments", Zend_Log::DEBUG);
		$this->handleSuccessPayments($postPayments, $params);
		$params['after_save'] = true;
		Billrun_Factory::log("Getting account involved payments", Zend_Log::DEBUG);
		$payments = $this->getInvolvedPayments($postPayments, $params);
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
	protected function preparePayments($method, $paymentsData, &$params = []) {
		$account = !empty($params['account']) ? $params['account'] : null;
		if (!is_null($account)) {
			Billrun_Factory::log("Preparing payments for account " . (is_array($account) ? $account['aid'] : $account->aid), Zend_Log::DEBUG);
		}
		$prePayments = [];
		foreach ($paymentsData as $index => $paymentData) {
			Billrun_Factory::log("Preparing payment number " . $index . ". Converting related bills", Zend_Log::DEBUG);
			Billrun_Bill::convertRelatedBills($paymentData);
			Billrun_Factory::log("Creating pre-payment", Zend_Log::DEBUG);
			$prePayment = new Billrun_DataTypes_PrePayment(array_merge($paymentData, $params), $method);
			$prePayment->setPayment($this->getPayment($method, $paymentData, $params));
			Billrun_Factory::log("Handling attachments", Zend_Log::DEBUG);
			$this->handleInvoicesAndPaymentsAttachment($prePayment, $params);
			if (!is_null($account)) {
				Billrun_Factory::log("Setting payment foreign fields", Zend_Log::DEBUG);
				$this->setPaymentForeignFields($prePayment, $account);
			}
			Billrun_Factory::log("Setting payment user fields", Zend_Log::DEBUG);
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
	protected function handleInvoicesAndPaymentsAttachment(&$prePayment, &$params = []) {
		Billrun_Factory::log("Handle invoices and payments attachments function was called", Zend_Log::DEBUG);
		$dir = $prePayment->getCustomerDirection();
		Billrun_Factory::log("Customer direction is " . $dir, Zend_Log::DEBUG);
		if (!in_array($dir, [Billrun_DataTypes_PrePayment::DIR_FROM_CUSTOMER, Billrun_DataTypes_PrePayment::DIR_TO_CUSTOMER]) && !is_null($dir)) {
			return;
		}
		Billrun_Factory::log("Pulling payment data and direction", Zend_Log::DEBUG);
		$paymentData = $prePayment->getData();
		$paymentDir = $prePayment->getPaymentDirection();
		Billrun_Factory::log("Payment direction is ", Zend_Log::DEBUG);
		switch ($paymentDir) {
			case Billrun_DataTypes_PrePayment::PAY_DIR_PAYS:
			case Billrun_DataTypes_PrePayment::PAY_DIR_PAID_BY:
				$method = $prePayment->getMethod();
				Billrun_Factory::log("Payment method is " . $method, Zend_Log::DEBUG);
				$prePayment->setPayment($this->getPayment($method, $paymentData, $params));
				Billrun_Factory::log("Attaching invoices and payment for type " . Billrun_DataTypes_PrePayment::BILL_TYPE_INVOICE, Zend_Log::DEBUG);
				$this->attachInvoicesAndPayments(Billrun_DataTypes_PrePayment::BILL_TYPE_INVOICE, $prePayment, $params);
				Billrun_Factory::log("Attaching invoices and payment for type " . Billrun_DataTypes_PrePayment::BILL_TYPE_RECEIPT, Zend_Log::DEBUG);
				$this->attachInvoicesAndPayments(Billrun_DataTypes_PrePayment::BILL_TYPE_RECEIPT, $prePayment, $params);
				break;
			default: // one of fc/tc
				Billrun_Factory::log("Payment direction " . $paymentDir . " using the switch default behavior", Zend_Log::DEBUG);
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
		Billrun_Factory::log("Attach invoices and payments function was called for bill type " . $billType . ". Pulling bills to handle", Zend_Log::DEBUG);
		$billsToHandle = $prePayment->getBillsToHandle($billType);
		if (empty($billsToHandle)) {
			Billrun_Factory::log("Didn't find any bills to handle", Zend_Log::DEBUG);
			return;
		}
		Billrun_Factory::log("Pulling related bills", Zend_Log::DEBUG);
		$relatedBills = $prePayment->getRelatedBills($billType);
		if (count($relatedBills) != count($billsToHandle)) {
			return $this->handleError("Unknown {$prePayment->getDisplayType($billType)}/s for account {$prePayment->getAid()}");
		}

		if (($prePayment->getAmount() - array_sum($billsToHandle)) <= -Billrun_Bill::precision) {
			return $this->handleError("{$prePayment->getAid()}: Total to pay is less than the subtotals");
		}
		Billrun_Factory::log("Found " . count($relatedBills) . " related bills", Zend_Log::DEBUG);
		foreach ($relatedBills as $index => $billData) {
			Billrun_Factory::log("Processing related bill number " . $index, Zend_Log::DEBUG);
			$bill = $prePayment->getBill($billType, $billData);
			if ($prePayment->getPaymentDirection() == Billrun_DataTypes_PrePayment::PAY_DIR_PAYS && $bill->isPaid()) {
				return $this->handleError("{$prePayment->getDisplayType($billType)} {$bill->getId()} already paid");
			}

			$billAmount = $prePayment->getBillAmount($billType, $bill->getId());
			if (!is_numeric($billAmount)) {
				return $this->handleError("Illegal amount for {$prePayment->getDisplayType($billType)} {$bill->getId()}");
			}

			$billAmount = floatval(Billrun_Bill::getRelatedBill($billsToHandle, $billType, $bill->getId())['amount']);
			Billrun_Factory::log("Related bill amount is " . $billAmount, Zend_Log::DEBUG);
			$leftAmount = $prePayment->getBillAmountLeft($bill);
			Billrun_Factory::log("Bill " . $bill->getId() . " left amount is " . $leftAmount, Zend_Log::DEBUG);
			if ($leftAmount < $billAmount && number_format($leftAmount, 2) != number_format($billAmount, 2)) {
				return $this->handleError("{$prePayment->getDisplayType($billType)} {$bill->getId()} cannot be overpaid");
			}
			Billrun_Factory::log("Adding " . $bill->getId() . " updated bill to the pre payment updated bills", Zend_Log::DEBUG);
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
	protected function attachAllInvoicesAndPayments(&$prePayment, $dir, &$params = []) {
		Billrun_Factory::log("Attach all invoices and payments for payment direction " . $dir, Zend_Log::DEBUG);
		if (is_null($dir)) {
			return;
		}
		$method = $prePayment->getMethod();
		Billrun_Factory::log("Method " . $method, Zend_Log::DEBUG);
		$leftToSpare = $prePayment->getAmount();
		Billrun_Factory::log("Pre payment amount left to spare " . $leftToSpare, Zend_Log::DEBUG);
		$params['switch_links'] = Billrun_Bill::shouldSwitchBillsLinks();
		Billrun_Factory::log("Switch links flag value is " . ($params['switch_links'] ? "true" : "false"), Zend_Log::DEBUG);
		if ($params['switch_links']) {
			Billrun_Factory::log("Detaching pending payments for account " . $prePayment->getAid(), Zend_Log::DEBUG);
			Billrun_Bill_Payment::detachPendingPayments($prePayment->getAid());
		} else {
			Billrun_Factory::log("Pulling related bills of the pre payment", Zend_Log::DEBUG);
			$relatedBills = $prePayment->getRelatedBills();
			Billrun_Factory::log("Found " . count($relatedBills) . " related bills", Zend_Log::DEBUG);
			foreach ($relatedBills as $index => $billData) {
				Billrun_Factory::log("Processing related bill number " . $index, Zend_Log::DEBUG);
				$bill = $prePayment->getBill('', $billData);
				$billAmountLeft = $prePayment->getBillAmountLeft($bill);
				Billrun_Factory::log("Bill left amount is " . $billAmountLeft, Zend_Log::DEBUG);
				$amount = min($billAmountLeft, $leftToSpare);
				if ($amount) {
					Billrun_Factory::log("Amounts are relevant to attach the bills - left amount is " . $billAmountLeft . " and left to spare amount is " . $leftToSpare, Zend_Log::DEBUG);
					$billType = $bill->getType();
					$paymentDir = $dir == Billrun_DataTypes_PrePayment::DIR_FROM_CUSTOMER ? Billrun_DataTypes_PrePayment::PAY_DIR_PAYS : Billrun_DataTypes_PrePayment::PAY_DIR_PAID_BY;
					$billId = $bill->getId();
					Billrun_Factory::log("Bill type is " . $billType . ", bill id is " . $billId . ", payment direction is " . $paymentDir , Zend_Log::DEBUG);
					$relatedBills = $prePayment->getData($paymentDir, []);
					Billrun_Factory::log("Found " . count($relatedBills) . " related bills", Zend_Log::DEBUG);
					Billrun_Bill::addRelatedBill($relatedBills, $billType, $billId, $amount, $bill->getRawData());
					Billrun_Factory::log("Setting payment data", Zend_Log::DEBUG);
					$prePayment->setData($paymentDir, $relatedBills);
					$paymentData = $prePayment->getData();
					$prePayment->setPayment($this->getPayment($method, $paymentData, $params));
					$leftToSpare -= $amount;
					Billrun_Factory::log("Updated pre payment left to spare amount is " . $leftToSpare . ". Adding updated related bill data", Zend_Log::DEBUG);
					$prePayment->addUpdatedBill($billType, $bill);
				}
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
	protected function savePayments($prePayments, $params = []) {
		$response = $this->getInvolvedPayments($prePayments, $params);
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
	protected function getInvolvedPayments($prePayments, $params = []) {
		$payments = [];
		foreach ($prePayments as $index => $prePayment) {
			Billrun_Factory::log("Getting pre payment number " . $index . " most updated data", Zend_Log::DEBUG);
			list($payment, $payment_data) = $this->getPaymentMostUpdatedData($prePayment, $params);
			if ($payment) {
				$payments[] = ['payments' => $payment, 'payment_data' => $payment_data];
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
			Billrun_Factory::log("No payment gateway - all payments are considered successful", Zend_Log::DEBUG);
			foreach ($prePayments as $prePayment) {
				$postPayment = new Billrun_DataTypes_PostPayment($prePayment);
				Billrun_Factory::dispatcher()->trigger('afterPaymentHandeled', array(&$postPayment));
				$ret[] = $postPayment;
			}
			return $ret;
		}

		foreach ($prePayments as $index => $prePayment) {
			Billrun_Factory::log("Handling pre payment number " . $index, Zend_Log::DEBUG);
			$postPayment = new Billrun_DataTypes_PostPayment($prePayment);
			Billrun_Factory::log("Updating gateway details", Zend_Log::DEBUG);
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
				Billrun_Factory::log("single_payment_gateway parameter is set", Zend_Log::DEBUG);
				try {
					Billrun_Factory::log("Setting payment as pending", Zend_Log::DEBUG);
					$payment->setPending(true);
					$addonData = array('aid' => $payment->getAid(), 'txid' => $payment->getId());
					Billrun_Factory::log("Making online transaction for aid " . $payment->getAid() . " and payment id " . $payment->getId(), Zend_Log::DEBUG);
					$paymentStatus = $gateway->makeOnlineTransaction($gatewayDetails, $addonData);
					Billrun_Factory::log("Checking returned payment status, and processing gateway response", Zend_Log::DEBUG);
					$responseFromGateway = Billrun_PaymentGateway::checkPaymentStatus($paymentStatus['status'], $gateway, $paymentStatus['additional_params']);
				} catch (Exception $e) {
					$payment->setGatewayChargeFailure($e->getMessage());
					$responseFromGateway = array('status' => $e->getCode(), 'stage' => "Rejected");
					Billrun_Factory::log('Failed to pay bill: ' . $e->getMessage(), Zend_Log::ALERT);
				}
			} else {
				Billrun_Factory::log("single_payment_gateway parameter is not set", Zend_Log::DEBUG);
				$paymentStatus = array(
					'status' => $payment->getSinglePaymentStatus(),
					'additional_params' => isset($params['additional_params']) ? $params['additional_params'] : array(),
				);
				if (empty($paymentStatus['status'])) {
					return $this->handleError("Missing status from gateway for single payment");
				}
				Billrun_Factory::log("Checking returned payment status, and processing gateway response", Zend_Log::DEBUG);
				$responseFromGateway = Billrun_PaymentGateway::checkPaymentStatus($paymentStatus['status'], $gateway, $paymentStatus['additional_params']);
			}
			Billrun_Factory::log("Updating details from gateway, setting transaction id and pg response", Zend_Log::DEBUG);
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
		Billrun_Factory::log("Handling success payments function was called", Zend_Log::DEBUG);
		$switch_links = Billrun_Factory::config()->getConfigValue('bills.switch_links', true);
		Billrun_Factory::log("Switch links flag value is " . ($switch_links ? "true" : "false"), Zend_Log::DEBUG);
		foreach ($postPayments as $index => $postPayment) {
			Billrun_Factory::log("Handling post payment number " . $index, Zend_Log::DEBUG);
			$payment = $postPayment->getPayment();
			if (empty($payment)) {
				return $this->handleError("Cannot get payment");
			}
			Billrun_Factory::log("Pullong payment data, pg transacion id, and pg response", Zend_Log::DEBUG);
			$paymantData = $payment->getRawData();
			$transactionId = Billrun_Util::getIn($paymantData, 'payment_gateway.transactionId');
			$pgResponse = $postPayment->getPgResponse();
			if (isset($paymantData['payment_gateway']) && empty($transactionId) && $pgResponse['stage'] != 'Rejected') {
				return $this->handleError('Illegal transaction id for aid ' . $paymantData['aid'] . ' in response from ' . $paymantData['name']);
			}

			$customerDir = $postPayment->getCustomerDirection();
			$gatewayDetails = $payment->getPaymentGatewayDetails();
			Billrun_Factory::log("Customer direction is " . $customerDir, Zend_Log::DEBUG);
			if (!empty($params['pretend_bills']) && $pgResponse && $pgResponse['stage'] != 'Pending') {
				$payment->setPending(false);
			}

			switch ($customerDir) {
				case Billrun_DataTypes_PrePayment::DIR_FROM_CUSTOMER:
				case Billrun_DataTypes_PrePayment::DIR_TO_CUSTOMER:
					Billrun_Factory::log()->log("Handling payment with txid " . $transactionId . ", customer direction " . $customerDir, Zend_Log::DEBUG);
					$relatedBills = $postPayment->getRelatedBills();
					Billrun_Factory::log("Found " . count($relatedBills) . " related bills", Zend_Log::DEBUG);
					if (!empty($relatedBills)) {
						foreach ($relatedBills as $index => $bill) {
							Billrun_Factory::log("Processing related bill number " . $index, Zend_Log::DEBUG);
							$billId = $bill['id'];
							$billType = $bill['type'];
							$amountPaid = $bill['amount'];
							Billrun_Factory::log("Bill id " . $billId . ", type " . $billType . ", amount " . $amountPaid, Zend_Log::DEBUG);
							if ($this->isFileBasedCharge($params) && $payment->isWaiting()) {
								Billrun_Factory::log("Charge is file' based, and payment is waiting. Setting pending flag as true", Zend_Log::DEBUG);
								$payment->setPending(true);
							}

							if ($pgResponse && $pgResponse['stage'] != 'Pending') {
								Billrun_Factory::log("Pg response is not empty, and stage is not pending - setting pending flag as false", Zend_Log::DEBUG);
								$payment->setPending(false);
							}
							Billrun_Factory::log("Getting Updated bill", Zend_Log::DEBUG);
							$updatedBill = $postPayment->getUpdatedBill($billType, $billId);
							if ($customerDir === Billrun_DataTypes_PrePayment::DIR_FROM_CUSTOMER) {
								Billrun_Factory::log("Payment customer direction is " . $customerDir . ". attaching payment as bills' paying bill", Zend_Log::DEBUG);
								$updatedBill->attachPayingBill($payment, $amountPaid, (!empty($pgResponse) && empty($pgResponse['stage']) || ($this->isFileBasedCharge($params) && $payment->isWaiting())) ? 'Pending' : @$pgResponse['stage'])->save();
							} else {
								Billrun_Factory::log("Payment customer direction is " . $customerDir . ". attaching payment as bills' paid bill", Zend_Log::DEBUG);
								$updatedBill->attachPaidBill($payment->getType(), $payment->getId(), $amountPaid, $payment->getRawData())->save();
							}
						}
					} else {
						Billrun_Factory::log("Didn't find related bills. Saving payment as it is", Zend_Log::DEBUG);
						$payment->save();
					}
					break;
				default:
					Billrun_Factory::log("Customer direction is " . $customerDir . ". Default switch behavior was called", Zend_Log::DEBUG);
					Billrun_Factory::log()->log("Couldn't find payment direction for txid " . $transactionId, Zend_Log::DEBUG);
			}
			Billrun_Factory::log()->log("Paying unpaid bills using over paying/pending payments, for account " . $payment->getAccountNo(), Zend_Log::DEBUG);
			$this->account_involved_payments = Billrun_Bill::payUnpaidBillsByOverPayingBills($payment->getAccountNo(), true, $switch_links);
			Billrun_Factory::log("Returned account involved payment count is " . count($this->account_involved_payments), Zend_Log::DEBUG);

			if (!empty($gatewayDetails)) {
				$gatewayAmount = isset($gatewayDetails['amount']) ? $gatewayDetails['amount'] : $gatewayDetails['transferred_amount'];
			} else {
				$gatewayAmount = 0;
				Billrun_Factory::log('No $gatewayDetails variable defined to rerive amount from, assuming the amount is : 0', Zend_Log::WARN);
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

	protected function setUserFields(&$prePayment) {
		$payment = $prePayment->getPayment();
		$payment->setUserFields($prePayment->getData(), true);
	}

	protected function setPaymentForeignFields(&$payment, $account) {
		$foreignData = $this->getForeignFields(array('account' => $account));
		$payment->getPayment()->setForeignFields($foreignData);
	}

	protected function getForeignFieldsEntity() {
		return 'bills';
	}

	protected function getPaymentMostUpdatedData($prePayment, $params) {
		if (empty($params['after_save'])) {
			return array($prePayment->getPayment(), $prePayment->getData());
		}
		$switch_links = isset($params['switch_links']) ? $params['switch_links'] : Billrun_Bill::shouldSwitchBillsLinks();
		$payment = $prePayment->getPayment();
		$data = $prePayment->getData();
		$updated_payment = isset($this->account_involved_payments[$prePayment->getPayment()->getId()]) ? $this->account_involved_payments[$prePayment->getPayment()->getId()] : null;
		Billrun_Factory::log(("Switch links flag value is " . ($switch_links ? "true" : "false") . ",") . (is_null($updated_payment) ? (" and no updated payment value for payment " . $prePayment->getPayment()->getId()) : (" and updated payment was found for payment " . $prePayment->getPayment()->getId())), Zend_Log::DEBUG);
		if ($switch_links && !is_null($updated_payment)) {
			$data = array_merge($data, $updated_payment);
			$payment->setBillData($updated_payment);
		}
		return array($payment, $data);
	}

	/**
	 * Function to allow payment manager uses to be locked in account level
	 * @var array $options - has to include action & aid
	 */
	public function lockPaymentAction($options = []) {
		if (!isset($options['action']) || !isset($options['aid'])) {
			Billrun_Factory::log("Billrun_PaymentManager::Missing lock information, action/aid", Zend_Log::DEBUG);
			return false;
		}
		$this->lock_action = $options['action'];
		$this->locked_aid = $options['aid'];
		if (!$this->lock()) {
			Billrun_Factory::log("Action " . $options['action'] . " is already running for account " . $options['aid'], Zend_Log::NOTICE);
			return false;
		}
		return true;
	}

	/**
	 * Function to allow payment manager uses to be released in account level
	 * @var array $options - has to include action & aid
	 */
	public function releasePaymentAction($options = []) {
		if (!isset($options['action']) || !isset($options['aid'])) {
			Billrun_Factory::log("Billrun_PaymentManager::Missing release information, action/aid", Zend_Log::DEBUG);
			return false;
		}
		$this->lock_action = $options['action'];
		$this->locked_aid = $options['aid'];
		if (!$this->release()) {
			Billrun_Factory::log("Problem in releasing action " . $options['action'] . " for account " . $options['aid'], Zend_Log::ALERT);
			return false;
		}
		return true;
	}

	protected function getConflictingQuery() {
		return array('filtration' => $this->locked_aid);
	}

	protected function getInsertData() {
		return array(
			'action' => $this->lock_action,
			'filtration' => $this->locked_aid
		);
	}

	protected function getReleaseQuery() {
		return array(
			'action' => $this->lock_action,
			'filtration' => $this->locked_aid,
			'end_time' => array('$exists' => false)
		);
	}

}

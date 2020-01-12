<?php

/**
 * Payment management - in charge of the integration between bills and payment gateways
 */
class Billrun_PaymentManager {

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
	 * Handles synchronous payment (awaits response)
	 */
	public function paySync($method, $paymentsData, $params = []) {
		if (!Billrun_Bill_Payment::validatePaymentMethod($method, $params)) {
			return $this->handleError("Unknown payment method {$method}");
		}
		
		$prePayments =$this->preparePayments($method, $paymentsData, $params);
		if (!$this->savePayments($prePayments)) {
			return $this->handleError('Error encountered while saving the payments');
		}
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
		$prePayments = [];
		foreach ($paymentsData as $paymentData) {
			$prePayment = new Billrun_DataTypes_PrePayment($paymentData);
			$prePayment->setPayment($this->getPayment($method, $paymentData, $params));
			$this->handleInvoicesAndPaymentsAttachment($prePayment, $params);
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

		$payment = Billrun_Bill_Payment::getInstance($method, $paymentData);
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
				if (!empty($paymentData[$paymentDir][Billrun_DataTypes_PrePayment::BILL_TYPE_INVOICE])) {
					$this->attachInvoicesAndPayments(Billrun_DataTypes_PrePayment::BILL_TYPE_INVOICE, $prePayment, $params);
				}
				if (!empty($paymentData[$paymentDir][Billrun_DataTypes_PrePayment::BILL_TYPE_RECEIPT])) {
					$this->attachInvoicesAndPayments(Billrun_DataTypes_PrePayment::BILL_TYPE_RECEIPT, $prePayment, $params);
				}
				break;
			default: // one of fc/tc
				$this->attachAllInvoicesAndPayments($prePayment, $dir);
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
		$relatedBills = $prePayment->getRelatedBills($billType);
		if (count($relatedBills) != count($billsToHandle)) {
			throw new Exception("Unknown {$prePayment->getDisplayType($billType)}/s for account {$prePayment->getAid()}");
		}

		if (($prePayment->getAmount() - array_sum($billsToHandle)) <= -Billrun_Bill::precision) {
			throw new Exception("{$prePayment->getAid()}: Total to pay is less than the subtotals");
		}

		foreach ($relatedBills as $billData) {
			$bill = $prePayment->getBill($billType, $billData);
			if ($bill->isPaid()) {
				return $this->handleError("{$prePayment->getDisplayType($billType)} {$bill->getId()} already paid");
			}

			$billAmount = $prePayment->getBillAmount($billType, $bill->getId());
			if (!is_numeric($billAmount)) {
				return $this->handleError("Illegal amount for {$prePayment->getDisplayType($billType)} {$bill->getId()}");
			}

			$billAmount = floatval($billsToHandle[$bill->getId()]);
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
				$prePayment->setData([$paymentDir, $billType, $billId], $amount);
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
		
		return $involvedAccounts;
	}
	
	/**
	 * save payments to DB
	 * 
	 * @param array $prePayments - array of Billrun_DataTypes_PrePayment
	 * @return boolean
	 */
	protected function savePayments($prePayments) {
		$payments = [];
		foreach ($prePayments as $prePayment) {
			$payments[] = $prePayment->getPayment();
		}
		
		$ret = Billrun_Bill_Payment::savePayments($payments);
		if (!$ret || empty($ret['ok'])) {
			return false;
		}

		return $ret;
	}


	protected function handleError($errorMessage, $logLevel = Billrun_Log::CRIT) {
		Billrun_Factory::log($errorMessage, $logLevel);
		throw new Exception($errorMessage);
	}

}

<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class holds preparation data needed for payment
 * 
 * @package  DataTypes
 */
class Billrun_DataTypes_PrePayment {

	const DIR_FROM_CUSTOMER = 'fc';
	const DIR_TO_CUSTOMER = 'tc';
	const BILL_TYPE_INVOICE = 'inv';
	const BILL_TYPE_RECEIPT = 'rec';
	const PAY_DIR_PAYS = 'pays';
	const PAY_DIR_PAID_BY = 'paid_by';
	const PAY_DIR_NONE = 'none';
	const BILL_TYPE_DISP_INVOICE = 'Invoice';
	const BILL_TYPE_DISP_RECEIPT = 'Payment';

	/**
	 * payment raw data
	 * @var array
	 */
	private $data = [];

	/**
	 * due amount
	 * @var float
	 */
	private $amount = null;

	/**
	 * payment gateway settings
	 * @var array
	 */
	private $pgSetting = [];

	/**
	 * bills handled by the payment
	 * @var array
	 */
	private $updatedBills = ['inv' => [], 'rec' => []];

	/**
	 * payment object
	 * @var Billrun_Bill_Payment
	 */
	private $payment = null;

	/**
	 * payment direction - one of pays/paid_by
	 * @var type 
	 */
	private $paymentDir = null;

	/**
	 * AID involved in the payment process
	 * @var int
	 */
	private $aid = null;

	public function __construct($paymentData) {
		$this->data = $paymentData;
	}

	/**
	 * get payment raw data
	 * 
	 * @return array
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 * Set raw data
	 * 
	 * @param mixed $path - path to update (string or array)
	 * @param mixed $value
	 */
	public function setData($path, $value) {
		Billrun_Util::setIn($this->data, $path, $value);
	}

	public function getPgSettings() {
		return $this->pgSetting;
	}

	public function setPgSttings($pgSettings) {
		$this->pgSetting = is_array($pgSettings) ? $pgSettings : [];
	}

	/**
	 * add updated bill
	 * 
	 * @param string $billType - one of inv/rec
	 * @param Billrun_Bill $bill
	 */
	public function addUpdatedBill($billType, $bill) {
		$this->updatedBills[$billType][$bill->getId()] = $bill;
	}

	/**
	 * get all updated bills of type
	 * 
	 * @param string $billType - one of inv/rec
	 * @return array of Billrun_Bill
	 */
	public function getUpdatedBills($billType) {
		if (empty($billType)) {
			return false;
		}
		return $this->updatedBills[$billType];
	}

	/**
	 * get updated bill by it's ID
	 * 
	 * @param string $billType - one of inv/rec
	 * @param string $billId
	 * @return Billrun_Bill
	 */
	public function getUpdatedBill($billType, $billId) {
		return Billrun_Util::getIn($this->updatedBills, [$billType, $billId]);
	}

	/**
	 * get bills related to the payment
	 * 
	 * @param string $billType - one of inv/rec
	 * @param array $billData
	 * @return array
	 */
	public function getRelatedBills($billType = '') {
		$query = [
			'aid' => $this->getAid(),
		];
		$updatedBills = $this->getUpdatedBills($billType);
		switch ($this->getPaymentDirection()) {
			case self::PAY_DIR_PAYS:
				$query['invoice_id'] = [
					'$in' => Billrun_Util::verify_array(array_keys($updatedBills), 'int'),
				];
				break;
			case self::PAY_DIR_PAID_BY:
				$query['txid'] = [
					'$in' => array_keys($updatedBills),
				];
			default:
				$customerDir = $this->getCustomerDirection();
				if ($customerDir == self::DIR_FROM_CUSTOMER) {
					return Billrun_Bill::getUnpaidBills($query);
				}

				if ($customerDir == self::DIR_TO_CUSTOMER) {
					return Billrun_Bill::getOverPayingBills($query);
				}
				return null;
		}

		switch ($billType) {
			case self::BILL_TYPE_INVOICE:
				return Billrun_Bill_Invoice::getInvoices($query);
			case self::BILL_TYPE_RECEIPT:
				return Billrun_Bill_Payment::queryPayments($query);
			default:
				return null;
		}
	}

	/**
	 * get bill amount left to pay / being paid
	 * 
	 * @param Billrun_Bill $bill
	 * @return float
	 */
	public function getBillAmountLeft($bill) {
		switch ($this->getPaymentDirection()) {
			case self::PAY_DIR_PAYS:
				return $bill->getLeftToPay();
			case self::PAY_DIR_PAID_BY:
				return $bill->getLeft();
			default:
				$customerDir = $this->getCustomerDirection();
				if ($customerDir == self::DIR_FROM_CUSTOMER) {
					return $bill->getLeftToPay();
				}

				if ($customerDir == self::DIR_TO_CUSTOMER) {
					return $bill->getLeft();
				}

				return 0;
		}
	}

	/**
	 * get bill object from bill data
	 * 
	 * @param string $billType - one of inv/rec
	 * @param array $billData
	 * @return Billrun_Bill
	 */
	public function getBill($billType, $billData) {
		if ($billData instanceof Billrun_Bill) {
			return $billData;
		}

		switch ($billType) {
			case self::BILL_TYPE_INVOICE:
				return Billrun_Bill_Invoice::getInstanceByData($billData);
			case self::BILL_TYPE_RECEIPT:
				return Billrun_Bill_Payment::getInstanceByData($billData);
			default:
				$customerDir = $this->getCustomerDirection();
				if ($customerDir == self::DIR_FROM_CUSTOMER) {
					return Billrun_Bill::getInstanceByData($billData);
				}
				return null;
		}
	}

	/**
	 * @return Billrun_Bill_Payment
	 */
	public function getPayment() {
		return $this->payment;
	}

	public function setPayment($payment) {
		$this->payment = $payment;
	}

	/**
	 * get payment direction
	 * 
	 * @return string (one of pays/paid_by)
	 */
	public function getPaymentDirection() {
		if (is_null($this->paymentDir)) {
			if (!empty($this->data[self::PAY_DIR_PAYS])) {
				$this->paymentDir = self::PAY_DIR_PAYS;
			} else if (!empty($this->data[self::PAY_DIR_PAID_BY])) {
				$this->paymentDir = self::PAY_DIR_PAID_BY;
			} else {
				$this->paymentDir = self::PAY_DIR_NONE;
			}
		}

		return $this->paymentDir;
	}

	/**
	 * get direction from/to customer
	 * 
	 * @return string (one of fc/tc)
	 */
	public function getCustomerDirection() {
		return Billrun_Util::getIn($this->getData(), 'dir');
	}

	/**
	 * get bill type name(for log/display purposes)
	 * 
	 * @return string
	 */
	public function getDisplayType($billType) {
		switch ($billType) {
			case self::BILL_TYPE_INVOICE:
				return self::BILL_TYPE_DISP_INVOICE;
			case self::BILL_TYPE_RECEIPT:
				return self::BILL_TYPE_DISP_RECEIPT;
			default:
				return $billType;
		}
	}

	/**
	 * get the bills the payment should handle
	 * 
	 * @return array of bills handled by the payment
	 */
	public function getBillsToHandle($billType) {
		$paymentDir = $this->getPaymentDirection();
		return Billrun_Util::getIn($this->getData(), [$paymentDir, $billType], []); // currently it is only possible to specifically pay invoices only and not payments
	}

	/**
	 * get payment aid
	 * @return int
	 */
	public function getAid() {
		if (is_null($this->aid)) {
			$this->aid = intavl($this->data['aid']);
		}

		return $this->aid;
	}

	/**
	 * get payment amount
	 * @return float
	 */
	public function getAmount() {
		if (is_null($this->amount)) {
			$this->amount = floatval($this->data['amount']);
		}

		return $this->amount;
	}

	/**
	 * get amount to pay by a given bill
	 * 
	 * @param int $billId
	 * @return float
	 */
	public function getBillAmount($billType, $billId) {
		$amount = Billrun_Util::getIn($this->getData(), [$this->getPayDirection(), $billType, $billId], 0);
		if (!is_numeric($amount)) {
			return false;
		}
		return floatval($amount);
	}

}

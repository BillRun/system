<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * BillRun Payment Denials class
 *
 * @package  Billrun
 * @since    5.9
 */
class Billrun_Bill_Payment_Denial extends Billrun_Bill_Payment {

	protected $method = 'denial';

	public function __construct($options) {
		$adjustedOptions = $this->adjustDenialOptions($options);
		parent::__construct($adjustedOptions);
	}
	
	protected function adjustDenialOptions($options) {
		$newOptions = array();
		$newOptions['amount'] = abs($options['amount']);
		$newOptions['due'] = -$options['amount'];
		$newOptions['aid'] = $options['aid'];
		$newOptions['denial'] = $options;
		return $newOptions;
	}
	
	/**
	 * Copy paid_by and paid objects from the original payment to the created denial.
	 * @param $payment- the original payment.
	 */
	protected function copyLinks($payment) {
		$rawPayment = $payment->getRawData();
		$this->data['linked_bills'] = isset($rawPayment['pays']) ? $rawPayment['pays'] : $rawPayment['paid_by'];
		$this->data['linked_rec'] = $payment->getId();
	}
	
}
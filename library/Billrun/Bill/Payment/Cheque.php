<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * BillRun Payment class
 *
 * @package  Billrun
 * @since    5.0
 */
class Billrun_Bill_Payment_Cheque extends Billrun_Bill_Payment_Transfer {

	protected $method = 'cheque';

	public function __construct($options) {
		parent::__construct($options);
		if ($this->getDir() == 'fc' && isset($options['deposit_slip'])) {
			$this->data['deposit_slip'] = $options['deposit_slip'];
		} else if ($this->getDir() == 'tc' && isset($options['cheque_no'])) {
			$this->data['cheque_no'] = $options['cheque_no'];
		} else {
			throw new Exception('Billrun_Bill_Payment_Cheque: Insufficient options supplied.');
		}
	}

}
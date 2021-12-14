<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * BillRun Payment Automatic class
 *
 * @package  Billrun
 * @since    5.0
 */
class Billrun_Bill_Payment_Automatic extends Billrun_Bill_Payment {

	protected $method = 'automatic';

	public function __construct($options) {
		parent::__construct($options);
		if (!isset($options['_id']) && !isset($options['cancel']) && Billrun_Util::getFieldVal($options['waiting_for_confirmation'], true)) {
			$this->data['waiting_for_confirmation'] = true;
		} else if (isset($options['cancel'])) {
			$this->data['waiting_for_confirmation'] = false;
		}
	}

}

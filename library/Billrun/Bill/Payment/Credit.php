<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * BillRun Payment Credit class
 *
 * @package  Billrun
 * @since    5.0
 */
class Billrun_Bill_Payment_Credit extends Billrun_Bill_Payment {

	protected $method = 'credit';

	protected $known_sources;

	public function __construct($options) {
		$this->known_sources = Billrun_Factory::config()->getConfigValue('payments.credit.known_sources', array('POS', 'web'));
		parent::__construct($options);
		if (!isset($options['source']) || !in_array($options['source'], $this->known_sources)) {
			throw new Exception('Billrun_Bill_Payment_Credit: Insufficient options supplied.');
		}
	}
}
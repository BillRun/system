<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing interface aggregator class
 *
 * @package  calculator
 * @since    1.0
 */
abstract class aggregator extends base {

	/**
	 * execute aggregate
	 */
	abstract public function aggregate();

	/**
	 * load the data to aggregate
	 */
	abstract public function load();

	abstract protected function updateBillingLine($subscriber_id, $item);

	abstract protected function updateBillrun($billrun, $row);

	protected function loadSubscriber($phone_number, $time) {
		$object = new stdClass();
		$object->phone_number = $phone_number;
		$object->time = $time;
		return $object;
	}

	abstract protected function save($data);
}
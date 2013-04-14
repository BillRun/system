<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing subscriber class based on database
 *
 * @package  Billing
 * @since    0.5
 */
class Billrun_Subscriber_Db extends Billrun_Subscriber {

	/**
	 * method to load subsbscriber details
	 * 
	 * @param array $params load by those params 
	 */
	public function load($params) {
		return true;
	}

	/**
	 * method to save subsbscriber details
	 */
	public function save() {
		return true;
	}

	/**
	 * method to delete subsbscriber entity
	 */
	public function delete() {
		return true;
	}

}
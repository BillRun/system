<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi model for balance update by prepaid include
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Action_Update_Balance_Id extends Models_Action_Update_Balance_Abstract {

	/**
	 * the update method type
	 * @var string
	 */
	protected $updateType = 'Id';
	
	/**
	 * @todo
	 */
	public function update() {
		
	}

	/**
	 * @todo
	 */
	protected function preload() {
		
	}

	/**
	 * create row to track the balance update
	 * @todo
	 */
	public function createTrackingLines() {
		
	}

}

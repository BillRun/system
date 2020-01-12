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
class Billrun_Balance_Update_Id extends Billrun_Balance_Update_Prepaidinclude {

	/**
	 * the update method type
	 * @var string
	 */
	protected $updateType = 'Id';
	
	 protected function init() {
		$this->query = array(
			'_id' => $this->data['_id'],
		);
		$this->before = $this->data;
	}

}

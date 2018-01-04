<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi services model for services entity
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Services extends Models_Entity {
	
	protected $errorCode = 999999;

	protected function init($params) {
		parent::init($params);
		$actionsExcludeValidation = array('move', 'close');
		if (!in_array($this->action, $actionsExcludeValidation)) {
			$this->validatePrice();
		}
	}
	
	/**
	 * Verfiy services has all price parameters required.
	 */
	protected function validatePrice() {
		$priceIntervals = $this->update['price'];
		if (empty($priceIntervals)) {
			throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Service must have price value');
		}
		
		foreach ($priceIntervals as $price) {
			if (!isset($price['from']) || $price['from'] === '' || 
				!isset($price['to']) || $price['to'] === '') {
				throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Service missing cycles parameters');
			}
			if (!isset($price['price']) || $price['price'] === '') {
				throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Service missing price parameter');
			}
		}
		
		return true;
	}

}

<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi model for exchange rates entity
 *
 * @package  Billapi
 * @since    5.5
 */
class Models_Exchangerates extends Models_Entity {

	public function update() {
		$this->verifyEditable();
		return parent::update();
	}

	public function closeandnew() {
		$this->verifyEditable();
		return parent::closeandnew();
	}
	
	/**
	 * verify that the subject of update is editable (not set to auto_sync)
	 *
	 * @return boolean
	 */
	protected function verifyEditable() {
		if (empty($this->before)) {
			return;
		}
		
		if (!Billrun_CurrencyConvert_Manager::canEditCurrency($this->before['target_currency'])) {
			throw new Billrun_Exceptions_Api(80880, [], 'Currency cannot be updated. It is set to auto_sync');
		}
		
		return true;
	}
}

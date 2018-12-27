<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi accounts model for subscribers entity
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Accounts extends Models_Entity {

	protected function init($params) {
		parent::init($params);
		$this->update['type'] = 'account';
	}

	public function get() {
		$this->query['type'] = 'account';
		return parent::get();
	}

	/**
	 * method to add entity custom fields values from request
	 * 
	 * @param array $fields array of field settings
	 */
	protected function getCustomFields($update = array()) {
		$customFields = parent::getCustomFields();
		$accountFields = Billrun_Factory::config()->getConfigValue($this->collectionName . ".account.fields", array());
		return array_merge($accountFields, $customFields);
	}
	
	public function getCustomFieldsPath() {
		return $this->collectionName . ".account.fields";
	}

}

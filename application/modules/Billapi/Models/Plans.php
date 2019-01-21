<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi services model for plans entity
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Plans extends Models_Entity {
	
	/**
	 * method to add entity custom fields values from request
	 * 
	 * @param array $fields array of field settings
	 */
	protected function getCustomFields($update = array()) {
		$customFields = parent::getCustomFields();
		$plays = Billrun_Util::getIn($update, 'play', Billrun_Util::getIn($this->before, 'play', []));
		return Billrun_Utils_Plays::filterCustomFields($customFields, $plays);
	}

}

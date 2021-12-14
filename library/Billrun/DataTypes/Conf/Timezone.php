<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Wrapper class for a complex timezone value object
 */
class Billrun_DataTypes_Conf_Timezone extends Billrun_DataTypes_Conf_String {

	public function __construct($obj) {
		parent::__construct($obj);
	}

	public function validate() {
		if (!parent::validate()) {
			return false;
		}

		// Check if valid timezone
		return in_array($this->val, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC));
	}

}

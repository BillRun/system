<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Wrapper class for a complex shared path value object
 */
class Billrun_DataTypes_Conf_Sharedpath extends Billrun_DataTypes_Conf_Base {
	public function __construct($obj) {
		$path = $obj['v'];
		// Convert to shared path.
		$sharedPath = Billrun_Util::getBillRunSharedFolderPath($path);
		
		$this->val = $sharedPath;
	}
	
	public function validate() {
		// TODO: Should we check here file exists???? I am not sure, it might
		// not have been created yet.
		if(empty($this->val) || !is_string($this->val)) {
			return false;
		}
		
		return true;
	}
}

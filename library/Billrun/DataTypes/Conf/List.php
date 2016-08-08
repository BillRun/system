<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Wrapper class for a complex list value object
 */
class Billrun_DataTypes_Conf_List extends Billrun_DataTypes_Conf_Base {
	use Billrun_Traits_Api_UserPermissions;
	
	protected $list = array();
	protected $template = array();
	
	/**
	 * Unique indicator for list memeber, to check if adding new 
	 * value or editting existing.
	 * @var string 
	 */
	protected $matchKey = "";
	public function __construct($obj) {
		$this->val = $obj['v'];
		$this->list = $obj['list'];
		$this->matchKey = $obj['k'];
		if(isset($obj['template'])) {
			$this->template = $obj['template'];
		}
	}
	
	public function validate() {
		if( empty($this->val)				   || 
		   !is_array($this->val)			   || 
		    empty($this->matchKey)			   || 
		   !is_string($this->matchKey)		   || 
		   !is_array($this->list)) {
			return false;
		}
		
		if(!$this->validateMendatoryFields()) {
			return false;
		}
		
		if(!$this->validateKnownFields()) {
			return false;
		}
		
		if(!$this->validateEditable()) {
			return false;
		}
		
		return true;
	}

	protected function validateEditable() {
		// Check if the value already exists.
		$name = $this->val[$this->matchKey];
		$found = array_filter($this->list, function($k,$v) use($name) {
			return $k == $this->matchKey && $v == $name;
		}, ARRAY_FILTER_USE_BOTH);
		
		if(!empty($found)) {
			// TODO: Use the permissions to check if the document is editable, if the
			// document is editable on system permissions, check for admin permissions 
			// of the user.
			return false;
		}
		
		return true;
	}
	
	protected function validateMendatoryFields() {
		foreach ($this->template as $field => $mendatory) {
			if($mendatory && !isset($this->val[$field])) {
				return false;
			}
		}
		return true;
	}
	protected function validateKnownFields() {
		$diff = array_diff(array_keys($this->val), array_keys($this->template));
		return empty($diff);
	}
	
	public function value() {
		$this->list[] = $this->val;
		return $this->list;
	}


	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_ADMIN;
	}

}

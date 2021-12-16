<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
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

	public function __construct(&$obj) {
		$this->val = &$obj['v'];
		$this->list = &$obj['list'];
		$this->matchKey = $obj['k'];
		if (isset($obj['template'])) {
			$this->template = $obj['template'];
		}
	}

	public function validate() {
		if (empty($this->val) ||
			!is_array($this->val) ||
			empty($this->matchKey) ||
			!is_string($this->matchKey) ||
			!is_array($this->list)) {
			return false;
		}

		if (!$this->validateMendatoryFields()) {
			return false;
		}

		if (!$this->validateKnownFields()) {
			return false;
		}

		if (!$this->validateEditable()) {
			return false;
		}

		// Set in the list.
		if ($this->val !== null) {
			$this->list[] = $this->val;
			$this->val = null;
		}

		return true;
	}

	protected function validateEditable() {
		// Check if the value already exists.
		$name = $this->val[$this->matchKey];
		$editing = array();
		$foundBlock = null;
		foreach ($this->list as &$block) {
			$found = array_filter($block, function($v, $k) use($name) {
				return $k == $this->matchKey && $v == $name;
			}, ARRAY_FILTER_USE_BOTH);
			if (!empty($found)) {
				// TODO: Do something with the 'system' magic value, magic values
				// are bad.
				$editing['block'] = (isset($block['system']) && $block['system']);
				$foundBlock = &$block;
				break;
			}
		}

		if (empty($editing)) {
			return true;
		}

		if (isset($editing['block']) && $editing['block']) {
			return false;
		}
		// Remove the existing object.
		$foundBlock = $this->val;
		$this->val = null;

		return true;
	}

	protected function validateMendatoryFields() {
		foreach ($this->template as $field => $mendatory) {
			if ($mendatory && 
				((!isset($this->val[$field])) ||
				((is_array($this->val[$field])) && empty($this->val[$field])) ||
				(is_string($this->val[$field]) && empty(trim($this->val[$field]))))){
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
		return $this->list;
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_ADMIN;
	}

}

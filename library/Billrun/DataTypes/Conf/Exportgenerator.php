<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Wrapper class for an export generator object.
 */
class Billrun_DataTypes_Conf_Exportgenerator extends Billrun_DataTypes_Conf_Base {

	protected $listData;
	protected $names;
	
	public function __construct(&$obj) {
		$this->val = &$obj['v'];
		$this->names = Billrun_Util::getFieldVal(&$obj['names'], array());
		$type = $this->val['type'];
		$template = Billrun_Util::getFieldVal(&$obj['template'][$type], array());
		$matchKey = 'field';
		$listData['k'] = $matchKey;
		$listData['template'] = $template;
		$this->listData = $listData;
	}

	public function validate() {
		if (empty($this->val) ||
			!is_array($this->val) ||
			empty($this->listData['template']) ||
			!isset($this->val['name']) ||
			!is_string($this->val['name'])) {
			return false;
		}

		// Validate the name.
		if(in_array($this->val['name'], $this->names)) {
			Billrun_Factory::log("Export generator " . $this->val['name'] . " already exists");
			return false;
		}
		
		// Get the segments.
		$segments = Billrun_Util::getFieldVal($this->val['segments'], array());
		
		// Does the list have a value.
		if(empty($segments) || !is_array($segments)) {
			return false;
		}
		
		if(!$this->validateSegments($segments)) {
			return false;
		}
		
		return true;
	}

	protected function addName($name) {
		/**
		 * @var Mongodloid_Collection  $coll
		 */
		$coll = Billrun_Factory::db()->configCollection();
		
		// Update name query.
		$update = array('$push' => array('export_generator.names' => $name));
		
		// Add the name
		$coll->update(array(), $update);
	}
	
	protected function validateSegments($segments) {
		$listData = $this->listData;
		
		// Validate each segment.
		foreach ($segments as $segValue) {
			$listData['v'] = $segValue;
			$list = new Billrun_DataTypes_Conf_List($listData);
			if(!$list->validate()) {
				return false;
			}
		}
		return true;
	}
	
	public function value() {
		return $this->val;
	}
}

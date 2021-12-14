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

	/**
	 * Internal list of objects
	 * @var array
	 */
	protected $list = array();

	public function __construct(&$obj) {
		$this->val = &$obj['v'];
		$this->list = &$obj['list'];
	}

	/**
	 * Validate the value
	 * @return boolean
	 */
	public function validate() {
		if (empty($this->val) ||
				!is_array($this->val) ||
				!is_array($this->list) ||
				!isset($this->val['name']) ||
				!is_string($this->val['name']) ||
				!isset($this->val['file_type']) ||
				!is_string($this->val['file_type'])) {
			return false;
		}

		if (!$this->validateName()) {
			return false;
		}

		if (!$this->validateSegments()) {
			return false;
		}

		$this->setToList();
		return true;
	}

	/**
	 * Set the validated value to the list of values.
	 */
	protected function setToList() {
		// Set in the list.
		if ($this->val !== null) {
			$this->list[] = $this->val;
			$this->val = null;
		}
	}

	/**
	 * Handle name
	 * @return boolean
	 */
	protected function validateName() {
		$name = $this->val['name'];

		// Validate the name.
		foreach ($this->list as $generator) {
			if ($generator['name'] == $name) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate the segments
	 * @return boolean
	 */
	protected function validateSegments() {
		// Get the segments.
		$segments = Billrun_Util::getFieldVal($this->val['segments'], array());

		// Does the list have a value.
		if (empty($segments) || !is_array($segments)) {
			return false;
		}

		// Get the available fields.
		$fields = $this->getFields();
		if (empty($fields)) {
			return false;
		}

		// Validate each segment.
		foreach ($segments as $segValue) {
			if (!$this->validateSegment($segValue, $fields)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Validate a single segment.
	 * @param type $segment
	 * @param type $fields
	 * @return boolean
	 */
	protected function validateSegment($segment, $fields) {
		foreach ($segment as $key => $value) {
			if (!in_array($key, $fields)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Get the available fields according to the input processor.
	 */
	protected function getFields() {
		$fileTypes = Billrun_Factory::config()->getConfigValue('file_types');
		$type = $this->val['file_type'];

		$fields = array();
		foreach ($fileTypes as $record) {
			if ($record['file_type'] != $type) {
				continue;
			}

			$fields = Billrun_Util::getFieldVal($record['parser']['structure'], array());
			break;
		}

		return $fields;
	}

	public function value() {
		return $this->list;
	}

}

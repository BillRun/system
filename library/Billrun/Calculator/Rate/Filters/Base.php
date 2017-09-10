<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing basic base filter
 *
 * @package  calculator
 * @since 5.0
 */
class Billrun_Calculator_Rate_Filters_Base {
	
	public $params = array();
	
	public function __construct($params = array()) {
		$this->params = $params;
	}

	public function updateQuery(&$match, &$additional, &$group, &$additionalAfterGroup, &$sort, $row) {
		
		$this->updateMatchQuery($match, $row);
		$a = $this->updateAdditionalQuery($row);
		if ($a) {
			$additional[] = $a;
		}
		$this->updateGroupQuery($group, $row);
		$a2 = $this->updateAdditionaAfterGrouplQuery($row);
		if ($a2) {
			$additionalAfterGroup[] = $a2;
		}
		$this->updateSortQuery($sort, $row);
	}
	
	protected function getRowFieldValue($row, $field, $regex = '') {
		if ($field === 'computed') {
			return $this->getComputedValue($row);
		}
		if (isset($row['uf'][$field])) {
			return $this->regexValue($row['uf'][$field], $regex);
		}
		
		if (isset($row[$field])) {
			return $this->regexValue($row[$field], $regex);
		}
		Billrun_Factory::log("Cannot get row value for rate. field: " . $field ." stamp: " . $row['stamp'], Zend_Log::NOTICE);
		return '';
	}
	
	protected function regexValue($value, $regex) {
		if (empty($regex) || !Billrun_Util::isValidRegex($regex)) {
			return $value;
		}
		
		return preg_replace($regex,'',$value);	
	}
	
	/**
	 * Gets a computed (regex/condition) field value
	 * 
	 * @param array $row
	 * @return value after regex applying, in case of condition - 1 if the condition is met, 0 otherwise
	 */
	protected function getComputedValue($row) {
		if (!isset($this->params['computed'])) {
			return '';
		}
		$computedType = Billrun_Util::getIn($this->params, array('computed', 'type'), 'regex');
		$firstValKey = Billrun_Util::getIn($this->params, array('computed', 'line_keys', 0, 'key'), '');
		$firstValRegex = Billrun_Util::getIn($this->params, array('computed', 'line_keys', 0, 'regex'), '');
		$firstVal = $this->getRowFieldValue($row, $firstValKey, $firstValRegex);
		if ($computedType === 'regex') {
			return $firstVal;
		}
		$secondValKey = Billrun_Util::getIn($this->params, array('computed', 'line_keys', 1, 'key'), '');
		$secondValRegex = Billrun_Util::getIn($this->params, array('computed', 'line_keys', 1, 'regex'), '');
		$secondVal = $this->getRowFieldValue($row, $secondValKey, $secondValRegex);
		$data = array('first_val' => $firstVal);
		$query = array(
			'first_val' => array(
				$this->params['computed']['operator'] => $secondVal,
			),
		);
		
		return Billrun_Utils_Arrayquery_Query::exists($data, $query) ? '1' : '0';
	}
	
	protected function updateMatchQuery(&$match, $row) {
	}
	
	protected function updateAdditionalQuery($row) {
	}
	
	protected function updateGroupQuery(&$group, $row) {
	}
	
	protected function updateAdditionaAfterGrouplQuery($row) {
	}
	
	protected function updateSortQuery(&$sort, $row) {
	}
}

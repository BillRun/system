<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing basic base filter
 *
 * @package  calculator
 * @since 5.10
 */
class Billrun_EntityGetter_Filters_Base {

	public $params = array();

	protected $specialQueries = array(
		'$exists' => array('$exists' => 1),
		'$existsFalse' => array('$exists' => 1),
		'$isTrue' => array('$exists' => true, '$eq' => true),
		'$isFalse' => array('$exists' => true, '$eq' => false),
	);

	protected $datePreFunctions = array(
		'minute_of_hour' => 'i' , // with leading zeros
		'hour_of_day'    => 'H' , // with leading zeros
		'time_of_day'    => 'Hi', // time of day (HHMM)
		'day_of_week'    => 'N' , // 1 (for Monday) through 7 (for Sunday); without leading zeros
		'day_of_month'   => 'd' , // with leading zeros
		'month_of_year'  => 'm' , // with leading zeros
		'month_and_day'  => 'md' , // with leading zeros
	);

	/**
	 * whether or not this can handle the query
	 * @var boolean
	 */
	protected $canHandle = true;

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
		
		$ufVal = Billrun_Util::getIn($row, 'uf.' . $field, null);
		if (!is_null($ufVal)) {
			return $this->regexValue($ufVal, $regex);
		}
		
		if (isset($row['foreign'][$field])) {
			if (is_array($row['foreign'][$field])) {
				return array_map(function ($element) use($regex) {
					return $this->regexValue($element, $regex);
				}, $row['foreign'][$field]) ;
			}
			return $this->regexValue($row['foreign'][$field], $regex);
		}
        
		$val = Billrun_Util::getIn($row, $field, null);
		if (!is_null($val)) {
			return $this->regexValue($val, $regex);
		}

		Billrun_Factory::log("Cannot get row value for entity. field: " . $field . " stamp: " . $row['stamp'], Zend_Log::NOTICE);
		return '';
	}

	protected function regexValue($value, $regex) {
		if (!empty($this->params['preFunction'])) {
			$value = $this->applyPreFunction($value, $this->params['preFunction']);
		}
		if (empty($regex) || !Billrun_Util::isValidRegex($regex)) {
			return $value;
		}

		return preg_replace($regex, '', $value);
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
		$preFunction = Billrun_Util::getIn($this->params, array('computed', 'line_keys', 0, 'preFunction'), false);
		if ($preFunction) {
			$firstVal = $this->applyPreFunction($firstVal, $preFunction);
		}

		if ($computedType === 'regex') {
			return $firstVal;
		}
		$operator = $this->params['computed']['operator'];
		$secondValKey = Billrun_Util::getIn($this->params, array('computed', 'line_keys', 1, 'key'), '');
		if ($operator === '$regex') { // in case of hard coded value
			$secondVal = $secondValKey;
		} else {
			$secondValRegex = Billrun_Util::getIn($this->params, array('computed', 'line_keys', 1, 'regex'), '');
			$secondVal = $this->getRowFieldValue($row, $secondValKey, $secondValRegex);
		}
		
		$data = array('first_val' => $firstVal);
		$query = array(
			'first_val' => array(
				$operator => $secondVal,
			),
		);
		if (!empty($this->specialQueries[$operator]) ) {
			$data = $row;
			$op = in_array($operator, []) ? '$and' : '$or';
			$query = array(
				$op => [
					[$firstValKey => $this->specialQueries[$operator]],
					['uf.'.$firstValKey => $this->specialQueries[$operator]],
				]
			);
		}

		$res = Billrun_Utils_Arrayquery_Query::exists($data, $query);
		if($operator === '$existsFalse') {
			$res = !$res;
		}
		return $this->getComputedValueResult($row, $res);
	}

	/**
	 * returns a value for a computed line key (the value received by a calculation on the CDR fields)
	 * 
	 * @param array $row
	 * @param boolean $conditionRes
	 * @return value to compare
	 */
	protected function getComputedValueResult($row, $conditionRes) {
		if (Billrun_Util::getIn($this->params, array('computed', 'must_met'), false) && !$conditionRes) {
			$this->canHandle = false;
			return false;
		}

		$projectionKey = $conditionRes ? 'on_true' : 'on_false';
		$key = Billrun_Util::getIn($this->params, array('computed', 'projection', $projectionKey, 'key'), '');
		switch ($key) {
			case ('condition_result'):
				return $conditionRes;
			case ('hard_coded'):
				return Billrun_Util::getIn($this->params, array('computed', 'projection', $projectionKey, 'value'), '');
			default:
				$regex = Billrun_Util::getIn($this->params, array('computed', 'projection', $projectionKey, 'regex'), '');
				return $this->getRowFieldValue($row, $key, $regex);
		}
	}

	/**
	 * method to apply pre-function on a value before use it on the mapping
	 * 
	 * @param string $val the value that function will be applied on
	 * @param string $preFunction the pre-defined function to be applied on the value
	 * 
	 * @return string the value after applying after running the function (if pre-defined)
	 */
	protected function applyPreFunction($val, $preFunction) {

		if (isset($this->datePreFunctions[$preFunction])) {
			if (isset($val->sec)) { // this is ISODate object
				$dateFuncInput = $val->sec;
			} else if (strlen($val) > 11) { // this is not unix timestamp (unless in this millennium) => ISO date string
				$dateFuncInput = strtotime($val);
			} else { // unix timestamp
				$dateFuncInput = $val;
			}
			return date($this->datePreFunctions[$preFunction], $dateFuncInput);
		}

		return $val;
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

	/**
	 * Whether or not the current filter can handle the query building
	 * currently, will return false only if a must_met condition is set on the CDR fields and the values are not equal
	 * 
	 * @return boolean
	 */
	public function canHandle() {
		return $this->canHandle;
	}

}

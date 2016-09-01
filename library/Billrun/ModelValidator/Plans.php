<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a validator for Plan.
 *
 * @since 5.1
 */
class Billrun_ModelValidator_Plans extends Billrun_ModelValidator_Base {

	/**
	 * Validate additional allowed names of plans which are not plans' names
	 * 
	 * @param type $data
	 * @return boolean
	 */
	protected function validateName($data) {
		if (!isset($data['name'])) {
			return false;
		}
		$name = strtolower($data['name']);
		return !in_array($name, array('base', 'groups'));
	}

	/**
	 * Validates price object in a Plan object
	 * 
	 * @param type $data
	 * @return true on succes, error message on failure
	 */
	protected function validatePrice($data) {
		foreach ($data['price'] as $price) {
			if (!isset($price['price']) || !isset($price['from']) || !isset($price['to'])) {
				return "Illegal price structure";
			}

			$typeFields = array(
				'price' => 'float',
				'from' => 'date',
				'to' => 'date',
			);
			$validateTypes = $this->validateTypes($price, $typeFields);
			if ($validateTypes !== true) {
				return $validateTypes;
			}
		}

		return true;
	}

	/**
	 * Validate recurrence field structure
	 * 
	 * @param type $data
	 * @return true on success, error message on failure
	 */
	protected function validateRecurrence($data) {
		if (!isset($data['recurrence']['periodicity']) || !isset($data['recurrence']['unit'])) {
			return 'Illegal "recurrence" stracture';
		}

		$typeFields = array(
			'unit' => 'integer',
			'periodicity' => array('type' => 'inarray', 'params' => array('month', 'year')),
		);
		$validateTypes = $this->validateTypes($data['recurrence'], $typeFields);
		if ($validateTypes !== true) {
			return $validateTypes;
		}

		if ($data['recurrence']['unit'] !== 1) {
			return 'Temporarily, recurrence "unit" must be 1';
		}

		return true;
	}

	/**
	 * Validates combination of "periodicity" and "upfront" fields.
	 * 
	 * @param type $data
	 * @return true on success, error message on failure
	 */
	protected function validateYearlyPeriodicity($data) {
		if ($data['recurrence']['periodicity'] === 'year' && !$data['upfront']) {
			return 'Plans with a yearly periodicity must be paid upfront';
		}
		return true;
	}

}

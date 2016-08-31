<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a validator for Plan.
 *
 */
class Billrun_ModelValidator_Plans extends Billrun_ModelValidator_Base {

	protected function validateName($data) {
		if (!isset($data['name'])) {
			return false;
		}
		$name = strtolower($data['name']);
		return !in_array($name, array('base', 'groups'));
	}

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

	protected function validateYearlyPeriodicity($data) {
		if ($data['recurrence']['periodicity'] === 'year' && !$data['upfront']) {
			return 'Plans with a yearly periodicity must be paid upfront';
		}
		return true;
	}

}

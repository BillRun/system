<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi services model for services entity
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Services extends Models_Entity {
	
	protected $errorCode = 999999;

	protected function init($params) {
		parent::init($params);
		$this->validatePrice();
		$this->validateTierLimitCycles();
		$this->validateRecurrence();
		$this->validateRoundingRules();
	}
	
	/**
	 * Verify in case of limit service sycles Recurring Charges cycles has the same sum of cycles.
	 */
	protected function validateTierLimitCycles() {
		$limitCycles = Billrun_Util::getIn($this->update, 'limit_cycles', false);
		if(!$limitCycles){
			return true;
		}
		if(!is_numeric($limitCycles) || $limitCycles <= 0){
			throw new Billrun_Exceptions_Api($this->errorCode, array(), '"No. of Cycles" is not valid');
		}
		$priceIntervals = Billrun_Util::getIn($this->update, 'price', []);
		$totalCycles = 0;
		foreach ($priceIntervals as $price) {
			$totalCycles = max($price['to'], $totalCycles);
		}
		if($limitCycles !== $totalCycles){
			throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Service summary cycles of "Recurring Charges" not match "No. of Cycles"');
		}
		
		return true;
	}

	/**
	 * Verify services has all price parameters required.
	 */
	protected function validatePrice() {
		$priceIntervals = Billrun_Util::getIn($this->update, 'price', []);
		foreach ($priceIntervals as $price) {
			if (!isset($price['from']) || $price['from'] === '' || 
				!isset($price['to']) || $price['to'] === '') {
				throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Service missing cycles parameters');
			}
			if (!isset($price['price']) || $price['price'] === '') {
				throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Service missing price parameter');
			}
		}
		
		return true;
	}
	
	/**
	 * Verify services has all price parameters required.
	 */
	protected function validateRecurrence() {
            $update_parameters = Billrun_Util::getIn($this->config, [$this->action, 'update_parameters'], []);
            $recurrence_field = array_reduce($update_parameters, function ($acc, $field) {
                return $field['name'] == 'recurrence' ? $field : $acc;
            }, null);
            if (!is_null($recurrence_field)) {
							$periodicity = Billrun_Util::getIn($this->update, 'recurrence.periodicity', null);
							if (empty($periodicity)) {
								$frequency = Billrun_Util::getIn($this->update, 'recurrence.frequency', null);
								if (empty($frequency) ) {
									throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Missing Billing Frequency - Type parameter');
								}
								$start = Billrun_Util::getIn($this->update, 'recurrence.start', null);
								if (empty($start)) {
									throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Missing Billing Frequency - Start parameter');
								}
							}
            }
            return true;
	}

	/**
	 * Verify services has all Rounding Rules required parameters.
	 */
	protected function validateRoundingRules() {
		$rounding_rules = Billrun_Util::getIn($this->update, 'rounding_rules', null);
		if (!empty($rounding_rules)) {
			$rounding_type = Billrun_Util::getIn($rounding_rules, 'rounding_type', 'None');
			if (!empty($rounding_type ) && $rounding_type !== 'None') {
				if (!in_array($rounding_type, ['down', 'up', 'nearest'])) {
					throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Rounding rules must have rounding type');
				}
				$rounding_decimals = Billrun_Util::getIn($rounding_rules, 'rounding_decimals', null);
				if (is_null($rounding_decimals)) {
					throw new Billrun_Exceptions_Api($this->update, array(), "Rounding rules must have rounding decimal");
				}
			}
		}
		return true;
	}
	
	/**
	 * method to add entity custom fields values from request
	 * 
	 * @param array $fields array of field settings
	 */
	protected function getCustomFields($update = array()) {
		$customFields = parent::getCustomFields();
		$plays = Billrun_Util::getIn($update, 'play', Billrun_Util::getIn($this->before, 'play', []));
		return Billrun_Utils_Plays::filterCustomFields($customFields, $plays);
	}

}

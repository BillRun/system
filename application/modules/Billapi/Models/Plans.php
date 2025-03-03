<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi services model for plans entity
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Plans extends Models_Entity {

	protected $errorCode = 988888;
	
	protected function init($params) {
		parent::init($params);
		if ($this->update['connection_type'] == "postpaid") {
			$this->validateRecurrence();
		}
		$this->validateRoundingRules();
	}


	/**
	 * Verify plan has all Rounding Rules required parameters.
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
	

}

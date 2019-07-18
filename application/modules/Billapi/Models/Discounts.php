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
class Models_Discounts extends Models_Entity {

	protected $errorCode = 999999;
	protected $cycleSupportedFields = [
		//'service.*', // all fields from service condition block are supported (not used)
		'subscriber.plan',
		'subscriber.plan_activation',
		'subscriber.plan_deactivation',
	];

	protected function init($params) {
		parent::init($params);
		$this->validateCycles();
	}

	/**
	 * Return the key field
	 *
	 * @return String
	 */
	protected function getKeyField() {
		return 'key';
	}

	/**
	 * Verify that plan or service exists in conditions if cycles value in limited.
	 */
	protected function validateCycles() {
		$sycles = Billrun_Util::getIn($this->update, ['params', 'cycles'], '');
		if (!empty($sycles)) {
			$conditionsGroups = Billrun_Util::getIn($this->update, ['params', 'conditions'], []);
			// params, conditions, <X>
			foreach ($conditionsGroups as $conditionsGroup) {
				// params, conditions, <X>, <subscriber / account>
				foreach ($conditionsGroup as $type => $typeConditions) {
					if ($type === 'subscriber') {
						// params, conditions, <X>, subscriber, <Y>
						foreach ($typeConditions as $subscriberConditionsGroup) {
							// params, conditions, <X>, subscriber, <Y>, <service / fields>
							foreach ($subscriberConditionsGroup as $subscriberConditionsType => $subscriberConditionsTypeConditions) {
								if ($subscriberConditionsType === 'fields') {
									// params, conditions, <X>, subscriber, <Y>, fields, <Z>
									foreach ($subscriberConditionsTypeConditions as $condition) {
										if (in_array($type . "." . $condition['field'], $this->cycleSupportedFields)) {
											return true;
										}
									}
								} else if($subscriberConditionsType === 'service') {
									// check if at least one condition exists
									if (!empty($subscriberConditionsTypeConditions['any'][0]['fields'][0]['field'])) {
										return true;
									}
								}
							}
						}
					} else if (!empty($typeConditions['fields'])) {
						// params, conditions, <X>, <TYPE>, fields
						foreach ($typeConditions['fields'] as $condition) {
							if (in_array($type . "." . $condition['field'], $this->cycleSupportedFields)) {
								return true;
							}
						}
					} else {
						// params, conditions, <X>, <TYPE>, <Y>
						foreach ($typeConditions as $typeConditionsGroup) {
							if (!empty($typeConditionsGroup['field'])){
								// params, conditions, <X>, <TYPE>, <Y>, fields
								foreach ($typeConditionsGroup['field'] as $condition) {
									if (in_array($type . "." . $condition['field'], $this->cycleSupportedFields)) {
										return true;
									}
								}
							}
						}
					}
				}
			}
			error_log("value : " . print_r($conditionsGroups, 1));
			throw new Billrun_Exceptions_Api($this->errorCode, array(), 'Limited by cycles must include at least one condition on Plan or Service');
		}
		return true;
	}



}
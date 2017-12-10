<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing class for subscriber query by Sid.
 *
 * @package  Billing
 * @since    4
 */
class Billrun_Subscriber_Query_Types_Custom extends Billrun_Subscriber_Query_Base {

	protected $customFields = array();
	
	/**
	 * get the field name in the parameters and the field name to set in the query.
	 * @return array - Key is the field name in the parameters and value is the field
	 * name in the query.
	 */
	public function __construct() {
		$config = Billrun_Factory::config()->getConfigValue('subscribers.subscriber.fields', array());
		$this->customFields = array_filter($config, function($field) {
			return isset($field['unique']) && $field['unique'];
		});
	}

	protected function getKeyFields() {
		$fieldNames = array_map(function($field){return $field['field_name'];}, $this->customFields);
		return array_combine($fieldNames, $fieldNames);
	}

	/**
	 * Build the query by the parameters.
	 * @param array $params - Array of received parameters.
	 * @param array $fieldNames - Array of field names in the parameters and the query.
	 * @return array Query built from received parameters.
	 */
	protected function buildQuery($params, $fieldNames) {
		$query = array();

		foreach ($fieldNames as $paramField => $queryField) {
			if (isset($params[$paramField])) {
				$query[$queryField] = $params[$paramField];
			}
		}

		return $query;
	}
	
	protected function canHandle($params, $fieldsToValidate) {
		foreach ($fieldsToValidate as $field) {
			if (isset($params[$field])) {
				return TRUE;
			}
		}
		return FALSE;
	}

}

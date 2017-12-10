<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Abstract base class for getting the subscriber query.
 *
 * @package  Billing
 * @since    4
 */
abstract class Billrun_Subscriber_Query_Base implements Billrun_Subscriber_Query_Interface {

	/**
	 * get the field name in the parameters and the field name to set in the query.
	 * @return array - Key is the field name in the parameters and value is the field
	 * name in the query.
	 */
	protected abstract function getKeyFields();

	/**
	 * Checks if a query can be built from the received parameters.
	 * @param array $params - Received array of parameters.
	 * @param array $fieldsToValidate - Array of field names in the parameters.
	 */
	protected function canHandle($params, $fieldsToValidate) {
		foreach ($fieldsToValidate as $field) {
			if (!isset($params[$field])) {
				return false;
			}
		}

		return true;
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
			$query[$queryField] = $params[$paramField];
		}

		return $query;
	}

	/**
	 * Get the query for a subscriber by received parameters.
	 * @param type $params - Params to get the subscriber query by.
	 * @return array Query to get the subscriber from the mongo, false otherwise.
	 */
	public function getQuery($params) {
		$fieldNames = $this->getKeyFields();

		if (!$this->canHandle($params, array_keys($fieldNames))) {
			return false;
		}

		return $this->buildQuery($params, $fieldNames);
	}

}

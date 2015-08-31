<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing class for subscriber query by Sid.
 *
 * @package  Billing
 * @since    4
 */
class Billrun_Subscriber_Query_Types_Sid extends Billrun_Subscriber_Query_Base {
	
	/**
	 * get the field name in the parameters and the field name to set in the query.
	 * @return array - Key is the field name in the parameters and value is the field
	 * name in the query.
	 */
	protected function getKeyFields() {
		return array('sid' => 'sid');
	}
	
	/**
	 * Checks if a query can be built from the received parameters.
	 * @param array $params - Received array of parameters.
	 * @param array $fieldsToValidate - Array of field names in the parameters.
	 */
	protected function canHandle($params, $fieldsToValidate) {
		// Add extra fields to validate.
		$fieldsToValidate[] = 'to';
		$fieldsToValidate[] = 'from';
		return parent::canHandle($params, $fieldsToValidate);
	}
	
	/**
	 * Build the query by the parameters.
	 * @param array $params - Array of received parameters.
	 * @param array $fieldNames - Array of field names in the parameters and the query.
	 * @return array Query built from received parameters.
	 */
	protected function buildQuery($params, $fieldNames) {
		$query = parent::buildQuery($params, $fieldNames);
		
		// Add the extra query fields.
		$query['to']['$lte']   = Billrun_Db::intToMongoDate($params['to']);
		$query['from']['$gte'] = Billrun_Db::intToMongoDate($params['from']);
		
		return $query;
	}
}

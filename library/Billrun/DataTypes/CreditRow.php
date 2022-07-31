<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents a credit row in the billing system.
 */
class CreditRow {

	/**
	 * The properties of the credit row.
	 */
	private $properties;

	/**
	 * Construct a new credit row object.
	 * 
	 * @param jsondata - $request to be to populate the credit row.
	 */
	public function __construct($request) {
		// @TODO: take to config
		$required_fields = array(
			array('credit_type', 'charge_type'), // charge_type is for backward compatibility
			'amount_without_vat',
			'reason',
			'account_id',
			'subscriber_id',
			'credit_time',
			'service_name',
		);

		// @TODO: take to config
		$optional_fields = array(
			'plan' => array(),
			'vatable' => array('default' => '1'),
			'promotion' => array(),
			'fixed' => array(),
		);
		$filtered_request = array();

		foreach ($required_fields as $field) {
			$found_field = false;
			if (is_array($field)) {
				foreach ($field as $req) {
					if (isset($credit_row[$req])) {
						if ($found_field) {
							unset($credit_row[$req]); // so the stamp won't be calculated on it.
						} else {
							$filtered_request[$req] = $credit_row[$req];
							$found_field = true;
						}
					}
				}
			} else if (isset($credit_row[$field])) {
				$filtered_request[$field] = $credit_row[$field];
				$found_field = true;
			}
			if (!$found_field) {
				return array(
					'status' => 0,
					'desc' => 'required field(s) missing: ' . print_r($field, true),
				);
			}
		}

		foreach ($optional_fields as $field => $options) {
			if (!isset($credit_row[$field])) {
				if (isset($options['default'])) {
					$filtered_request[$field] = $options['default'];
				}
			} else {
				$filtered_request[$field] = $credit_row[$field];
			}
		}

		if (isset($filtered_request['charge_type'])) {
			$filtered_request['credit_type'] = $filtered_request['charge_type'];
			unset($filtered_request['charge_type']);
		}
		if ($filtered_request['credit_type'] != 'charge' && $filtered_request['credit_type'] != 'refund') {
			return array(
				'status' => 0,
				'desc' => 'credit_type could be either "charge" or "refund"',
			);
		}

		$amount_without_vat = Billrun_Util::filter_var($filtered_request['amount_without_vat'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
		if (!is_numeric($filtered_request['amount_without_vat']) || $amount_without_vat === false) {
			return array(
				'status' => 0,
				'desc' => 'amount_without_vat is not a number',
			);
		} else if ($amount_without_vat == 0) {
			return array(
				'status' => 0,
				'desc' => 'amount_without_vat equal zero',
			);
		} else {
			// TODO: Temporary conversion. Remove it once they send negative values!
			if ($filtered_request['credit_type'] == 'refund' && floatval($amount_without_vat) > 0) {
				$filtered_request['amount_without_vat'] = -floatval($amount_without_vat);
			} else {
				$filtered_request['amount_without_vat'] = floatval($amount_without_vat);
			}
		}

		if (is_string($filtered_request['reason'])) {
			$filtered_request['reason'] = preg_replace('/[^a-zA-Z0-9-_]+/', '_', $filtered_request['reason']); // removes unwanted characters from the string (especially dollar sign and dots)
		} else {
			return array(
				'status' => 0,
				'desc' => 'reason error',
			);
		}

		if (!empty($filtered_request['service_name']) && is_string($filtered_request['service_name'])) {
			$filtered_request['service_name'] = preg_replace('/[^a-zA-Z0-9-_]+/', '_', $filtered_request['service_name']); // removes unwanted characters from the string (especially dollar sign and dots) as they are not allowed as mongo keys
		} else {
			return array(
				'status' => 0,
				'desc' => 'service_name error',
			);
		}

		if (isset($filtered_request['account_id'])) {
			$filtered_request['aid'] = (int) $filtered_request['account_id'];
			unset($filtered_request['account_id']);
		}

		if (isset($filtered_request['subscriber_id'])) {
			$filtered_request['sid'] = (int) $filtered_request['subscriber_id'];
			unset($filtered_request['subscriber_id']);
		}

		if ($filtered_request['aid'] == 0) {
			return array(
				'status' => 0,
				'desc' => 'account id must be positive integers',
			);
		}

		if ($filtered_request['sid'] < 0) {
			return array(
				'status' => 0,
				'desc' => 'subscriber id must be greater or equal to zero',
			);
		}

		$credit_time = new Zend_Date($filtered_request['credit_time']);
		$filtered_request['urt'] = new MongoDate($credit_time->getTimestamp());
		unset($filtered_request['credit_time']);

		$filtered_request['vatable'] = filter_var($filtered_request['vatable'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		if (!is_null($filtered_request['vatable'])) {
			$filtered_request['vatable'] = (int) $filtered_request['vatable'];
		} else {
			return array(
				'status' => 0,
				'desc' => 'vatable could be either "0" or "1"',
			);
		}

		$filtered_request['source'] = 'api';
		$filtered_request['usaget'] = $filtered_request['type'] = 'credit';
		ksort($filtered_request);
		$filtered_request['stamp'] = Billrun_Util::generateArrayStamp($filtered_request);

		return $filtered_request;
	}

}

<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Credit action class
 *
 * @package  Action
 * @since    0.5
 */
class CreditAction extends Action_Base {

	/**
	 * method to execute the refund
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		Billrun_Factory::log()->log("Execute credit", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests

		$parsed_row = $this->parseRow($request);
		if (is_null($parsed_row)) {
			return;
		}
		try {
			$linesCollection = Billrun_Factory::db()->linesCollection();
			if ($linesCollection->query('stamp', $parsed_row['stamp'])->count() > 0) {
				return $this->setError('Transaction already exists in the DB', $request);
			}

			$parsed_row['process_time'] = date(Billrun_Base::base_dateformat);

			$entity = new Mongodloid_Entity($parsed_row);
		
			if ($entity->save($linesCollection) === false) {
				return $this->setError('failed to store into DB lines', $request);
			}

				if ($this->insertToQueue($entity) === false) {
					return $this->setError('failed to store into DB queue', $request);
				} else {
					$this->getController()->setOutput(array(array(
							'status' => 1,
							'desc' => 'success',
							'stamp' => $entity['stamp'],
							'input' => $request,
					)));
					Billrun_Factory::log()->log("Added credit line " . $entity['stamp'], Zend_Log::INFO);
					return true;
				}
		} catch(\Exception $e) {
			Billrun_Factory::log()->log('failed to store into DB got error : '.$e->getCode() .' : '.$e->getMessage() , $request);
			Billrun_Factory::log()->log('failed saving request :'.print_r($request,1) , Zend_Log::INFO);
            Billrun_Factory::log()->log('failed saving :'.json_encode($parsed_row) , Zend_Log::INFO);

			$fd= fopen(Billrun_Factory::config()->getConfigValue('credit.failed_credits_file', './files/failed_credits.json'), 'a+');			
			fwrite($fd,json_encode($parsed_row) . PHP_EOL);
			fclose($fd);
			
			return $this->setError('failed to store into DB queue', $request);
		}
		
	}

	protected function parseRow($credit_row) {
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
			'vatable' => '1',
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
				return $this->setError('required field(s) missing: ' . print_r($field, true), $credit_row);
			}
		}

		foreach ($optional_fields as $field => $default_value) {
			if (!isset($credit_row[$field])) {
				$filtered_request[$field] = $default_value;
			} else {
				$filtered_request[$field] = $credit_row[$field];
			}
		}

		if (isset($filtered_request['charge_type'])) {
			$filtered_request['credit_type'] = $filtered_request['charge_type'];
			unset($filtered_request['charge_type']);
		}
		if ($filtered_request['credit_type'] != 'charge' && $filtered_request['credit_type'] != 'refund') {
			return $this->setError('credit_type could be either "charge" or "refund"', $credit_row);
		}

		$amount_without_vat = Billrun_Util::filter_var($filtered_request['amount_without_vat'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
		if (!is_numeric($filtered_request['amount_without_vat']) || $amount_without_vat === false) {
			return $this->setError('amount_without_vat is not a number', $credit_row);
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
			return $this->setError('reason error', $credit_row);
		}

		if (!empty($filtered_request['service_name']) && is_string($filtered_request['service_name'])) {
			$filtered_request['service_name'] = preg_replace('/[^a-zA-Z0-9-_]+/', '_', $filtered_request['service_name']); // removes unwanted characters from the string (especially dollar sign and dots) as they are not allowed as mongo keys
		} else {
			return $this->setError('service_name error', $credit_row);
		}

		if (isset($filtered_request['account_id'])) {
			$filtered_request['aid'] = (int) $filtered_request['account_id'];
			unset($filtered_request['account_id']);
		}

		if (isset($filtered_request['subscriber_id'])) {
			$filtered_request['sid'] = (int) $filtered_request['subscriber_id'];
			unset($filtered_request['subscriber_id']);
		}

		if ($filtered_request['aid'] == 0 || $filtered_request['sid'] == 0) {
			return $this->setError('account, subscriber ids must be positive integers', $credit_row);
		}

		$credit_time = new Zend_Date($filtered_request['credit_time']);
		$filtered_request['urt'] = new MongoDate($credit_time->getTimestamp());
		unset($filtered_request['credit_time']);

		$filtered_request['vatable'] = filter_var($filtered_request['vatable'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		if (!is_null($filtered_request['vatable'])) {
			$filtered_request['vatable'] = (int) $filtered_request['vatable'];
		} else {
			return $this->setError('vatable could be either "0" or "1"', $credit_row);
		}

		$filtered_request['source'] = 'api';
		$filtered_request['usaget'] = $filtered_request['type'] = 'credit';
		ksort($filtered_request);
		$filtered_request['stamp'] = Billrun_Util::generateArrayStamp($filtered_request);

		return $filtered_request;
	}

	protected function insertToQueue($entity) {
		$queue = Billrun_Factory::db()->queueCollection();
		if (!is_object($queue)) {
			Billrun_Factory::log()->log('Queue collection is not defined', Zend_Log::ALERT);
			return false;
		} else {
			return $queue->insert(array(
					'stamp' => $entity['stamp'],
					'type' => $entity['type'],
					'urt' => $entity['urt'],
					'calc_name' => false,
					'calc_time' => false,
			));
		}
	}

	function setError($error_message, $input = null) {
		Billrun_Factory::log()->log("Sending Error : {$error_message}", Zend_Log::NOTICE);
		$output = array(
			'status' => 0,
			'desc' => $error_message,
		);
		if (!is_null($input)) {
			$output['input'] = $input;
		}
		$this->getController()->setOutput(array($output));
		return;
	}

}
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
		Billrun_Factory::log()->log("Execute refund", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests

		$parsed_row = $this->parseRow($request);
		if (is_null($parsed_row)) {
			return;
		}



		$linesCollection = Billrun_Factory::db()->linesCollection();
		if ($linesCollection->query('stamp', $parsed_row['stamp'])->count() > 0) {
			return $this->setError('Transaction already exists in the DB', $request);
		}

		$entity = new Mongodloid_Entity($parsed_row);
		if ($this->insertToQueue($entity) === false) {
			return $this->setError('failed to store into DB', $request);
		}

		if ($entity->save($linesCollection) === false) {
			return $this->setError('failed to store into DB', $request);
		} else {
			$this->getController()->setOutput(array(array(
					'status' => 1,
					'desc' => 'success',
					'stamp' => $entity['stamp'],
					'input' => $request,
			)));
			return true;
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
			$filtered_request['amount_without_vat'] = floatval($amount_without_vat);
		}

		if (is_string($filtered_request['reason'])) {
			$filtered_request['reason'] = preg_replace('/[^a-zA-Z0-9-_]+/', '_', $filtered_request['reason']); // removes unwanted characters from the string (especially dollar sign and dots) as they are not allowed as mongo keys
		} else {
			return $this->setError('reason error', $credit_row);
		}

		$filtered_request['account_id'] = (int) $filtered_request['account_id'];
		$filtered_request['subscriber_id'] = (int) $filtered_request['subscriber_id'];
		if ($filtered_request['account_id'] == 0 || $filtered_request['subscriber_id'] == 0) {
			return $this->setError('account, subscriber ids must be positive integers', $credit_row);
		}

		if (Billrun_Util::isTimestamp(strval($filtered_request['credit_time']))) {
			$filtered_request['urt'] = (int) $filtered_request['credit_time'];
			unset($filtered_request['credit_time']);
		} else {
			return $this->setError('credit_time is not a valid time stamp', $credit_row);
		}

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
			return $queue->insert(array('stamp' => $entity['stamp'], 'type' => $entity['type']));
		}
	}

	function setError($error_message, $input = null) {
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

class BulkCreditAction extends CreditAction {

	/**
	 * method to execute the bulk credit
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		$request = $this->getRequest()->getPost();
//		$request = $this->getRequest()->getQuery();
//		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		if (isset($request['operation'])) {
			if ($request['operation'] == 'credit') {
				return $this->bulkCredit($request);
			} else if ($request['operation'] == 'query') {
				return $this->queryCredit($request);
			}
		}
		return $this->setError('Unrecognized operation', $request);
	}

	protected function bulkCredit($request) {
		$credits = json_decode($request['credits'], true);
		if (!is_array($credits) || empty($credits)) {
			return $this->setError('Input json is invalid', $request);
		}

		$filename = md5(microtime()) . ".json";
		foreach ($credits as $credit) {
			$parsed_row = $this->parseRow($credit);
			if (is_null($parsed_row)) {
				return;
			}
			$parsed_row['file'] = $filename;
			$parsed_rows[] = $parsed_row;
		}

		$options = array(
			'type' => 'credit',
			'file_name' => $filename,
			'file_content' => json_encode($parsed_rows),
		);

		$receiver = Billrun_Receiver::getInstance($options);
		if ($receiver) {
			$files = $receiver->receive();
		} else {
			return $this->setError('Receiver cannot be loaded', $request);
		}
		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'operation' => 'credit',
				'stamp' => $options['file_name'],
				'input' => $request,
		)));
		return true;
	}

	protected function queryCredit($request) {
		if (isset($request['stamp'])) {
			$filtered_request['stamp'] = $request['stamp'];
		} else {
			return $this->setError('Stamp is missing', $request);
		}

		if (!isset($request['details'])) {
			$filtered_request['details'] = 0;
		} else {
			$filtered_request['details'] = filter_var($request['details'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
			if (!is_null($filtered_request['details'])) {
				$filtered_request['details'] = (int) $filtered_request['details'];
			} else {
				return $this->setError('details could be either "0" or "1"', $request);
			}
		}
		return $this->getFileStatus($filtered_request['stamp'], $filtered_request['details']);
	}

	protected function getFileStatus($filename, $details) {
		$log = Billrun_Factory::db()->logCollection();
		$query = array(
			'file_name' => $filename,
		);
		$log_entry = $log->query($query)->cursor()->current();
		if ($log_entry->isEmpty()) {
			return $this->setError("File $filename not found");
		}
		$statuses = array(
			0 => 'File received',
			1 => 'In progress',
			2 => 'Done processing',
		);
		if (isset($log_entry['process_time'])) {
			$status = 2;
		} else if (isset($log_entry['start_process_time'])) {
			$status = 1;
		} else {
			$status = 0;
		}
		$output = array(
			'status' => 1,
			'desc' => $statuses[$status],
			'operation' => 'query',
			'stamp' => $filename,
		);
		if ($status == 2) {
			if ($details) {
				$lines = Billrun_Factory::db()->linesCollection();
				$query = array(
					'file' => $filename,
				);
				$details = $lines->query($query)->cursor();
				$output['details'] = array();
				foreach ($details as $row) {
					$output['details'][] = $row->getRawData();
				}
				$output['count'] = count($output['details']);
			}
		}
		$this->getController()->setOutput(array($output));
		return true;
	}

}
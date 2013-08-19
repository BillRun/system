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

//		$post = $this->getRequest()->getPost(); // finally uncomment this
		$post = $this->getRequest()->getQuery();
		$filtered_post = array();

		foreach ($required_fields as $field) {
			$found_field = false;
			if (is_array($field)) {
				foreach ($field as $req) {
					if (isset($post[$req])) {
						if ($found_field) {
							unset($post[$req]); // so the stamp won't be calculated on it.
						} else {
							$filtered_post[$req] = $post[$req];
							$found_field = true;
						}
					}
				}
			} else if (isset($post[$field])) {
				$filtered_post[$field] = $post[$field];
				$found_field = true;
			}
			if (!$found_field) {
				$this->getController()->setOutput(array(array(
						'status' => 0,
						'desc' => 'required field(s) missing: ' . print_r($field, true),
				)));
				return;
			}
		}

		foreach ($optional_fields as $field => $default_value) {
			if (!isset($post[$field])) {
				$filtered_post[$field] = $default_value;
			} else {
				$filtered_post[$field] = $post[$field];
			}
		}

		if (isset($filtered_post['charge_type'])) {
			$filtered_post['credit_type'] = $filtered_post['charge_type'];
			unset($filtered_post['charge_type']);
		}
		if ($filtered_post['credit_type'] != 'charge' && $filtered_post['credit_type'] != 'refund') {
			$this->getController()->setOutput(array(array(
					'status' => 0,
					'desc' => 'credit_type could be either "charge" or "refund"',
			)));
			return;
		}

		$filtered_post['amount_without_vat'] = Billrun_Util::filter_var($filtered_post['amount_without_vat'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
		if ($filtered_post['amount_without_vat'] === false) {
			$this->getController()->setOutput(array(array(
					'status' => 0,
					'desc' => 'amount_without_vat is not a number',
			)));
			return;
		} else {
			$filtered_post['amount_without_vat'] = floatval($filtered_post['amount_without_vat']);
		}

		if (is_string($filtered_post['reason'])) {
			$filtered_post['reason'] = preg_replace('/[^a-zA-Z0-9-_]+/', '_', $filtered_post['reason']); // removes unwanted characters from the string (especially dollar sign and dots) as they are not allowed as mongo keys
		} else {
			$this->getController()->setOutput(array(array(
					'status' => 0,
					'desc' => 'reason error',
			)));
			return;
		}

		$filtered_post['account_id'] = (int) $filtered_post['account_id'];
		$filtered_post['subscriber_id'] = (int) $filtered_post['subscriber_id'];
		if ($filtered_post['account_id'] == 0 || $filtered_post['subscriber_id'] == 0) {
			$this->getController()->setOutput(array(array(
					'status' => 0,
					'desc' => 'account, subscriber ids must be positive integers',
			)));
			return;
		}

		if (Billrun_Util::isTimestamp($filtered_post['credit_time'])) {
			$filtered_post['unified_record_time'] = new MongoDate((int) $filtered_post['credit_time']);
			unset($filtered_post['credit_time']);
		} else {
			$this->getController()->setOutput(array(array(
					'status' => 0,
					'desc' => 'credit_time is not a valid time stamp',
			)));
			return;
		}

		if ($filtered_post['vatable'] == '0' || $filtered_post['vatable'] == '1') {
			$filtered_post['vatable'] = (int) $filtered_post['vatable'];
		} else {
			$this->getController()->setOutput(array(array(
					'status' => 0,
					'desc' => 'vatable could be either "0" or "1"',
			)));
			return;
		}

		$filtered_post['source'] = 'api';
		$filtered_post['usaget'] = $filtered_post['type'] = 'credit';
		$filtered_post['stamp'] = Billrun_Util::generateArrayStamp($filtered_post);

		$filtered_post['process_time'] = Billrun_Util::generateCurrentTime();

		$linesCollection = Billrun_Factory::db()->linesCollection();
		if ($linesCollection->query('stamp', $filtered_post['stamp'])->count() > 0) {
			$this->getController()->setOutput(array(array(
					'status' => 0,
					'desc' => 'Transaction already exists in the DB',
					'input' => $post,
			)));
			return;
		}

		$entity = new Mongodloid_Entity($filtered_post);
		if ($this->insertToQueue($entity) === false) {
			$this->getController()->setOutput(array(array(
					'status' => 0,
					'desc' => 'failed to store into DB',
					'input' => $post,
			)));
			return;
		}

		if ($entity->save($linesCollection) === false) {
			$this->getController()->setOutput(array(array(
					'status' => 0,
					'desc' => 'failed to store into DB',
					'input' => $post,
			)));
		} else {
			$this->getController()->setOutput(array(array(
					'status' => 1,
					'desc' => 'success',
					'stamp' => $entity['stamp'],
					'input' => $post,
			)));
		}
	}

	protected function insertToQueue($entity) {
		$queue = Billrun_Factory::db()->queueCollection();
		return $queue->insert(array('stamp' => $entity['stamp'], 'type' => $entity['type']));
	}

}
<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Credit action class
 *
 * @package  Action
 * @since    0.5
 */
class CreditAction extends ApiAction {

	/**
	 * method to execute the refund
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		Billrun_Factory::log("Execute credit", Zend_Log::INFO);
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

			if ($linesCollection->save($entity, 1) === false) {
				return $this->setError('failed to store into DB lines', $request);
			}

			if ($this->insertToQueue($entity) === false) {
				return $this->setError('failed to store into DB queue', $request);
			}
			$this->getController()->setOutput(array(array(
					'status' => 1,
					'desc' => 'success',
					'stamp' => $entity['stamp'],
					'input' => $request,
			)));
			Billrun_Factory::log("Added credit line " . $entity['stamp'], Zend_Log::INFO);
			return true;
		} catch (\Exception $e) {
			Billrun_Factory::log('failed to store into DB got error : ' . $e->getCode() . ' : ' . $e->getMessage(), Zend_Log::ALERT);
			Billrun_Factory::log('failed saving request :' . print_r($request, 1), Zend_Log::ALERT);
			Billrun_Factory::log('failed saving :' . json_encode($parsed_row), Zend_Log::ALERT);
			Billrun_Util::logFailedCreditRow($parsed_row);

			return $this->setError('failed to store into DB queue', $request);
		}
	}

	protected function parseRow($credit_row) {
		$ret = Billrun_Util::parseCreditRow($credit_row);
		if (isset($ret['status']) && $ret['status'] == 0) {
			$error_message = isset($ret['desc']) ? $ret['desc'] : 'Error with credit row';
			return $this->setError($error_message, $credit_row);
		}
		return $ret;
	}

	protected function insertToQueue($entity) {
		$queue = Billrun_Factory::db()->queueCollection();
		if (!is_object($queue)) {
			Billrun_Factory::log('Queue collection is not defined', Zend_Log::ALERT);
			return false;
		} else {
			return $queue->insert(array(
					'stamp' => $entity['stamp'],
					'type' => $entity['type'],
					'urt' => $entity['urt'],
					'calc_name' => false,
					'calc_time' => false,
			), array('w' => 1));
		}
	}

}

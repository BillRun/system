<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Credit.php';

/**
 * Bulk credit action class
 *
 * @package  Action
 * @since    0.8
 */
class BulkCreditAction extends CreditAction {

	use Billrun_Traits_Api_UserPermissions;

	/**
	 * method to execute the bulk credit
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		$this->allowed();
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
		$parsed_rows = array();
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
		if (!$receiver) {
			return $this->setError('Receiver cannot be loaded', $request);
		}

		$files = $receiver->receive();
		if (!$files) {
			return $this->setError('Couldn\'t receive file', $request);
		}

		$this->processBulkCredit();
		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'operation' => 'credit',
				'stamp' => $options['file_name'],
				'input' => $request,
		)));
		return true;
	}

	protected function processBulkCredit() {
		$cmd = 'php -t ' . APPLICATION_PATH . ' ' . APPLICATION_PATH . '/public/index.php ' . Billrun_Util::getCmdEnvParams() . ' --process --type credit --parser none';
		Billrun_Util::forkProcessCli($cmd);
	}

	protected function queryCredit($request) {
		if (!isset($request['stamp'])) {
			return $this->setError('Stamp is missing', $request);
		}

		$filtered_request['stamp'] = $request['stamp'];

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
					'type' => 'credit',
					'file' => $filename,
				);
				$details = $lines->query($query)->hint(array('type' => 1))->cursor();
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

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_ADMIN;
	}

}

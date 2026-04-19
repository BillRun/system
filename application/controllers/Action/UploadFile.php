<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Receive action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       1.0
 */
class UploadFileAction extends Action_Base {
	use Billrun_Traits_Api_UserPermissions;

	/**
	 * method to execute the receive process
	 * it's called automatically by the cli main controller
	 */
	public function execute() {
		$this->allowed();
		Billrun_Factory::log()->log("Upload file API", Zend_Log::DEBUG);
		$status = false;
		$request = $this->getRequest();
		$payment_gateway = $request->get('payment_gateway');
		$options["payment_gateway"] = $payment_gateway;
		$options["payments_file_type"] = $request->get('payments_file_type');
		$options["type"] = str_replace("_", '', $payment_gateway . ucwords($options['payments_file_type'], '_'));
		$options["file_type"] = $request->get('file_type');
		Billrun_Factory::log()->log("Validating input options - payment_gateway - '" . $options["payment_gateway"] . "', payments file type - '" . $options["payments_file_type"] . "', processed type - '" . $options["type"] . "', file type - '" . $options["file_type"] . "'", Zend_Log::DEBUG);
		$res = $this->validateOptions($options);
		if ($res === true) {
			$pgOptions = $options;
			//			$pgOptions['file_type'] = $options['type'];
			$pgOptions["receiver"]["receiver_type"] = "PaymentGateway_" .
				$options["payment_gateway"] .
				"_" .
				ucfirst($options["type"]);
			if (!empty($_FILES['file']['name'])) {
				$file = $_FILES['file'];
				if (is_uploaded_file($_FILES['file']['tmp_name'])) {
					$directoryPath = $this->getFilesUploadPath($options);
					$sharedDirectoryPath = Billrun_Util::getBillRunSharedFolderPath($directoryPath);
					if (!file_exists($sharedDirectoryPath)) {
						mkdir($sharedDirectoryPath, 0777, true);
					}
					$targetPath = $sharedDirectoryPath . DIRECTORY_SEPARATOR . $file['name'];
					if (@move_uploaded_file($file['tmp_name'], $targetPath)) {
						chmod($targetPath, 0660);
						$pgOptions["file_name"] = $file['name']; 
					}
				}
			}
			Billrun_Factory::log()->log("Loading receiver using processed receiver_type " . $pgOptions["receiver"]["receiver_type"], Zend_Log::DEBUG);
			$receiver = $this->loadReceiver($pgOptions);
			if ($receiver) {
				Billrun_Factory::log()->log("Receiving file", Zend_Log::DEBUG);
				$status = $receiver->receive();
			}
		} else {
			Billrun_Factory::log()->log("Input validation failed - " . $res, Zend_Log::ALERT);
		}

		$warnings = [];
		$this->getController()->setOutput([
			[
				"status" => $status ? 1 : 0,
				"desc" => $status ? "success" : "error",
				"details" => [],
				"warnings" => $warnings,
			],
		]);
		return true;
	}

	protected function validateOptions($options) {
		$cpg_config = Billrun_Factory::config()->getConfigValue("payment_gateways", []);
		$cpg_name = $options['payment_gateway'];
		$current_pg_config = array_values(array_filter($cpg_config, function ($item) use ($cpg_name) {
			return $item['name'] === $cpg_name;
		}))[0] ?? null;
		if (empty($current_pg_config)) {
			return "Couldn't find " . $cpg_name . " payment gateway";
		}
		$found_file_type = false;
		foreach ($current_pg_config[$options['payments_file_type']] as $file_type) {
			if ($file_type['file_type'] == $options['file_type']) {
				$found_file_type = true;
				break;
			}
		}
		if (!$found_file_type) {
			return "Couldn't find " . $options['file_type'] . " file type";
		}
		$expected_source = str_replace('_', '', ucwords($options['payment_gateway'], '_')) . str_replace('_', '', ucwords($options['payments_file_type'], '_'));
		if ($options['type'] !== $expected_source) {
			return "Processed source " . $options['type'] . " is different from the expected " . $expected_source;
		}
		return true;
	}

	protected function getFilesUploadPath($options = []) {
		return implode(DIRECTORY_SEPARATOR, array_filter(array_map('trim', [
			"uploaded_files",
			date("Y"),
			date("m"),
			date("d"),
			Billrun_Util::getIn($options, 'payment_gateway', ''),
			Billrun_Util::getIn($options, 'payments_file_type', ''),
			Billrun_Util::getIn($options, 'file_type', ''),
		]), 'strlen'));
	}
	
	protected function loadReceiver($options) {
		$connection = null;
		$inputProcessor = false;
		$connectionDetails["connection_type"] = "relocate";
		$connectionDetails["path"] = $this->getFilesUploadPath($options);
		$connectionDetails["delete_received"] = true;
		$connectionDetails["filename_regex"] = "/^" . preg_quote($options["file_name"]) . '$/';
		$a = mkdir(
			Billrun_Util::getBillRunSharedFolderPath($connectionDetails["path"]),
			0775,
			true
		);
		if (!$inputProcessor) {
			// $connectionDetails["type"] = str_replace("_", "", ucwords($options["payment_gateway"], "_")) .
				// str_replace("_", "", ucwords($options["type"], "_"));
			$connectionDetails["type"] = $options["type"];
			$connectionDetails["payments_file_type"] = $options["payments_file_type"];
			$connectionDetails["file_type"] = $options["file_type"];
			$connectionDetails["cpg_name"] = $options["payment_gateway"];
			$connection = Billrun_Factory::paymentGatewayConnection(
					$connectionDetails
			);
		}
		return $connection;
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
	}

}

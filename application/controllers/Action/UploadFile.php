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
		$status = false;
		$request = $this->getRequest();
		$payment_gateway = $request->get('payment_gateway');
		$options["payment_gateway"] = $payment_gateway;
		$options["payments_file_type"] = $request->get('payments_file_type');
		$options["type"] = str_replace("_", '', $payment_gateway . ucwords($options['payments_file_type'], '_'));
		$options["file_type"] = $request->get('file_type');


		$pgOptions = $options;
		//			$pgOptions['file_type'] = $options['type'];
		$pgOptions["receiver"]["receiver_type"] = "PaymentGateway_" .
			$options["payment_gateway"] .
			"_" .
			ucfirst($options["type"]);
		if (!empty($_FILES['file']['name'])) {
			$file = $_FILES['file'];
			if (is_uploaded_file($_FILES['file']['tmp_name'])) {
				$directoryPath = $this->getFilesUploadPath();
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
		$receiver = $this->loadReceiver($pgOptions);
		if ($receiver) {
			$status = $receiver->receive();
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

	protected function getFilesUploadPath() {
		return "uploaded_files" . DIRECTORY_SEPARATOR . date("Y") . DIRECTORY_SEPARATOR . date("m") . DIRECTORY_SEPARATOR . date("d");
	}
	
	protected function loadReceiver($options) {
		$connection = null;
		$inputProcessor = false;
		$connectionDetails["connection_type"] = "relocate";
		$connectionDetails["path"] = $this->getFilesUploadPath();
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
		//return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
		//return Billrun_Factory::config()->getConfigValue('upload_file.permission', Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE);
		//return Billrun_Traits_Api_IUserPermissions::PERMISSION_ADMIN;
	}

}

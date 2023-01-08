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
		$options["payment_gateway"] = ""; // "payment_gateway" name. E.g. Direct_Debit
		$options["type"] = ""; // transactions_response etc.
		$options["file_type"] = "";

		$pgOptions = $options;
		//			$pgOptions['file_type'] = $options['type'];
		$pgOptions["receiver"]["receiver_type"] = "PaymentGateway_" .
			$options["payment_gateway"] .
			"_" .
			ucfirst($options["type"]);
		$pgOptions["file_name"] = "abc.csv";
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

	protected function loadReceiver($options) {
		$connection = null;
		$inputProcessor = false;
		$connectionDetails["connection_type"] = "relocate";
		$connectionDetails["path"] = "uploaded_files/" . date("Y") . "/" . date("m") . "/" . date("d");
		$connectionDetails["delete_received"] = true;
		$connectionDetails["filename_regex"] = "/^" . preg_quote($options["file_name"]) . '$/';
		$a = mkdir(
			Billrun_Util::getBillRunSharedFolderPath($connectionDetails["path"]),
			0775,
			true
		);
		if (!$inputProcessor) {
			$connectionDetails["type"] = str_replace("_", "", ucwords($options["payment_gateway"], "_")) .
				str_replace("_", "", ucwords($options["type"], "_"));
			$connectionDetails["payments_file_type"] = $options["type"];
			$connectionDetails["file_type"] = $options["file_type"];
			$connection = Billrun_Factory::paymentGatewayConnection(
					$connectionDetails
			);
		}
		return $connection;
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_ADMIN;
	}

}

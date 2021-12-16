<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2017 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * This class deal with uploaded files.
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       5.7
 */
class UploadedFileAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;

	public function execute() {
		$this->allowed();
		$request = $this->getRequest();
		$category = $request->get('category');
		if (empty($category)) {
			throw new Exception("Need to pass category");
		}
		$fileType = $request->get('file_type');
		if (empty($category)) {
			throw new Exception("Need to pass input processor name");
		}
		$result = 0;
		$message = "There was an error uploading the file, please try again!";
		if (is_uploaded_file($_FILES['file']['tmp_name'])) {
			if ($_FILES['file']['size'] > 1048576) { // 1MB
				throw new Exception("Invalid file size!, files bigger than 1MB are not allowed");
			}
			$directoryPath = $this->decidePathByCategory($category);
			$sharedDirectoryPath = Billrun_Util::getBillRunSharedFolderPath($directoryPath);
			if (!file_exists($sharedDirectoryPath)) {
			   mkdir($sharedDirectoryPath, 0777, true);
			}
			$time = time();
			$targetPath = $sharedDirectoryPath . $fileType . '_'. $time;
			if (@move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
				chmod($targetPath, 0440);
				$result = 1;
				$message = "The file " . basename($_FILES['file']['name']) . " has been uploaded";
			}
		}
		$output = array(
			'status' => $result ? 1 : 0,
			'desc' => $result ? 'success' : 'error',
			'details' => $result ? array('message' => $message, 'path' => $fileType . '_'. $time) : $message,
		);
		$this->getController()->setOutput(array($output));
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_ADMIN;
	}
	
	protected function decidePathByCategory($category) {
		switch ($category) {
			case 'key':
				$path = 'files/keys/input_processors/';
				break;

			default:
				throw new Exception("Unknown category");
		}
		return $path;
	}

}

<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Remote Files responder class
 *
 * @package  Billing
 * @since    0.5
 */
abstract class Billrun_Responder_Base_LocalDir extends Billrun_Responder_Base_FilesResponder {

	/**
	 * the responder export base path.
	 * @var string directory path
	 */
	protected $exportDir;
	protected $exportFromConfig = false;

	public function __construct($options) {

		parent::__construct($options);

		if (isset($options['export-path']) && true !== $options['export-path']) {
			$this->exportDir = $options['export-path'];
		} else {
			$this->exportDir = Billrun_Factory::config()->getConfigValue('response.export.path', './');
			$this->exportFromConfig = true;
		}
	}

	protected function respondAFile($responseFilePath, $fileName, $logLine) {
		//move file to export folder
		$exportDir = $this->exportFromConfig ? $this->exportDir . DIRECTORY_SEPARATOR . self::$type :
			$this->exportDir;
		if (!file_exists($exportDir)) {
			mkdir($exportDir);
		}
		$exportPath = $exportDir . DIRECTORY_SEPARATOR . $fileName;
		$result = rename($responseFilePath, $exportPath);
		if (!$result) {
			return FALSE;
		}
		parent::respondAFile($responseFilePath, $fileName, $logLine);
		Billrun_Factory::log("Placed response at : $exportPath", Zend_Log::DEBUG);

		return $exportPath;
	}

}

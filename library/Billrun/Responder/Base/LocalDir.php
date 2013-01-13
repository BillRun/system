<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing Remote Files responder class
 *
 * @package  Billing
 * @since    1.0
 */
abstract class Billrun_Responder_Base_LocalDir extends Billrun_Responder_Base_FilesResponder {

	/**
	 * the responder export base path.
	 * @var string directory path
	 */
	protected $exportDir;

	public function __construct($options) {

		parent::__construct($options);

		if (isset($options['export-path'])) {
			$this->exportDir = $options['export-path'];
		} else {
			$this->exportDir = $this->config->response->export->path;
		}
	}

	protected function respondAFile($responseFilePath, $fileName, $logLine) {
		//move file to export folder
		if (!file_exists($this->exportDir . DIRECTORY_SEPARATOR . self::$type)) {
			mkdir($this->exportDir . DIRECTORY_SEPARATOR . self::$type);
		}
		$result = rename($responseFilePath, $this->exportDir . DIRECTORY_SEPARATOR . self::$type . DIRECTORY_SEPARATOR . $fileName);
		if ($result) {
			parent::respondAFile($responseFilePath, $fileName, $logLine);
		}
	}

}

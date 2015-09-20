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
	
	protected $exportFromConfig = false;

	public function __construct($options) {

		parent::__construct($options);

		if (isset($options['export-path']) &&  true !== $options['export-path']) {
			$this->exportDir = $options['export-path'];
		} else {
			$this->exportDir = Billrun_Factory::config()->getConfigValue('response.export.path', './');
			$this->exportFromConfig = true;
		}
	}

	protected function respondAFile($responseFilePath, $fileName, $logLine , $premium_type = null) {
		//move file to export folder
		if(!is_null($premium_type)) {
			$type = !is_null($premium_type) ? $premium_type : self::$type;
		}
		$exportDir = $this->exportFromConfig ?	$this->exportDir . DIRECTORY_SEPARATOR . $type : 
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
		Billrun_Factory::log()->log("Placed response at : $exportPath", Zend_Log::DEBUG);
		
		return $exportPath;
	}

}

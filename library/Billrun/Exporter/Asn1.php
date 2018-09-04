<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract exporter bulk (multiple rows at once) to ASN1
 *
 * @package  Billing
 * @since    2.8
 */
abstract class Billrun_Exporter_Asn1 extends Billrun_Exporter_File {
	
	static protected $type = 'asn1';
	
	/**
	 * ASN1 file struct configuration
	 * 
	 * @var array
	 */
	protected $fileStruct = array();
	
	public function __construct($options = array()) {
		parent::__construct($options);
		$this->fileStruct = $this->getConfig('file_structure', array());
	}
	
	/**
	 * in case of ASN1 we will export the entire file in 1 batch, and not per line
	 * 
	 * @param FileStream $fp
	 * @param array $row
	 */
	protected function exportRowToFile($fp, $row) {
		
	}
	
	/**
	 * see parent::handleExport
	 * will export complete file
	 */
	function handleExport() {
		$filePath = $this->getExportFilePath();
		$fp = fopen($filePath, 'w');
		if (!$fp) {
			Billrun_Log::getInstance()->log('ANS1 bulk export: Cannot open file "' . $filePath . '"', Zend_log::ERR);
			return false;
		}
		$dataToExport = $this->getDataToExport();
		$binaryData = Asn1_Utils::encode($dataToExport, $this->fileStruct);
		fwrite($fp, $binaryData);
		fclose($fp);
		$this->afterExport();
		return $dataToExport;
	}
	
}


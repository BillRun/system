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

	protected function respondAFile($responseFilePath, $fileName, $logLine) {
		//move file to export folder
		if(!file_exists($this->exportDir . DIRECTORY_SEPARATOR . $this->type ))  {
			mkdir($this->exportDir . DIRECTORY_SEPARATOR . $this->type );
		}
		$result = rename($responseFilePath, $this->exportDir . DIRECTORY_SEPARATOR . $this->type . DIRECTORY_SEPARATOR .$fileName);
		if($result) {
			parent::respondAFile($responseFilePath, $fileName, $logLine);
		}
	}


}

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
abstract class Billrun_Responder_LocalDir extends Billrun_Responder_FilesResponderBase {

	protected function respondAFile($responseFilePath, $fileName, $logLine) {
		//move file to export folder
		$result = rename($responseFilePath, $this->exportDir . DIRECTORY_SEPARATOR . $this->type . DIRECTORY_SEPARATOR .$fileName);
		if($result) {
			parent::respondAFile($responseFilePath, $fileName, $logLine);
		}
	}


}

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
abstract class Billrun_Responder_Base_Ftp extends Billrun_Responder_Base_FilesResponder {

	const TIMEOUT = 30000;
	const UPLOAD_FILE_MODE = 0666;
	protected $host = null;
	protected $port = 21;
	protected $username = "";
	protected $password = "";

	public function __construct($options) {

		parent::__construct($options);
		if($this->config->ftp) {
			$this->username = $this->config->ftp->username;
			$this->password = $this->config->ftp->password;
		}
	}

	protected function respondAFile($responseFilePath, $fileName, $logLine) {
		$ftpH = ftp_connect($this->host,$this->port, Billrun_Responder_Base_Ftp::TIMEOUT);
		if($ftpH && ftp_login($ftpH, $this->username, $this->password) ) {
			//upload  to the remote ftp.
			$result = ftp_put($ftpH,$this->exportDir . $fileName, $responseFilePath, Billrun_Responder_Base_Ftp::UPLOAD_FILE_MODE );
			ftp_close($ftpH);
			if($result) {
				//update the db line.
				parent::respondAFile($responseFilePath, $fileName, $logLine);
			}
		}

	}


}

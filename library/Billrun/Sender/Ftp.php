<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing sender for ftp
 *
 * @package  Billing
 * @since    5.9
 */
class Billrun_Sender_Ftp extends Billrun_Sender {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'ftp';

	/**
	 * see parent::send()
	 */
	public function send($filePath) {
		Billrun_Factory::dispatcher()->trigger('beforeFTPSendFiles', array($this));
		$files = is_array($filePath) ? $filePath : array($filePath);
		$connectionSettings = $this->options;
		$ftp = (new Billrun_Connector_Ftp($connectionSettings))->connect();
		if (!$ftp) {
			Billrun_Factory::log()->log("Cannot get FTP connector. details: " . print_R($connectionSettings, 1), Zend_Log::ERR);
			return false;
		}

		$remoteDirectory = rtrim(Billrun_Util::getIn($connectionSettings, 'remote_directory', ''), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		$ret = true;
		foreach ($files as $file) {
			if (empty($file) || !file_exists($file)) {
				Billrun_Factory::log()->log("Cannot get file " . $file, Zend_Log::ERR);
				$ret = false;
				continue;
			}
			$fileName = basename($file);
			$remoteFilePath = $remoteDirectory . $fileName;
			if (!ftp_put($ftp->getConnection(), $remoteFilePath, $file, FTP_BINARY)) {
				Billrun_Factory::log()->log("Cannot put file in FTP server. file: " . $file . ", directory: " . $remoteDirectory, Zend_Log::ERR);
				$ret = false;
			}
		}

		Billrun_Factory::dispatcher()->trigger('afterFTPSendFiles', array($this));
		return $ret;
	}

}

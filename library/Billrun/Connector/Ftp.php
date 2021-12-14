<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing connector for ftp
 *
 * @package  Billing
 * @since    5.9
 */
class Billrun_Connector_Ftp extends Billrun_Connector_Base {

	static protected $type = 'ftp';

	/**
	 * see parent::connect()
	 */
	public function connect() {
		$host = Billrun_Util::getIn($this->config, 'host', '');
		$user = Billrun_Util::getIn($this->config, 'user', '');
		$password = Billrun_Util::getIn($this->config, 'password', '');
		$passive = Billrun_Util::getIn($this->config, 'passive', false);

		Billrun_Factory::log()->log("Connecting to FTP server: " . $host, Zend_Log::INFO);
		$ftp = Zend_Ftp::connect($host, $user, $password);
		if (!$ftp) {
			Billrun_Factory::log()->log("Cannot connect to FTP server: " . $host, Zend_Log::WARN);
			return false;
		}
		Billrun_Factory::log()->log("Connected to FTP server: " . $host, Zend_Log::INFO);
		$ftp->setPassive($passive);
		$ftp->setMode(2); // setting ftp mode to binary

		return $ftp;
	}

}

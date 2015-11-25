<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Status model class
 *
 * @package     Models
 * @since       1.0
 */
class statusModel {

	protected $ftp;
	protected $config;
	

	public function __construct() {
		$this->config = Billrun_Factory::config()->getConfigValue('nrtrde.ftp');
		$this->ftp = Zend_Ftp::connect($this->config['host'], $this->config['user'], $this->config['password']);
	}

	/**
	 * method to test the fraud ftp connection
	 */
	public function testFtpConnection() {
		$this->ftp->setPassive(isset($this->config['passive']) ? $this->config['passive'] : false);
		return $this->ftp->isConnected();
	}

	public function getFtpFiles() {
		return $this->ftp->getDirectory($this->config['remote_directory'])->getContents();
	}

	/*
	 * method to query for the last file recieved
	 */

	public function lastFile() {
		$last_file = Billrun_Factory::db()->logCollection()->
				query(array('source' => 'nrtrde', 'received_time' => array('$exists' => true)))->cursor()->
				sort(array('received_time' => -1))->limit(1)->current();
		$hour_warning = strtotime('-1 hour') > strtotime($last_file['received_time']);
		return array('file_name' => $last_file['file_name'], 'received_time' => $last_file['received_time'], 'longer_then_an_hour' => $hour_warning);
	}

}

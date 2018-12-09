<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Prototype of connector
 * Should handle connection to a server/location (for example: FTP, SFTP, local file system, etc...)
 *
 * @package  Billing
 * @since    5.9
 */
abstract class Billrun_Connector_Base {
	
	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'base';
	
	protected $config = array();
	
	public function __construct($config = array()) {
		$this->config = $config;
	}

	/**
	 * Connect function
	 */
	public abstract function connect();
}

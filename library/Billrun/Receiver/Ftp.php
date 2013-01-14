<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing receiver for ftp
 *
 * @package  Billing
 * @since    1.0
 */
class Billrun_Receiver_Ftp extends Billrun_Receiver {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'ftp';

	/**
	 * resource to the ftp server
	 * 
	 * @var Zend_Ftp
	 */
	protected $ftp = null;

	/**
	 * the path on the remote server
	 * 
	 * @param string
	 */
	protected $ftp_path = '/';

	/**
	 * the path on the backup
	 * 
	 * @param string
	 */
	protected $backup_path = '.';

	public function __construct($options) {
		parent::__construct($options);

		$this->ftp = Zend_Ftp::connect($options['ftp']['host'], $options['ftp']['user'], $options['ftp']['password']);
		$this->ftp->setPassive(false);

		if (isset($options['ftp']['remote_directory'])) {
			$this->ftp_path = $options['ftp']['remote_directory'];
		}

		if (isset($options['workspace'])) {
			$this->workspace = $options['workspace'];
		}

		if (isset($options['backup'])) {
			$this->backup_path = $options['backup'];
		}
	}

	/**
	 * method to receive files through ftp
	 * 
	 * @return array list of the files received
	 */
	public function receive() {

		$this->dispatcher->trigger('beforeReceive', array($this));

		$files = $this->ftp->getDirectory($this->ftp_path)->getContents();

		$ret = array();
		foreach ($files as $file) {
			if ($file->isFile()) {
				$this->log->log("FTP: Download file " . $file->name . " from remote host", Zend_Log::INFO);
				$file->saveToPath($this->workspace);
				$ret[] = $this->workspace . $file->name;
			}
		}

		$this->dispatcher->trigger('afterReceive', array($this, $ret));

		return $ret;
	}

}

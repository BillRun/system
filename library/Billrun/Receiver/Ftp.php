<?php

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

	}

	/**
	 * method to receive files through ftp
	 * 
	 * @return array list of the files received
	 */
	public function receive() {

		$this->dispatcher->trigger('beforeFTPReceive', array($this));

		$files = $this->ftp->getDirectory($this->ftp_path)->getContents();
		
		$ret = array();
		foreach ($files as $file) {
			if ($file->isFile()) {
				$this->log->log("FTP: Download file " . $file->name . " from remote host", Zend_Log::INFO);
				if ($file->saveToPath($this->workspace) === FALSE) {
					$this->log->log("FTP: failed to download " . $file->name . " from remote host", Zend_Log::ALERT);
					continue;
				}
				$received_path = $this->workspace . $file->name;
				$this->dispatcher->trigger('afterFTPFileReceived', array(&$received_path, $file, $this));
				$this->logDB($received_path);
				$ret[] = $received_path;
			}
		}

		$this->dispatcher->trigger('afterFTPReceived', array($this, $ret));

		return $ret;
	}

}

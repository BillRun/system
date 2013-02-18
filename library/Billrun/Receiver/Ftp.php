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
	 * A regular expression to identify the files that should be downloaded
	 * 
	 * @param string
	 */
	protected $filenameRegex = '/.*/';
	
	protected $ftpConfig = false;

	public function __construct($options) {
		parent::__construct($options);
		$this->ftpConfig = is_array( $options['ftp']['host'] ) ? Billrun_Util::joinSubArraysOnKey($options['ftp'],2) : array($options['ftp']);

		if (isset($options['ftp']['remote_directory'])) {
			$this->ftp_path = $options['ftp']['remote_directory'];
		}

		if (isset($options['workspace'])) {
			$this->workspace = $options['workspace'];
		}
		
		if (isset($options['filename_regex'])) {
			$this->filenameRegex = $options['filename_regex'];
		}


	}

	/**
	 * method to receive files through ftp
	 * 
	 * @return array list of the files received
	 */
	public function receive() {
		$ret = array();
		$this->dispatcher->trigger('beforeFTPReceiveFullRun', array($this));
		
		foreach($this->ftpConfig as $hostName => $config) {
			if(!is_array($config)) { continue; }

			if(is_numeric($hostName)) { $hostName='';}
			
			$this->ftp = Zend_Ftp::connect($config['host'], $config['user'], $config['password']);
			$this->ftp->setPassive(false);

			$this->dispatcher->trigger('beforeFTPReceive', array($this, $hostName));
			$hostRet = $this->receiveFromHost($hostName, $config);
			$this->dispatcher->trigger('afterFTPReceived', array($this, $hostRet, $hostName));
			
			$ret = array_merge($ret, $hostRet);	
		}
		
		$this->dispatcher->trigger('afterFTPReceivedFullRun', array($this, $ret, $hostName));
		return $ret;
	}
	
	
	protected function receiveFromHost($hostName,$config) {
			$ret = array();
			$files = $this->ftp->getDirectory($config['remote_directory'])->getContents();

			foreach ($files as $file) {
				if ($file->isFile() && $this->isFileValid($file->name,$file->path)) {
					if($this->isFileReceived($file->name,$this->getType())) {
							if(Billrun_Factory::config()->isProd()) {
								$file->delete();
							}
							continue;
					}
					$this->log->log("FTP: Download file " . $file->name . " from remote host", Zend_Log::INFO);
					if ($file->saveToPath($this->workspace) === FALSE) {
						$this->log->log("FTP: failed to download " . $file->name . " from remote host", Zend_Log::ALERT);
						continue;
					}
					$received_path = $this->workspace . $file->name;
					$this->dispatcher->trigger('afterFTPFileReceived', array(&$received_path, $file, $this, $hostName));
					if($this->logDB($received_path, $hostName)) {
						$ret[] = $received_path;
					}
				}
			}
			return $ret;
	}
		
	/**
	 * Verify that the file is a valid file. 
	 * @return boolean false if the file name should not be received true if it should.
	 */
	protected function isFileValid($filename, $path) {
		return preg_match($this->filenameRegex, $filename);
	}

}

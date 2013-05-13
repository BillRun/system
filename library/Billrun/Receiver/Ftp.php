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

	
	protected $ftpConfig = false;

	public function __construct($options) {
		parent::__construct($options);
		$this->ftpConfig = isset($options['ftp']['host']) ? array($options['ftp']) : $options['ftp'];

		if (isset($options['ftp']['remote_directory'])) {
			$this->ftp_path = $options['ftp']['remote_directory'];
		}

		if (isset($options['workspace'])) {
			$this->workspace = $options['workspace'];
		}

		Zend_Ftp_Factory::registerParserType(Zend_Ftp::UNKNOWN_SYSTEM_TYPE, 'Billrun_Receiver_NsnFtpParser');
	}

	/**
	 * method to receive files through ftp
	 * 
	 * @return array list of the files received
	 */
	public function receive() {
		$ret = array();
		Billrun_Factory::dispatcher()->trigger('beforeFTPReceiveFullRun', array($this));
		
		foreach($this->ftpConfig as $hostName => $config) {
			if(!is_array($config)) { continue; }

			if( is_numeric($hostName) ) { $hostName='';}
			
			$this->ftp = Zend_Ftp::connect($config['host'], $config['user'], $config['password']);
			$this->ftp->setPassive(isset($config['passive']) ? $config['passive'] : false);

			Billrun_Factory::dispatcher()->trigger('beforeFTPReceive', array($this, $hostName));
			try {
				$hostRet = $this->receiveFromHost($hostName, $config);
			} catch(Exception $e) {
				Billrun_Factory::log()->log("FTP: Fail when downloading from : $hostName with exception : ".$e, Zend_Log::DEBUG);
			}
			Billrun_Factory::dispatcher()->trigger('afterFTPReceived', array($this, $hostRet, $hostName));
			
			$ret = array_merge($ret, $hostRet);	
		}
		
		Billrun_Factory::dispatcher()->trigger('afterFTPReceivedFullRun', array($this, $ret, $hostName));
		return $ret;
	}
	
	/**
	 * Receive files from the ftp host.
	 * @param type $hostName the ftp hostname/alias
	 * @param type $config the ftp configuration
	 * @return array conatining the path to the received files.
	 */
	protected function receiveFromHost($hostName,$config) {
			$ret = array();
			$files = $this->ftp->getDirectory($config['remote_directory'])->getContents();
			Billrun_Factory::log()->log("FTP: Starting to receive from remote host : $hostName", Zend_Log::DEBUG);
			foreach ($files as $file) {
				$extraData = array();
				Billrun_Factory::log()->log("FTP: Found file " . $file->name . " on remote host", Zend_Log::DEBUG);
				Billrun_Factory::dispatcher()->trigger('beforeFTPFileReceived', array(&$file, $this, $hostName, &$extraData));
				if ($file->isFile() && $this->isFileValid($file->name,$file->path)) {
					Billrun_Factory::log()->log("FTP: Download file " . $file->name . " from remote host", Zend_Log::INFO);
					if ($file->saveToPath($this->workspace, null, 0, true) === FALSE) { // the last arg declare try to recover on failure
						Billrun_Factory::log()->log("FTP: failed to download " . $file->name . " from remote host", Zend_Log::ALERT);
						continue;
					}
					
					$received_path = $this->workspace . $file->name;
					Billrun_Factory::dispatcher()->trigger('afterFTPFileReceived', array(&$received_path, $file, $this, $hostName, $extraData));
					
					$local_size = filesize($received_path);
					$remote_size = $file->size();
					if ($local_size !== $remote_size) {
						Billrun_Factory::log()->log("FTP: The remote file size (" . $remote_size . ") is different from local file size (" . $local_size . ")", Zend_Log::DEBUG);
						continue;
					}
					
					if($this->logDB($received_path, $hostName , $extraData)) {
						$ret[] = $received_path;						
						// delete the file after downloading and store it to processing queue
						if( Billrun_Factory::config()->isProd() && (isset($config['delete_received']) && $config['delete_received'] ) ) {
							Billrun_Factory::log()->log("FTP: Deleting file {$file->name} from remote host ", Zend_Log::DEBUG);
							 $file->delete();
						}
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

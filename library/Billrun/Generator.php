<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract generator class
 *
 * @package  Billing
 * @since    0.5
 */
abstract class Billrun_Generator extends Billrun_Base {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'generator';

	/**
	 * the directory where the generator store files
	 * @var string
	 */
	protected $export_directory;

	/**
	 * load balanced between db primary and secondary
	 * @var int
	 */
	protected $loadBalanced = 0;

	/**
	 * whether to auto create the export directory
	 * @var boolean 
	 */
	protected $auto_create_dir = true;
	
	/**
	 * for SSH connection
	 */
	protected $ssh = null;
	
	/**
	 * the directory where to send(copy) the file
	 * @var string
	 */
	protected $export_dir; 
	
	/**
	 * the name of the file to transfer
	 * @var string
	 */
	protected $filename;
	
	/**
	 * whether to move the exported
	 * @var boolean
	 */
	protected $move_exported;

        /**
         * file name config - in case of customized file name.
         * @var array
         */
        protected $file_name_config;
	/**
	 * constructor
	 * 
	 * @param array $options parameters for the generator to dynamically behaiour
	 */
	public function __construct($options) {

		parent::__construct($options);

		if (isset($options['export_directory'])) {
			if (!isset($options['disable_stamp_export_directory']) || !$options['disable_stamp_export_directory']) {
				$this->export_directory = Billrun_Util::getBillRunSharedFolderPath($options['export_directory'] . DIRECTORY_SEPARATOR . $this->stamp);
			} else {
				$this->export_directory = Billrun_Util::getBillRunSharedFolderPath($options['export_directory']);
			}
		} else {
			$this->export_directory = Billrun_Util::getBillRunSharedFolderPath(Billrun_Factory::config()->getConfigValue(static::$type . '.export') . DIRECTORY_SEPARATOR . $this->stamp); //__DIR__ . '/../files/';
		}

		if (isset($options['move_exported'])) {
			$this->move_exported = $options['move_exported'];
		} else {
			$this->move_exported = Billrun_Factory::config()->getConfigValue(static::$type . '.move_exported', false);
		}

		if (isset($options['auto_create_dir'])) {
			$this->auto_create_dir = $options['auto_create_dir'];
		}

		$this->loadBalanced = Billrun_Factory::config()->getConfigValue('generate.loadBalanced', 0);

		if ($this->auto_create_dir && !file_exists($this->export_directory)) {
			mkdir($this->export_directory, 0777, true);
			chmod($this->export_directory, 0777);
		}
		if (isset($options['export']['type']) && $options['export']['type'] == 'ssh') {
			if (isset($options['export']['user'])) {
				$user = $options['export']['user'];
			} else {
				$user = Billrun_Factory::config()->getConfigValue(static::$type . '.export.user');
			}
			if (isset($options['export']['pw'])) {
				$password = $options['export']['pw'];
			} else {
				$password = Billrun_Factory::config()->getConfigValue(static::$type . '.export.pw');
			}
			if (isset($options['export']['server'])) {
				$server = $options['export']['server'];
			} else {
				$server = Billrun_Factory::config()->getConfigValue(static::$type . '.export.server');
			}
			$this->defineSshConnection($user, $password, $server);
		}
		
                if (isset($options['file_name']) && !empty($options['file_name'])){
                        $this->file_name_config = $options['file_name'];
                }
                
		if (isset($options['export']['dir'])) {
			$this->export_dir = Billrun_Util::getBillRunSharedFolderPath($options['export']['dir']);
		} else {
			$this->export_dir = Billrun_Util::getBillRunSharedFolderPath(Billrun_Factory::config()->getConfigValue(static::$type . '.export.dir'));
		}
		
		if(!empty($options['page'])) {
			$this->page = intval($options['page']);
		}
		
		if(!empty($options['size'])) {
			$this->limit = intval($options['size']);
		}
	}

	public function getExportDirectory() {
		return $this->export_directory;
	}
	
	/**
	 * load the container the need to be generate
	 */
	abstract public function load();

	/**
	 * execute the generate action
	 */
	abstract public function generate();
	
	/**
	 * connecting to server by SSH Protocol
	 */
	protected function defineSshConnection($user, $password, $server) {
		
		$port = 22;
		$auth = array(
			'password' => $password,
		);			
		$hostAndPort = $server;
		$hostAndPort .= ':'.$port;
				
		$this->ssh = new Billrun_Ssh_Seclibgateway($hostAndPort, $auth, array());
		$this->ssh->connect($user);					
	}
	
	
	/**
	 * copy the file to the location defined
	 * @since 5.0
	 */
	public function move(){
		if (!is_null($this->ssh)){
			$this->ssh->put($this->export_directory . '/' . $this->filename, $this->export_dir . '/' . $this->filename); // instead of test 2&3 put the name of the generated file.
		}
		else{
			if ($this->move_exported) {
				copy($this->export_directory . '/' . $this->filename, $this->export_dir . '/' . $this->filename); // change to function rename instead of copy
			}
		}
	}
	
	/*
	* if folder doesnt exists add it and change permission to write to it 
	*/
	public function addFolder($path) {
		if (!file_exists($path)) {
			$old_umask = umask(0);
			mkdir($path, octdec(Billrun_Factory::config()->getConfigValue(static::$type.'.new_folder_permissions','0775')), true);
			umask($old_umask);
		}
	}
	
	public function shouldFileBeMoved() {
		return true;
	}
	
        public function getFileNameConfig(){
            return $this->file_name_config;
        }
}

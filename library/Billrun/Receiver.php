<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract receiver class
 *
 * @package  Billing
 * @since    0.5
 */
abstract class Billrun_Receiver extends Billrun_Base {

	use Billrun_Traits_FileActions;

	/**
	 * Type of object
	 *
	 * @var string
	 */
	static protected $type = 'receiver';

	/**
	 * the receiver workspace path of files
	 * this is the place where the files will be received
	 * 
	 * @var string
	 */
	protected $workspace;

	/**
	 * A regular expression to identify the files that should be downloaded
	 * 
	 * @param string
	 */
	protected $filenameRegex = '/.*/';

	public function __construct($options = array()) {
		parent::__construct($options);

		if (!empty($options['filename_regex']) || !empty($options['receiver']['connections'][0]['filename_regex'])) {
			$this->filenameRegex = !empty($options['receiver']['connections'][0]['filename_regex']) ? $options['receiver']['connections'][0]['filename_regex'] : $options['filename_regex'];
		}
		if (isset($options['receiver']['limit']) && $options['receiver']['limit']) {
			$this->setLimit($options['receiver']['limit']);
		}
		if (isset($options['receiver']['preserve_timestamps'])) {
			$this->preserve_timestamps = $options['receiver']['preserve_timestamps'];
		}
		if (isset($options['backup_path'])) {
			$this->backupPaths = Billrun_Util::getBillRunSharedFolderPath($options['backup_path']);
		} else {
			$this->backupPaths = Billrun_Util::getBillRunSharedFolderPath(Billrun_Factory::config()->getConfigValue($this->getType() . '.backup_path', './backups/' . $this->getType()));
		}
		if (isset($options['receiver']['backup_granularity']) && $options['receiver']['backup_granularity']) {
			$this->setGranularity((int) $options['receiver']['backup_granularity']);
		}

		if (Billrun_Util::getFieldVal($options['receiver']['backup_date_format'], false)) {
			$this->setBackupDateDirFromat($options['receiver']['backup_date_format']);
		}

		if (isset($options['receiver']['orphan_time']) && ((int) $options['receiver']['orphan_time']) > 900) {
			$this->file_fetch_orphan_time = $options['receiver']['orphan_time'];
		}
		
		$this->workspace = Billrun_Util::getBillRunSharedFolderPath(Billrun_Util::getFieldVal($options['workspace'], 'workspace'));
	}

	/**
	 * general function to receive
	 *
	 * @return array list of files received
	 */
	abstract protected function receive();

	/**
	 * method to log the processing
	 * 
	 * @todo refactoring this method
	 */
	protected function logDB($fileData) {
		$oldStamp = $fileData['stamp'];
		Billrun_Factory::dispatcher()->trigger('beforeLogReceiveFile', array(&$fileData, $this));

		$query = array(
			'stamp' => $oldStamp,
			'received_time' => array('$exists' => false)
		);

		$addData = array(
			'received_hostname' => Billrun_Util::getHostName(),
			'received_time' => new MongoDate()
		);

		$update = array(
			'$set' => array_merge($fileData, $addData)
		);

		if (empty($query['stamp'])) {
			Billrun_Factory::log("Billrun_Receiver::logDB - got file with empty stamp :  {$fileData['stamp']}", Zend_Log::NOTICE);
			return FALSE;
		}

		$log = Billrun_Factory::db()->logCollection();
		$result = $log->update($query, $update);

		if ($result['ok'] != 1 || $result['n'] != 1) {
			Billrun_Factory::log("Billrun_Receiver::logDB - Failed when trying to update a file log record " . $fileData['file_name'] . " with stamp of : {$fileData['stamp']}", Zend_Log::NOTICE);
		}

		return $result['n'] == 1 && $result['ok'] == 1;
	}
	
	public static function getInstance() {
		$args = func_get_args();
		$stamp = md5(static::class . serialize($args));
		if (isset(self::$instance[$stamp])) {
			return self::$instance[$stamp];
		}

		$type = $args[0]['receiver']['receiver_type'];
		unset($args[0]['receiver']['receiver_type']);
		$args = $args[0];
		$args['type'] = $args['file_type'];
		$class = 'Billrun_Receiver_' . ucfirst($type);
		if (!@class_exists($class, true)) {
			Billrun_Factory::log("Can't find class: " . $class, Zend_Log::EMERG);
			return false;
		}
		self::$instance[$stamp] = new $class($args);
		return self::$instance[$stamp];
	}
	
	public function getReceiver() {
		
	}

}

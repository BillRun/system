<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
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

		if (isset($options['filename_regex'])) {
			$this->filenameRegex = $options['filename_regex'];
		}
		if (isset($options['receiver']['limit']) && $options['receiver']['limit']) {
			$this->setLimit($options['receiver']['limit']);
		}
		if (isset($options['receiver']['preserve_timestamps'])) {
			$this->preserve_timestamps = $options['receiver']['preserve_timestamps'];
		}
		if (isset($options['backup_path'])) {
			$this->backupPaths = $options['backup_path'];
		} else {
			$this->backupPaths = Billrun_Factory::config()->getConfigValue($this->getType() . '.backup_path', array('./backups/' . $this->getType()));
		}
		if (isset($options['receiver']['backup_granularity']) && $options['processor']['backup_granularity']) {
			$this->setGranularity((int) $options['processor']['backup_granularity']);
		}
	}

	/**
	 * general function to receive
	 *
	 * @return array list of files received
	 */
	abstract public function receive();

	/**
	 * method to log the processing
	 * 
	 * @todo refactoring this method
	 */
	protected function logDB($path, $remoteHost = null, $extraData = false) {
		$log = Billrun_Factory::db()->logCollection();

		$log_data = array(
			'source' => static::$type,
			'path' => $path,
			'file_name' => basename($path),
		);

		if (!is_null($remoteHost)) {
			$log_data['retrieved_from'] = $remoteHost;
		}

		if ($extraData) {
			$log_data['extra_data'] = $extraData;
		}

		$log_data['stamp'] = md5(serialize($log_data));
		$log_data['received_time'] = date(self::base_dateformat);

		Billrun_Factory::dispatcher()->trigger('beforeLogReceiveFile', array(&$log_data, $this));
		$entity = new Mongodloid_Entity($log_data);
		if ($log->query('stamp', $entity->get('stamp'))->count() > 0) {
			Billrun_Factory::log()->log("Billrun_Receiver::logDB - DUPLICATE! trying to insert duplicate log file " . $log_data['file_name'] . " with stamp of : {$entity->get('stamp')}", Zend_Log::NOTICE);
			return FALSE;
		}

		return $entity->save($log);
	}

}

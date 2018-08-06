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
		Billrun_Factory::dispatcher()->trigger('beforeLogReceiveFile', array(&$fileData, $this));

		$query = array(
			'stamp' => $fileData['stamp'],
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

		$type = $args[0]['type'];
		unset($args[0]['type']);
		$args = $args[0];

		if (!$config_type = Billrun_Factory::config()->{$type}) {
			$config_type = array_filter(Billrun_Factory::config()->file_types->toArray(), function($fileSettings) use ($type) {
				return $fileSettings['file_type'] === $type && Billrun_Config::isFileTypeConfigEnabled($fileSettings);
			});
			if ($config_type) {
				$config_type = current($config_type);
			}
		}
		$called_class = get_called_class();

		if ($called_class && Billrun_Factory::config()->getConfigValue($called_class)) {
			$args = array_merge(Billrun_Factory::config()->getConfigValue($called_class)->toArray(), $args);
		}

		$class_type = $type;
		if ($config_type) {
			if (is_object($config_type)) {
				$config_type = $config_type->toArray();
			}
			$args = array_merge($config_type, $args);
			if (isset($args['receiver_type']) && $args['connections']) {
				$class_type = $args['type'] = $args['receiver_type'];
				$args['receiver']['connections'] = $args['connections'];
			} else if (isset($config_type[$called_class::$type]) &&
				isset($config_type[$called_class::$type]['type'])) {
					$class_type = $config_type[$called_class::$type]['type'];
					$args['type'] = $type;
			} else if(!empty($config_type[$called_class::$type]['type_mapping'])) {
				foreach (@$config_type[$called_class::$type]['type_mapping'] as $typeConfig) {
					$match = true;
					foreach (@$typeConfig['config'] as $field => $value) {
						$match &= Billrun_Factory::config()->getConfigValue($field,null) == $value;
					}
					if($match) {
						$class_type = $typeConfig['type'];
						$args['type'] = $type;
						break;
					}
				}
			}	
		}
		$class = $called_class . '_' . ucfirst($class_type);
		if (!@class_exists($class, true)) {
			// try to search in external sources (application/helpers)
			$external_class = str_replace('Billrun_', '', $class);
			if (($pos = strpos($external_class, "_")) !== FALSE) {
				$namespace = substr($external_class, 0, $pos);
				Yaf_Loader::getInstance(APPLICATION_PATH . '/application/helpers')->registerLocalNamespace($namespace);
			}
			// TODO: We need a special indication for this case.
			// There are places in the code that try to create clases in a loop,
			// if the class doesn't exist it is a critical error that should stop the operation
			// of most executed logic.
			if (!@class_exists($external_class, true)) {
				Billrun_Factory::log("Can't find class: " . $class, Zend_Log::EMERG);
				return false;
			}
			$class = $external_class;
		}

		self::$instance[$stamp] = new $class($args);
		return self::$instance[$stamp];
	}

}

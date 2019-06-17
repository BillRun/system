<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This Trait is used to allow classes to  parse file names an  extract  data  from them. 
 *
 * @author eran
 */
trait Billrun_Traits_FileActions {

	/**
	 * the backup sequence file number digits granularity 
	 * (1=batches of 10 files  in each directory, 2= batches of 100, 3= batches of 1000,etc...)
	 * @param integer
	 */
	protected $backup_seq_granularity = 2; // 100 files in each directory.

	/**
	 * the backup date fromat to save files under 
	 * 
	 * @param integer
	 */
	protected $backup_date_dir_format = "Ym"; // seperate files monthly

	/**
	 * An array of path to back files to.
	 * @var Array containg the paths  to save backups to.
	 */
	protected $backupPaths = array();

	/**
	 *
	 * @var boolean whether to preserve the modification timestamps of the received files
	 */
	protected $preserve_timestamps = true;

	/**
	 * @var boolean whether to preserve the modification timestamps of the received files
	 */
	protected $file_fetch_orphan_time = 3600;
	

	/**
	 * Get the data the is stored in the file name.
	 * @return an array containing the sequence data. ie:
	 * 			array(seq => 00001, date => 20130101 )
	 */
	public function getFilenameData($filename, $configPrefix) {
		return array(
			'seq' => Billrun_Util::regexFirstValue(Billrun_Factory::config()->getConfigValue($configPrefix . ".sequence_regex.seq", "/\D(\d+)\D/"), $filename),
			'date' => Billrun_Util::regexFirstValue(Billrun_Factory::config()->getConfigValue($configPrefix . ".sequence_regex.date", "/\D(20\d{4})\D/"), $filename),
			'time' => Billrun_Util::regexFirstValue(Billrun_Factory::config()->getConfigValue($configPrefix . ".sequence_regex.time", "/20\d{4}.*\D(\d{4,6})\D/"), $filename),
		);
	}

	/**
	 * Verify that the file is a valid file. 
	 * @return boolean false if the file name should not be received true if it should.
	 */
	protected function isFileValid($filename, $path) {
		//igonore hidden files by default
		return preg_match(( $this->filenameRegex ? $this->filenameRegex : "/^[^\.]/"), $filename);
	}

	/**
	 * method to check if the file already processed
	 */
	protected function isFileReceived($filename, $type, $more_fields = array()) {
		$log = Billrun_Factory::db()->logCollection();
		$logData = $this->getFileLogData($filename, $type, $more_fields);
		$query = array(
			'stamp' => $logData['stamp'],
			'source' => $type,
			'file_name' => $filename,
		);

		if (!empty($more_fields)) {
			$query = array_merge($query, Billrun_Util::arrayToMongoQuery($query));
		}

		//Billrun_Factory::dispatcher()->trigger('alertisFileReceivedQuery', array(&$query, $type, $this));
		$resource = $log->query($query)->cursor()->limit(1);
		return $resource->count() > 0;
	}

	/**
	 * Method to check if the file is allready being received 
	 * @return bollean true  if the file wasn't receive and can be fetched to the workspace or false if another process allready received the file.
	 */
	protected function lockFileForReceive($filename, $type, $more_fields = array(), $orphan_window = false) {
		$log = Billrun_Factory::db()->logCollection();
		$orphan_window = $orphan_window ? $orphan_window : $this->file_fetch_orphan_time;
		$logData = $this->getFileLogData($filename, $type, $more_fields);
		$query = array(
			'stamp' => $logData['stamp'],
			'file_name' => $filename,
			'fetching_time' => array('$lt' => new MongoDate(time() - $orphan_window)),
			'received_time' => array('$exists' => false)
		);

		$update = array(
			'$set' => array(
				'fetching_time' => new MongoDate(time()),
				'fetching_host' => Billrun_Util::getHostName(),
			),
			'$setOnInsert' => $logData
		);
		try {
			$result = $log->update($query, $update, array('upsert' => 1, 'w' => 1)); // TODO: this will not work with PHP 7 (needs to be true instead of 1)
		} catch (Exception $e) {
			if ($e->getCode() == 11000) {
				Billrun_Factory::log()->log("Billrun_Traits_FileActions::lockFileForReceive - Trying to relock  a file the was already beeen locked : " . $filename . " with stamp of : {$logData['stamp']}", Zend_Log::DEBUG);
			} else {
				throw $e;
			}
			return FALSE;
		}
		return $result['n'] == 1 && $result['ok'] == 1; // TODO: this will not work with PHP 7 (there is no 'n' in the response)
	}

	/**
	 * build the structure that will be used as a base to log the file in the DB, and generate the file uniqe stamp.
	 * @param type $filename
	 * @param type $type
	 * @param type $more_fields
	 * @return type
	 */
	protected function getFileLogData($filename, $type, $more_fields = array()) {
		$log_data = array(
			'source' => $type,
			'file_name' => $filename,
		);

		if (!empty($more_fields)) {
			$log_data = array_merge($log_data, $more_fields);
		}

		$log_data['stamp'] = md5(serialize($log_data));
		return $log_data;
	}

	/**
	 * Backup the current processed file to the proper backup paths
	 * @param type $move should the file be moved when the backup ends?
	 */
	protected function backup($filePath, $filename, $backupPaths, $retrievedHostname = false, $move = false) {
		$backupPaths = is_array($backupPaths) ? $backupPaths : array($backupPaths);

		$seqData = $this->getFilenameData($filename, $this->getType());
		$backedTo = array();
		for ($i = 0; $i < count($backupPaths); $i++) {
			$backupPath = $this->generateBackupPath($backupPaths[$i], $seqData, $retrievedHostname);
			$this->prepareBackupPath($backupPath);
			if ($this->backupToPath($filePath, $backupPath, $this->preserve_timestamps, !($move && $i + 1 == count($backupPaths))) === TRUE) {
				Billrun_Factory::log()->log("Success backup file " . $filePath . " to " . $backupPath, Zend_Log::INFO);
				$backedTo[] = $backupPath;
			} else {
				Billrun_Factory::log()->log("Failed backup file " . $filePath . " to " . $backupPath, Zend_Log::WARN);
			}
		}

		return $backedTo;
	}

	/**
	 * Generate a backup path to a given file.
	 * @param type $basePath the base of the backup path.
	 * @param type $fileSeqData the file sequence data.
	 * @param type $retrievedHostname the host the file was retrived from.
	 * @return string the path to place the file in.
	 */
	public function generateBackupPath($basePath, $fileSeqData, $retrievedHostname = false) {
		$backupPath = $basePath;
		$date = Billrun_Util::fixShortHandYearDate($fileSeqData['date'], $this->backup_date_dir_format); //HACK to fix short hand year.
		$backupPath .= ($retrievedHostname ? DIRECTORY_SEPARATOR . $retrievedHostname : ""); //If theres more then one host or the files were retrived from a named host backup under that host name
		$backupPath .= DIRECTORY_SEPARATOR . date($this->backup_date_dir_format, (strtotime($date) > 0 ? strtotime($date) : time())); // if the file name has a date  save under that date else save under tthe current month
		$backupPath .= ($fileSeqData['seq'] ? DIRECTORY_SEPARATOR . substr($fileSeqData['seq'], 0, - $this->backup_seq_granularity) : ""); // break the date to sequence number with varing granularity

		return $backupPath;
	}

	/**
	 * method to backup the processed file
	 * @param string $trgtPath  the path to backup the file to.
	 * @param boolean $copy copy or rename (move) the file to backup
	 * 
	 * @return boolean return true if success to backup
	 */
	public function backupToPath($srcPath, $trgtPath, $preserve_timestamps = true, $copy = true) {
		if ($copy) {
			$callback = "copy";
		} else {
			$callback = "rename"; // php move
		}

		if (!file_exists($trgtPath)) {
			@mkdir($trgtPath, 0777, true);
		}

		$filename = basename($srcPath);
		$target_path = $trgtPath . DIRECTORY_SEPARATOR . $filename;
		Billrun_Factory::log()->log("Backing up file from : " . $srcPath . " to :  " . $trgtPath, Zend_Log::INFO);
		$timestamp = filemtime($srcPath); // this will be used after copy/move to preserve timestamp
		$ret = @call_user_func_array($callback, array(
				$srcPath,
				$target_path,
		));
		if ($preserve_timestamps) {
			Billrun_Util::setFileModificationTime($target_path, $timestamp);
		}

		return $ret;
	}

	/**
	 * Make sure the file can be coppied/moved to a given backup path.
	 * @param type $paththe path to prepare for backup.
	 * @return boolean
	 */
	protected function prepareBackupPath($path) {
		if (!file_exists($path) && !@mkdir($path, 0777, true)) {
			Billrun_Factory::log()->log("Can't create backup path or is not a directory " . $path, Zend_Log::WARN);
			return FALSE;
		}

		// in case the path exists but it's a file
		if (!is_dir($path)) {
			Billrun_Factory::log()->log("The path " . $path . " is not directory", Zend_Log::WARN);
			return FALSE;
		}
		return $path;
	}

	/**
	 * Set the backup granularity.
	 * @param int $grn the digit  granularity count.
	 */
	protected function setGranularity($grn) {
		$this->backup_seq_granularity = intval($grn);
	}

	/**
	 * Set the backup date seperation directory format.
	 * @param string $dateFromat the date fromat (http://php.net/manual/en/function.date.php) to use.
	 */
	protected function setBackupDateDirFromat($dateFromat) {
		if (strtotime(date($dateFromat))) {//check that the format is legitimate
			$this->backup_date_dir_format = $dateFromat;
		}
	}

	/**
	 * Remove the file if its backed up if not back it  in a default folder.
	 * @param type $filepath the stamp to the file to remove from workspace
	 */
	protected function removeFromWorkspace($filestamp) {
		$file = Billrun_Factory::db()->logCollection()->query(array('stamp' => $filestamp))->cursor()->limit(1)->current();
		if (!$file->isEmpty()) {
			$defaultBackup = Billrun_Factory::config()->getConfigValue('backup.default_backup_path', FALSE);
			if (empty($file['backed_to'])) {
				$backupPaths = !empty($this->backupPaths) ? (array) $this->backupPaths : (!empty($defaultBackup) ? (array) $defaultBackup : array('./backup/' . $this->getType()));
				Billrun_Factory::log()->log("Backing up and moving file {$file['path']} to - " . implode(",", $backupPaths), Zend_Log::INFO);
				$this->backup($file['path'], basename($file['path']), $backupPaths, $file['retrieved_from'], true);
			} else {
				Billrun_Factory::log()->log("File {$file['path']}  already backed up to :" . implode(",", $file['backed_to']), Zend_Log::INFO);
				Billrun_Factory::log()->log("Removing file {$file['path']} from the workspace", Zend_Log::INFO);
				unlink($file['path']);
			}
		}
	}

	/**
	 * retrive the type name of the using class instance.
	 * @return string the  class instance name
	 */
	abstract function getType();
}

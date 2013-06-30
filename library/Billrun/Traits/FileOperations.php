<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * A Trait to add common cdr input file operations. 
 * @since    0.5
 */
trait  Billrun_Traits_FileOperations {
	
		/**
	 * the backup sequence file number digits granularity 
	 * (1=batches of 10 files  in each directory, 2= batches of 100, 3= batches of 1000,etc...)
	 * @param integer
	 */
	protected $backup_seq_granularity = 2;
	
	protected function getFileBackupPath($basePath, $filename, $logLine) {		
			$seqData = $this->getFileSequenceData($logLine['source'],$filename);		
			$backupPath = $basePath;
			$backupPath .= ($logLine['retrieved_from'] ? DIRECTORY_SEPARATOR . $logLine['retrieved_from'] : "");//If theres more then one host or the files were retrived from a named host backup under that host name
			$backupPath .= DIRECTORY_SEPARATOR . ($seqData['date'] ? $seqData['date'] : date("Ym"));// if the file name has a date  save under that date else save under tthe current month
			$backupPath .= ($seqData['seq'] ? DIRECTORY_SEPARATOR . substr($seqData['seq'], 0, -$this->backup_seq_granularity) : ""); // brak the date to sequence number with varing granularity

		return $backupPath;
	}
	
	/**
	 * An helper function for the Billrun_Common_FileSequenceChecker  ( helper :) ) class.
	 * Retrive the ggsn file date and sequence number
	 * @param string $type the configuration type to retrive.
	 * @param string $filename the full file name.
	 * @return boolea|Array false if the file couldn't be parsed or an array containing the file sequence data
	 *						[seq] => the file sequence number.
	 *						[date] => the file date.  
	 */
	public function getFileSequenceData($type,$filename) {
		return array(
				'seq' => Billrun_Util::regexFirstValue(Billrun_Factory::config()->getConfigValue("$type.sequence_regex.seq","/(\d+)/"), $filename),
				'zone' =>Billrun_Util::regexFirstValue(Billrun_Factory::config()->getConfigValue("$type.sequence_regex.zone","//"), $filename),
				'date' =>Billrun_Util::regexFirstValue(Billrun_Factory::config()->getConfigValue("$type.sequence_regex.date","/(20\d{6})/"), $filename),
				'time' => Billrun_Util::regexFirstValue(Billrun_Factory::config()->getConfigValue("$type.sequence_regex.time","/\D(\d{4,6})\D/"), $filename)	,
			);
	}
	
	/**
	 * Backups (Copy) a list of file to a location that is retrived from the configuration.
	 * @param type $filepaths	the path to  the current location of the files
	 * @param type $type		the type (name) of the object that is doing the backup 
	 *							( will be used to check the configuration for the base path to copy to)
	 * @param string $path		the destination path to transfer the path to.
	 * @param type $subpath		(Optional) a sub path to save the files under the third pary path in the configuration.
	 * @return bool				true  if all the files  where successfuly copied false otherwise.
	 */
	protected function backupToPath($filepaths, $type, $path, $subpath = false) { 
		
		if(!$path) return FALSE;
		if( $subpath ) {
			$path = $path . DIRECTORY_SEPARATOR . $subpath;
		}
		
		if(!file_exists($path)) {
			Billrun_Factory::log()->log("Making directory : $path" , Zend_Log::ERR);
			if(!mkdir($path, 0777, true)) {
				Billrun_Factory::log()->log("Failed when trying to create directory : $path" , Zend_Log::ERR);
			}
		}
		
		Billrun_Factory::log()->log("Saving retrived files to  : $path" , Zend_Log::DEBUG);
		$ret = true;
		foreach($filepaths as $srcPath) {
			if(!copy($srcPath, $path .DIRECTORY_SEPARATOR. basename($srcPath))) {
				Billrun_Factory::log()->log(" Failed when trying to save file : ".  basename($srcPath)." to third party path : $path" , Zend_Log::ERR);
				$ret = FALSE;
			}
		}
		return $ret;
	}

}

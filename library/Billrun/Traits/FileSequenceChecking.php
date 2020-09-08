<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * File Sequencing checker triat
 *
 * @package  Billing
 * @since    0.2
 */
trait Billrun_Traits_FileSequenceChecking {

	/**
	 * Holds sequence checkers 
	 * @var Array of Billrun_Common_FileSequenceChecker.
	 */
	protected $hostSequenceCheckers = array();

	/**
	 * Initilaize  sequence checker  for a given host if none exists.
	 * @param type $hostname string a uniqe name of the host
	 */
	protected function setFilesSequenceCheckForHost($hostname) {
		if (!isset($this->hostSequenceCheckers[$hostname])) {
			$this->hostSequenceCheckers[$hostname] = new Billrun_Common_FileSequenceChecker(array($this, 'getFileSequenceData'), $hostname, $this->getName());
			$lastFile = $this->loadLastFileDataFromHost($hostname);
			if ($lastFile) {
				$this->hostSequenceCheckers[$hostname]->addFileToSequence($lastFile);
			}
		}
	}

	/**
	 * Check the  received files sequence.
	 * @param type $receiver
	 * @param type $filepaths
	 * @param type $hostname
	 * @return type
	 * @throws Exception
	 */
	protected function checkFilesSeq($filepaths, $hostname) {
		if (!isset($this->hostSequenceCheckers[$hostname])) {
			throw new Exception('Couldn`t find hostname in sequence checker might be a problem with the program flow.');
		}
		$mailMsg = FALSE;

		if ($filepaths) {
			foreach ($filepaths as $path) {
				$ret = $this->hostSequenceCheckers[$hostname]->addFileToSequence(basename($path));
				if ($ret) {
					$mailMsg .= $ret . "\n";
				}
			}
			$ret = $this->hostSequenceCheckers[$hostname]->hasSequenceMissing();
			if ($ret) {
				$mailMsg .= $this->getName() . " Reciever : Received a file out of sequence from host : $hostname - for the following files : \n";
				foreach ($ret as $file) {
					$mailMsg .= $file . "\n";
				}
			}
		} else if ($this->hostSequenceCheckers[$hostname]->lastLogFile) {
			$timediff = time() - strtotime($this->hostSequenceCheckers[$hostname]->lastLogFile['received_time']);
			if ($timediff > Billrun_Factory::config()->getConfigValue($this->getName() . '.receiver.max_missing_file_wait', 3600)) {
				$mailMsg = 'Didn`t received any new ' . $this->getName() . ' files form host ' . $hostname . ' for more then ' . $timediff . ' Seconds';
			}
		}
		//If there were any errors log them as high issues 
		if ($mailMsg) {
			Billrun_Factory::log()->log($mailMsg, Zend_Log::WARN);
		}
	}

	/**
	 * An helper function for the Billrun_Common_FileSequenceChecker  ( helper :) ) class.
	 * Retrive the ggsn file date and sequence number
	 * @param type $filename the full file name.
	 * @return boolea|Array false if the file couldn't be parsed or an array containing the file sequence data
	 * 						[seq] => the file sequence number.
	 * 						[date] => the file date.  
	 */
	public function getFileSequenceData($filename) {
		return array(
			'seq' => Billrun_Util::regexFirstValue(Billrun_Factory::config()->getConfigValue($this->getName() . ".sequence_regex.seq", "/(\d+)/"), $filename),
			'zone' => Billrun_Util::regexFirstValue(Billrun_Factory::config()->getConfigValue($this->getName() . ".sequence_regex.zone", "//"), $filename),
			'date' => Billrun_Util::regexFirstValue(Billrun_Factory::config()->getConfigValue($this->getName() . ".sequence_regex.date", "/(20\d{6})/"), $filename),
			'time' => Billrun_Util::regexFirstValue(Billrun_Factory::config()->getConfigValue($this->getName() . ".sequence_regex.time", "/\D(\d{4,6})\D/"), $filename),
		);
	}

	/**
	 * load the last sequence number for the files of the current type from the data base.
	 */
	protected function loadLastFileDataFromHost($hostname) {
		$log = Billrun_Factory::db()->logCollection();
		$lastLogFile = $log->query()->equals('source', $this->getName())->exists('received_time')
				->equals('retrieved_from', $hostname)->
				cursor()->setReadPreference('RP_PRIMARY')->sort(array('_id' => -1))->limit(1)->current();

		return isset($lastLogFile['file_name']) ? $lastLogFile['file_name'] : false;
	}

	/**
	 * Retrive the type of the current object to match to the configuration type.
	 */
	abstract function getName();
}

?>

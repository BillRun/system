<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * This is an Helper class to allow sequence checking for plugins 
 * usage :
 *  provide $getFileSequenceDataCallable callable function that will return an array (array('seq'=> <number> , date => <date>))
 *  to the contructor.
 *  then call verifyFileSequence with the file name for each file you want to check.
 *
 * @author eran
 */
class Billrun_Common_FileSequenceChecker {

	public $lastLogFile = false;
	protected $getFileSequenceDataCallable = false;
	protected $hostname = '';
	protected $type = false;
	protected $sequencers = array();

	public function __construct($getFileSequenceDataFunc, $host, $type) {
		$this->getFileSequenceDataCallable = $getFileSequenceDataFunc;
		$this->hostname = $host;
		$this->type = $type;
		$this->loadLastFileDataFromHost();
	}

	/**
	 * Check that the received files are in the proper order.
	 * @param $filename the recieve filename.
	 */
	public function addFileToSequence($filename) {
		$msg = FALSE;
		if (!$this->getFileSequenceDataCallable) {
			throw new Exception('getFileSequenceData Function wasn`t set on construction!');
		}

		if (!($sequenceData = call_user_func($this->getFileSequenceDataCallable, $filename))) {
			$msg = "GGSN Reciever : Couldnt parse received file : $filename !!!!";
			Billrun_Factory::log()->log($msg, Zend_Log::ALERT);
			return $msg;
		}

		if (!isset($this->sequencers[$sequenceData['date']])) {
			$this->sequencers[$sequenceData['date']] = new Billrun_Common_SequenceChecker();
		}
		$this->sequencers[$sequenceData['date']]->addSequence($sequenceData['seq'], $filename);

		return $msg;
	}

	/**
	 * Check if the files that were added has a  missing or out if syn sequence.
	 * @return bool|array false if the sequence is ok or anarry containg the files that were checked.
	 */
	public function hasSequenceMissing() {
		$ret = false;

		foreach ($this->sequencers as $sequencer) {
			if (!$sequencer->isSequenceValid()) {
				$ret = array_values($sequencer->sequence);
			}
		}

		return $ret;
	}

	/**
	 * load the last sequence number for the files of the current type from the data base.
	 */
	protected function loadLastFileDataFromHost() {
		$log = Billrun_Factory::db()->logCollection();
		$lastLogFile = $log->query()->equals('source', $this->type)->exists('received_time')
				->equals('retrieved_from', $this->hostname)->
				cursor()->sort(array('received_time' => -1, 'file_name' => -1))->limit(1)->rewind()->current();
		if (isset($lastLogFile['file_name'])) {
			$this->lastLogFile = $lastLogFile;
			$lastSequenceData = call_user_func($this->getFileSequenceDataCallable, $lastLogFile->get('file_name'));
			$this->sequencers[$lastSequenceData['date']] = new Billrun_Common_SequenceChecker();
			$this->sequencers[$lastSequenceData['date']]->addSequence($lastSequenceData['seq'], $lastLogFile['file_name']);
		}
	}

}

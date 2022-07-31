<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is an Helper class to allow sequence checking for plugins 
 * usage :
 *  provide $getFileSequenceDataCallable callable function that will return an array (array('seq'=> <number> , date => <date>))
 *  to the contructor.
 *  then call verifyFileSequence with the file name for each file you want to check.
 *
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
			Billrun_Factory::log($msg, Zend_Log::ALERT);
			return $msg;
		}

		if (!isset($this->sequencers[$sequenceData['date'] . $sequenceData['zone']])) {
			$this->sequencers[$sequenceData['date'] . $sequenceData['zone']] = new Billrun_Common_SequenceChecker();
		}
		$this->sequencers[$sequenceData['date'] . $sequenceData['zone']]->addSequence($sequenceData['seq'], $filename);

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

}

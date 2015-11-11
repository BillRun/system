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
	 * the Zend log levelto use for  files that are out of sequence.
	 * @var int 
	 */
	protected $outOfSequenceAlertLevel =  Zend_Log::ALERT;

	/**
	 * Initilaize  sequence checker  for a given host if none exists.
	 * @param type $hostname string a uniqe name of the host
	 */
	protected function setFilesSequenceCheckForHost($hostname) {
		if(!isset($this->hostSequenceCheckers[$hostname])) {
			$granularity = Billrun_Factory::config()->getConfigValue($this->getName().'.receiver.sequencer_date_granularity',8);
			$this->hostSequenceCheckers[$hostname] = new Billrun_Common_FileSequenceChecker(array($this,'getFileSequenceData'), $hostname, $this->getName(), $granularity );			
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
	protected function checkFilesSeq( $filepaths , $hostname ) {		
		if(!isset($this->hostSequenceCheckers[$hostname])) { 
			throw new Exception('Couldn`t find hostname in sequence checker might be a problem with the program flow.');
		}
		$mailMsg = FALSE;
		
		$lastFiles = $this->loadLastFilesForHost($hostname);
		foreach($lastFiles as $name) {
			$ret = $this->hostSequenceCheckers[$hostname]->addFileToSequence($name);
			if($ret) {
				$mailMsg .= $ret . "\n";
			}
		}
		$ret = $this->hostSequenceCheckers[$hostname]->hasSequenceMissing();
		if($ret) {
				$mailMsg .=  $this->getName()." Reciever : Received a file out of sequence from host : $hostname - for the following files : \n";
				foreach($ret as $file) {
					$mailMsg .= $file . "\n";
				}
		} 
		
		//If there were any errors log them
		if($mailMsg) {
			Billrun_Factory::log()->log($mailMsg, $this->outOfSequenceAlertLevel );
		} else {
			//marked the last  sequenced files
			foreach($this->hostSequenceCheckers[$hostname]->getSequences() as  $sequence) {
				$log = Billrun_Factory::db()->logCollection();				
				$lastSeqeueced = $log->query(
									array(	'source'=> $this->getName(), 
											'file_name' => $sequence->getLast(),
											'retrieved_from'=> $hostname, 
											'last_sequenced' => array('$ne' => true))
									)										
								->cursor()->current();
				if(count($lastSeqeueced->getRawData())) {
					$lastSeqeueced['last_sequenced']  = true;
					$lastSeqeueced->save( Billrun_Factory::db()->logCollection() );
				}
			}
		}
	}

	/**
	 * An helper function for the Billrun_Common_FileSequenceChecker  ( helper :) ) class.
	 * Retrive the ggsn file date and sequence number
	 * @param type $filename the full file name.
	 * @return boolea|Array false if the file couldn't be parsed or an array containing the file sequence data
	 *						[seq] => the file sequence number.
	 *						[date] => the file date.  
	 */
	public function getFileSequenceData($filename) {
		return Billrun_Traits_FileActions::getFilenameData($this->getName(), $filename);	
	}

	
	/**
	 * load the last  files of the current type from the data base that were  received from a given host.
	 */
	protected function loadLastFilesForHost($hostname) {
		$log = Billrun_Factory::db()->logCollection();
		$unsequencedWindow =  time()- strtotime('-'. Billrun_Factory::config()->getConfigValue($this->getName().'.receiver.unsequenced_time_window','30 min'));
		$sequencedWindowMulti =   Billrun_Factory::config()->getConfigValue($this->getName().'.receiver.sequenced_time_window_multi',2);
		$sequencedPadding =   Billrun_Factory::config()->getConfigValue($this->getName().'.receiver.sequence_padding',100);
		$lastSeqeueced = $log->query(
									array(	
											'source'=> $this->getName(), 
											'last_sequenced' => array('$exists'=> TRUE),											
											'retrieved_from'=> $hostname, 
											'received_time' => array( '$gte' => date(Billrun_Base::base_dateformat,time() - ($unsequencedWindow * $sequencedWindowMulti * 2) ) ),
										)
									)										
								->cursor()->sort(array('received_time' => -1))
								->limit(1)->current();		
		$lastLogFiles = $log->query(
									array(	'source'=> $this->getName(), 
											'received_time' => array(	'$lt' => date(Billrun_Base::base_dateformat,time() - $unsequencedWindow),
																		'$gte' => isset($lastSeqeueced['received_time']) ? $lastSeqeueced['received_time'] : date(Billrun_Base::base_dateformat,time() - ($unsequencedWindow * $sequencedWindowMulti) ) ),
											'retrieved_from'=> $hostname, )
									)										
								->cursor()->sort(array('extra_data.seq'=> -1,'file_name'=> -1))->skip($sequencedPadding);
		
		$retArr = array();
		foreach($lastLogFiles as $file) {
			$retArr[] = $file['file_name'];
		}		
		return $retArr;
	}

	/**
	 * Retrive the type of the current object to match to the configuration type.
	 */
	abstract function getName();
}

?>

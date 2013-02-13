<?php


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
	
	protected $lastSequenceData = false;
	protected $getFileSequenceDataCallable = false;
	protected $hostname = '';
	
	public function __construct( $getFileSequenceDataFunc, $host) {
		$this->getFileSequenceDataCallable = $getFileSequenceDataFunc;
		$this->hostname = $host;
		
		$this->loadLastFileDataFromHost();
	}
	
	/**
	 * Check that the received files are in the proper order.
	 * @param $filename the recieve filename.
	 */
	public function verifyFileSequence($filename) {
		$msg = FALSE;
		if(!$this->getFileSequenceDataCallable) {
			throw new Exception('getFileSequenceData Function wasn`t set on construction!');
		}
		if(!($sequenceData = call_user_func($this->getFileSequenceDataCallable, $filename))) {
			$msg = "GGSN Reciever : Couldnt parse received file : $filename !!!!, last sequence was". ($this->lastSequenceData ? " : ".$this->lastSequenceData['seq'] : "n't set");
			Billrun_Factory::log()->log($msg,  Zend_Log::ALERT);			
			return $msg;
		}	
		if($this->lastSequenceData) {
			
			if( $this->lastSequenceData['date']  == $sequenceData['date'] && $this->lastSequenceData['seq'] + 1 != $sequenceData['seq'] || 
				 $this->lastSequenceData['date']  != $sequenceData['date'] && $sequenceData['seq'] != 0 ) {
				$msg = "GGSN Reciever : Received a file out of sequence from host : {$this->hostname} - for file $filename , last sequence was : {$this->lastSequenceData['seq']}, current sequence is : {$sequenceData['seq']} ";
				//TODO use a common mail agent.
				Billrun_Factory::log()->log($msg,  Zend_Log::ALERT);
			}
		}
		$this->lastSequenceData =  $sequenceData;
		return $msg;
	}
	
	protected function loadLastFileDataFromHost() {
		$db = Billrun_Factory::db();
		$log = $db->getCollection($db::log_table);
		$lastLogFile = $log->query()->equals('source','ggsn')->exists('received_time')
									->equals('retrieved_from',$this->hostname)->
									cursor()->sort(array('received_time' => -1))->limit(1)->rewind()->current();
		if( isset($lastLogFile['file_name']) ) {
			$this->lastLogFile = $lastLogFile;
			$this->lastSequenceData = call_user_func($this->getFileSequenceDataCallable, $lastLogFile->get('file_name'));
			//Billrun_Factory::log()->log(print_r($this->lastSequenceData,1),  Zend_Log::DEBUG);
		}
	}

}

?>

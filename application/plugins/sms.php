<?php

/**
 * Temporary plugin  to handle smsc/smpp/mmsc retrival should be changed to specific CDR  handling baviour
 *
 * @author eran
 */
class smsPlugin extends Billrun_Plugin_BillrunPluginBase {

	use Billrun_Traits_FileSequenceChecking;
	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'sms';
	
	
	/**
	 * Setup the sequence checker.
	 * @param type $receiver
	 * @param type $hostname
	 * @return type
	 */
	public function beforeFTPReceive($receiver, $hostname) {
		if($receiver->getType() != 'smsc' && $receiver->getType() != "smpp" && $receiver->getType() != "mmsc" ) { return; }
		
		$this->setFilesSequenceCheckForHost($hostname);
	}
	
	/**
	 * Check the  received files sequence.
	 * @param type $receiver
	 * @param type $filepaths
	 * @param type $hostname
	 * @return type
	 * @throws Exception
	 */
	public function afterFTPReceived($receiver, $filepaths, $hostname, $hostConfig) {
		if($receiver->getType() != 'smsc' && $receiver->getType() != "smpp" && $receiver->getType() != "mmsc" ) { return; }
		
		$this->checkFilesSeq($filepaths, $hostname);
		
		$path = Billrun_Factory::config()->getConfigValue($receiver->getType().'.thirdparty.backup_path', false, 'string');
		if (!$path)	return;
		if ($hostname) {
			$path = $path . DIRECTORY_SEPARATOR . $hostname;
		}
		
		foreach ($filepaths as $filePath) {
			if (!$receiver->backupToPath($filePath, $path, true , true)) {
				Billrun_Factory::log()->log("Couldn't save file $filePath to third patry path at : $path", Zend_Log::ERR);
			}
		}
	}
	
	public function beforeProcessorStore($processor){
		$data = &$processor->getData();
		foreach ($data['data'] as $line) {
			if (isset($line['type']) && $line['type'] == 'mmsc' && preg_match('/^\+\d+\/TYPE\s*=\s*.*golantelecom/', $line['mm_source_addr']) && !preg_match('/^\+\d+/', $line['recipent_addr'])){
				$processor->unsetQueueRow($line['stamp']);
			}
		}
	}

}

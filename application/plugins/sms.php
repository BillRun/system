<?php

/**
 * Temporary plugin  to handle smsc/smpp/mmsc retrival should be changed to specific CDR  handling baviour
 *
 * @author eran
 */
class smsPlugin extends Billrun_Plugin_BillrunPluginBase {
	
	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'sms';
	/**
	 * ((TODO) changed to be done on the processing logic  (afterProcessorStore) )
	 * An HACK Copy  smsc and smpp to a thrid party directory.
	 * @param type $receiver the receiver instance
	 * @param type $filepaths the received file paths.
	 * @param type $hostname the  "hostname" the file  were recevied from
	 *
	public function afterFTPReceived($receiver,  $filepaths , $hostname) {
		if($receiver->getType() != 'smsc' && $receiver->getType() != "smpp" && $receiver->getType() != "mmsc" ) { return; } 
		$path = Billrun_Factory::config()->getConfigValue($receiver->getType().'.thirdparty.backup_path',false,'string');
		
		if(!$path) return;
		if( $hostname ) {
			$path = $path . DIRECTORY_SEPARATOR . $hostname;
		}
		Billrun_Factory::log()->log("Making directory : $path" , Zend_Log::DEBUG);
		if(!file_exists($path)) {
			if(mkdir($path, 0777, true)) {
				Billrun_Factory::log()->log("Failed when trying to create directory : $path" , Zend_Log::ERR);
			}
		}
		Billrun_Factory::log()->log(" saving retrieved files to third party at : $path" , Zend_Log::DEBUG);
		foreach($filepaths as $srcPath) {
			$targetPath = $path .DIRECTORY_SEPARATOR. basename($srcPath);
			if(!copy($srcPath, $targetPath)) {
				Billrun_Factory::log()->log(" Failed when trying to save file : ".  basename($srcPath)." to third party path : $path" , Zend_Log::ERR);
			} else {
				if (Billrun_Factory::config()->getConfigValue($receiver->getType().'.reciever.preserve_timestamps',false)) {
					$timestamp = filemtime($srcPath);
					Billrun_Util::setFileModificationTime($targetPath, $timestamp);
				}
			}
		}
	}*/
	
	/**
	 * back up retrived files that were processed to a third patry path. (NSOFT)
	 * @param \Billrun_Processor $processor the processor instace contain the current processed file data. 
	 */
	public function afterProcessorStore(\Billrun_Processor $processor) {
		$type = $processor->getType() ;
		if($type != 'smsc' && $type != "smpp" && $type != "mmsc" ) { return; } 

		$path = Billrun_Factory::config()->getConfigValue($type . '.thirdparty.backup_path', false, 'string');
		Billrun_Factory::log($path);
		if (!$path) {
			return;
		}
		
		if ($processor->retrievedHostname) {
			$path = $path . DIRECTORY_SEPARATOR . $processor->retrievedHostname;
		}
		
		Billrun_Factory::log()->log("Saving  file to third party at : $path", Zend_Log::DEBUG);
		if (!$processor->backupToPath($path, true)) {
			Billrun_Factory::log()->log("Couldn't  save file to third patry path at : $path", Zend_Log::ERR);
		}
	}
}

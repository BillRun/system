<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of smsc
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
	
	public function afterFTPReceived($receiver,  $filepaths , $hostname) {
		if($receiver->getType() != 'smsc' && $receiver->getType() != "smpp" ) { return; } 
		$path = Billrun_Factory::config()->getConfigValue($receiver->getType().'.thirdparty.backup_path',false,'string');
		
		if(!$path) return;
		if( $hostname ) {
			$path = $path . DIRECTORY_SEPARATOR . $hostname;
		}
		Billrun_Factory::log()->log("Making directory : $path" , Zend_Log::ERR);
		if(!file_exists($path)) {
			if(mkdir($path, 0777, true)) {
				Billrun_Factory::log()->log("Failed when trying to create directory : $path" , Zend_Log::ERR);
			}
		}
		Billrun_Factory::log()->log(" saving retrived files to third party at : $path" , Zend_Log::DEBUG);
		foreach($filepaths as $srcPath) {
			if(!copy($srcPath, $path .DIRECTORY_SEPARATOR. basename($srcPath))) {
				Billrun_Factory::log()->log(" Failed when trying to save file : ".  basename($srcPath)." to : $path" , Zend_Log::ERR);
			}
		}
	}
}

?>

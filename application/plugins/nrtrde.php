<?php

class nrtrdePlugin extends Billrun_Plugin_BillrunPluginBase {

	public function beforeFTPReceive($ftp) {
		return true;
	}

	/**
	 * method to extend the behaviour after file received with FTP receiver
	 * the method will extract the NRTRDE zip file to the same location
	 * 
	 * @param type $local_path
	 * @param Zend_Ftp_File $file ftp object
	 * @param Billrun_Receiver_Ftp $ftp ftp receiver
	 */
	public function afterFTPFileReceived($local_path, $file, $ftp) {
		//Create filter object
//		$filter = new Zend_Filter_Decompress(
//			array(
//				'adapter' => 'Zend_Filter_Compress_Zip', //Or 'Tar', or 'Gz'
//				'options' => array(
//					'target' => dirname($local_path),
//				)
//			));
//
//		$filter->filter($local_path);
//		
		return true;
	}
	
	protected function decompress($local_path) {
		//Create filter object
		$filter = new Zend_Filter_Decompress(
			array(
				'adapter' => 'Zend_Filter_Compress_Zip', //Or 'Zend_Filter_Compress_Tar', or 'Zend_Filter_Compress_Gz'
				'options' => array(
					'target' => dirname($local_path),
				)
			));

		$filter->filter($local_path);
		
		return true;

	}

	public function afterFTPReceived($ftp, $files) {
		return true;
	}
	
	/**
	 *  method to extend and add data to the log of the receiver
	 * 
	 * @param array $log_data the data to log
	 * @param Billrun_Receiver $receiver Billrun receiver object
	 * 
	 * @return true success
	 */
	public function beforeLogReceiveFile($log_data, $receiver) {
//		$log_data['source'] = 'nrtrde';
//		
//		return TRUE;
	}
	
	public function processorBeforeFileLoad($file_path) {
		$this->decompress($file_path);
		$file_path = str_replace('.zip', '', $file_path);
		return true;
	}
	
	public function beforeDataParsing($line, $processor) {
	}

}
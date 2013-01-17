<?php

class nrtrdePlugin extends Billrun_Plugin_BillrunPluginBase {

	public function beforeFTPReceive($ftp) {
		print "beforeFTPReceive" . PHP_EOL;
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
		$filter = new Zend_Filter_Decompress(
			array(
				'adapter' => 'Zend_Filter_Compress_Zip', //Or 'Tar', or 'Gz'
				'options' => array(
					'target' => dirname($local_path),
				)
			));

		$filter->filter($local_path);
	}

	public function afterFTPReceived($ftp, $files) {
		print "afterFTPReceived" . PHP_EOL;
	}

}
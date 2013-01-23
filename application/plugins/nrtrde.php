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

	/**
	 * method to unzip the processing file of NRTRDE (received as zip archive)
	 * 
	 * @param string $file_path the path of the file
	 * @param Billrun_Processor $processor instance of the processor who dispatch this event
	 * 
	 * @return boolean
	 */
	public function processorBeforeFileLoad($file_path, $processor) {
		if ($processor instanceof Billrun_Processor_Nrtrde) {
			$this->decompress($file_path);
			$file_path = str_replace('.zip', '', $file_path);
			return true;
		}
		return false;
	}

	public function beforeDataParsing($line, $processor) {
		
	}

    protected function get_last_charge_time($return_timestamp = false) {
		// TODO take the 25 from config
		$dayofmonth = 25;
		$format = "Ym" . $dayofmonth . "000000";
        if (date("d") >= $dayofmonth) {
            $time = date($format);
        } else {
            $time = date($format, strtotime('-1 month'));
        }
        if ($return_timestamp) {
            return strtotime($time);
        }
        return $time;
    }

	/**
	 * method to collect data which need to be handle by event
	 */
	public function handlerCollect() {
		$db = Billrun_Factory::db();
		$lines = $db->getCollection($db::lines_table);
		$charge_time = $this->get_last_charge_time();

		$where = array(
			'$match' => array(
				'source' => 'nrtrde',
				'record_type' => 'MOC',
				'connectedNumber' => array('$regex' => '^972'),
				'callEventStartTimeStamp' => array('$gte' => $charge_time),
				'deposit_stamp' => array('$exists' => false),
			),
		);

		$group = array(
			'$group' => array(
				"_id" => '$imsi',
				"total" => array('$sum' => '$callEventDuration'),
			),
		);

//		$project = array(
//			'$project' => array(
//				'imsi' => 1,
//				'total' => 'function() {return 1}'
//			),
//		);
		
		$having = array(
			'$match' => array(
				'total' => array('$gte' => 0)
			),
		);

		$moc_israel = $lines->aggregate($where, $group, $having);

		$where['$match']['connectedNumber']['$regex'] = '^(?!972)';
		$having['$match']['total']['$gte'] = 0;
		$moc_nonisrael = $lines->aggregate($where, $group, $having);
		
		$where['$match']['record_type'] = 'MTC';
		unset($where['$match']['connectedNumber']);
		$having['$match']['total']['$gte'] = 200;
		
		$mtc = $lines->aggregate($where, $group, $having);
		
		$where['$match']['record_type'] = 'MOC';
		$where['$match']['callEventDuration'] = 0;
		$group['$group']['total']['$sum'] = 1;
		$having['$match']['total']['$gte'] = 2;

		$sms_out = $lines->aggregate($where, $group, $having);
		
		// unite all the results per imsi
		die;
	}

}
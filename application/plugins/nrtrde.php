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
		if ($processor instanceof Billrun_Processor_Nrtrde && file_exists($file_path)) {
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
				'callEventDuration' => array('$gt' => 0),
			),
		);

		$group = array(
			'$group' => array(
				"_id" => '$imsi',
				"moc_israel" => array('$sum' => '$callEventDuration'),
			),
		);

		$project = array(
			'$project' => array(
				'imsi' => '$_id',
				'_id' => 0, 
				'moc_israel' => 1,
			),
		);
		
		$having = array(
			'$match' => array(
				'moc_israel' => array('$gte' => 10)
			),
		);

		$ret = array();
			
		$moc_israel = $lines->aggregate($where, $group, $project, $having);
		
		$this->normalize(&$ret, $moc_israel, 'moc_israel');

		$where['$match']['connectedNumber']['$regex'] = '^(?!972)';
		$group['$group']['moc_nonisrael'] = $group['$group']['moc_israel'];
		unset($group['$group']['moc_israel']);
		unset($having['$match']['moc_israel']);
		$having['$match']['moc_nonisrael'] = array('$gte' => 0);
		$project['$project']['moc_nonisrael'] = 1;
		unset($project['$project']['moc_israel']);
		$moc_nonisrael = $lines->aggregate($where, $group, $project, $having);
		$this->normalize(&$ret, $moc_nonisrael, 'moc_nonisrael');

		$where['$match']['record_type'] = 'MTC';
		unset($where['$match']['connectedNumber']);
		$group['$group']['mtc_all'] = $group['$group']['moc_nonisrael'];
		unset($group['$group']['moc_nonisrael']);
		unset($having['$match']['moc_nonisrael']);
		$having['$match']['mtc_all'] = array('$gte' => 100);
		$project['$project']['mtc_all'] = 1;
		unset($project['$project']['moc_nonisrael']);
		$mtc = $lines->aggregate($where, $group, $project, $having);
		$this->normalize(&$ret, $mtc, 'mtc_all');
		
		$where['$match']['record_type'] = 'MOC';
		$where['$match']['callEventDuration'] = 0;
		$group['$group']['sms_out'] = $group['$group']['mtc_all'];
		unset($group['$group']['mtc_all']);
		unset($having['$match']['mtc_all']);
		$group['$group']['sms_out'] = array('$sum' => 1);
		$having['$match']['sms_out'] = array('$gte' => 3);
		$project['$project']['sms_out'] = 1;
		unset($project['$project']['mtc_all']);
		$sms_out = $lines->aggregate($where, $group, $project, $having);
		$this->normalize(&$ret, $sms_out, 'sms_out');

		print_R($ret);

		// unite all the results per imsi
		die;
	}
	
	protected function normalize($ret, $items, $field) {
		if (!is_array($items) || !count($items)) {
			return false;
		}
		
		foreach ($items as $item) {
			$ret[$item['imsi']][$field] = $item[$field];
		}
		
		return true;
	}
	
}
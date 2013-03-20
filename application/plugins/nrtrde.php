<?php

class nrtrdePlugin extends Billrun_Plugin_BillrunPluginFraud {

		
	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'nrtrde';
	
	const time_format = 'YmdHis';
	
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
	public function processorBeforeFileLoad(&$file_path, $processor) {
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
		$dayofmonth = Billrun_Factory::config()->getConfigValue('billrun.charging_day', 25, 'int');
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

		$lines = Billrun_Factory::db()->linesCollection();
		$charge_time = $this->get_last_charge_time();

		$where = array(
			'$match' => array(
				'source' => 'nrtrde',
				'record_type' => 'MOC',
				'connectedNumber' => array('$regex' => '^972'),
				'callEventStartTimeStamp' => array('$gte' => $charge_time),
				'deposit_stamp' => array('$exists' => false),
				'callEventDuration' => array('$gt' => 0), // not sms
			),
		);

		$group = array(
			'$group' => array(
				"_id" => '$imsi',
				"moc_israel" => array('$sum' => '$callEventDuration'),
				'lines_stamps' => array('$addToSet' => '$stamp'),
			),
		);

		$project = array(
			'$project' => array(
				'imsi' => '$_id',
				'_id' => 0, 
				'moc_israel' => 1,
				'lines_stamps' => 1,
			),
		);
		
		$having = array(
			'$match' => array(
				'moc_israel' => array('$gte' => Billrun_Factory::config()->getConfigValue('nrtde.thresholds.moc.israel',1800, 'int'))
			),
		);

		$ret = array();
			
		$moc_israel = $lines->aggregate($where, $group, $project, $having);
		
		$this->normalize($ret, $moc_israel, 'moc_israel');

		$where['$match']['connectedNumber']['$regex'] = '^(?!972)';
		$group['$group']['moc_nonisrael'] = $group['$group']['moc_israel'];
		unset($group['$group']['moc_israel']);
		unset($having['$match']['moc_israel']);
		$having['$match']['moc_nonisrael'] = array('$gte' => Billrun_Factory::config()->getConfigValue('nrtde.thresholds.moc.nonisrael', 600, 'int'));
		$project['$project']['moc_nonisrael'] = 1;
		unset($project['$project']['moc_israel']);
		$moc_nonisrael = $lines->aggregate($where, $group, $project, $having);
		$this->normalize($ret, $moc_nonisrael, 'moc_nonisrael');

		$where['$match']['record_type'] = 'MTC';
		unset($where['$match']['connectedNumber']);
		$group['$group']['mtc_all'] = $group['$group']['moc_nonisrael'];
		unset($group['$group']['moc_nonisrael']);
		unset($having['$match']['moc_nonisrael']);
		$having['$match']['mtc_all'] = array('$gte' => Billrun_Factory::config()->getConfigValue('nrtde.thresholds.mtc', 2400, 'int'));
		$project['$project']['mtc_all'] = 1;
		unset($project['$project']['moc_nonisrael']);
		$mtc = $lines->aggregate($where, $group, $project, $having);
		$this->normalize($ret, $mtc, 'mtc_all');

		$where['$match']['record_type'] = 'MOC';
		$where['$match']['callEventDuration'] = 0;
		$group['$group']['sms_out'] = $group['$group']['mtc_all'];
		unset($group['$group']['mtc_all']);
		unset($having['$match']['mtc_all']);
		$group['$group']['sms_out'] = array('$sum' => 1);
		$having['$match']['sms_out'] = array('$gte' => Billrun_Factory::config()->getConfigValue('nrtde.thresholds.smsout', 70, 'int'));
		$project['$project']['sms_out'] = 1;
		unset($project['$project']['mtc_all']);
		$sms_out = $lines->aggregate($where, $group, $project, $having);
		$this->normalize($ret, $sms_out, 'sms_out');
		
		unset($group['$group']['sms_out']);
		unset($having['$match']['sms_out']);
		unset($project['$project']['sms_out']);
		$timeWindow= strtotime("-" . Billrun_Factory::config()->getConfigValue('nnrtrde.hourly.timespan','1h'));
		$where['$match']['callEventStartTimeStamp']['$gt'] = date(self::time_format, $timeWindow);
		$group['$group']['sms_hourly'] = array('$sum' => 1);
		$having['$match']['sms_hourly'] = array('$gte' => Billrun_Factory::config()->getConfigValue('nrtde.hourly.thresholds.smsout', 250, 'int'));
		$project['$project']['sms_hourly'] = 1;
		$sms_hourly = $lines->aggregate($where, $group, $project, $having);
		$this->normalize($ret, $sms_hourly, 'sms_hourly');
		
		unset($group['$group']['sms_hourly']);
		unset($having['$match']['sms_hourly']);
		unset($project['$project']['sms_hourly']);
		$where['$match']['callEventStartTimeStamp']['$gt'] = date(self::time_format,$timeWindow);
		$where['$match']['callEventDuration'] = array('$gt' => 0);
		$group['$group']['moc_nonisrael_hourly'] = array('$sum' => '$callEventDuration');
		$having['$match']['moc_nonisrael_hourly'] = array('$gte' => Billrun_Factory::config()->getConfigValue('nrtde.hourly.thresholds.mocnonisrael', 3000));
		$project['$project']['moc_nonisrael_hourly'] = 1;
		$moc_nonisrael_hourly = $lines->aggregate($where, $group, $project, $having);
		$this->normalize($ret, $moc_nonisrael_hourly, 'moc_nonisrael_hourly');


		return $ret;
	}
	
	protected function normalize(&$ret, $items, $field) {
		if (!is_array($items) || !count($items)) {
			return false;
		}
		
		foreach ($items as $item) {
			$imsi = $item['imsi'];
			if (!isset($ret[$imsi])) {
				$ret[$imsi] = array();
			}
			
			$ret[$imsi][$field] = $item[$field];
			
			if (isset($ret[$imsi]['lines_stamps'])) {
				$ret[$imsi]['lines_stamps'] = array_merge($ret[$imsi]['lines_stamps'], $item['lines_stamps']);
			} else {
				$ret[$item['imsi']]['lines_stamps'] = $item['lines_stamps'];
			}
			
		} 
		
		return true;
	}
	
	/**
	 * Add data that is needed to use the event object/DB document later
	 * 
	 * @param Array|Object $event the event to add fields to.
	 * @return Array|Object the event object with added fields
	 */
	protected function addAlertData(&$event) {
		$type = isset($event['moc_israel']) ? 'moc_israel': 
					(isset($event['moc_nonisrael']) ? 'moc_nonisrael' : 
						(isset($event['mtc_all']) ? 'mtc_all' : 
						(isset($event['sms_hourly']) ? 'sms_hourly' : 
								'sms_out')));
		
		$event['units']	= 'MIN';
		$event['value']	= $event[$type];
		$event['event_type']	= 'NRTRDE_VOICE';
		
		switch($type) {
			case 'moc_israel':
					$event['threshold']	= $this->getConfigValue('nrtde.thresholds.moc.israel', 1800, 'int');
				break;
			
			case 'moc_nonisrael':				
					$event['threshold']	= $this->getConfigValue('nrtde.thresholds.moc.nonisrael', 600, 'int');
				break;
			
			case 'mtc_all':
					$event['threshold']	= $this->getConfigValue('nrtde.thresholds.mtc', 2400, 'int');
				break;
			
			case 'sms_out':
					$event['threshold']	= $this->getConfigValue('nrtde.thresholds.smsout', 70, 'int');
					$event['units']	= 'SMS';
					$event['event_type']	= 'NRTRDE_SMS';
				break;
			case 'sms_hourly':
					$event['threshold']	= $this->getConfigValue('nrtde.hourly.thresholds.smsout', 250, 'int');
					$event['units']	= 'SMS';
					$event['event_type']	= 'NRTRDE_HOURLY_SMS';
				break;	
			
		}
		
		return $event;
	}
	
}

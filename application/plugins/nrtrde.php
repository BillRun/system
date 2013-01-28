<?php

class nrtrdePlugin extends Billrun_Plugin_BillrunPluginBase {

		
	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'nrtrde';
	
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
		$dayofmonth = $this->getConfigValue('billrun.charging_day',25);
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
	 * 
	 * @param type $items
	 * @param type $pluginName
	 */
	public function handlerAlert(&$items,$pluginName) {
			if($pluginName != $this->getName() || !$items ) {return;}
		//$this->log->log("Marking down Alert For {$item['imsi']}",Zend_Log::DEBUG);
		$ret = array();
		$db = Billrun_Factory::db();
		$lines = $db->getCollection($db::lines_table);
		foreach($items as $item) {
			$newEvent = new Mongodloid_Entity($item);
			
			$newEvent['source']	= $this->getName();
			unset($newEvent['lines_stamps']);
			$newEvent = $this->addAlertData($newEvent);
			$newEvent['stamp']	= md5(serialize($newEvent));
			$item['event_stamp']= $newEvent['stamp'];
			
			$ret[] = $events->save($newEvent);
		}
		return $ret;
	}

	/**
	 * 
	 * @param type $items
	 * @param type $pluginName
	 * @return array
	 */
	public function handlerMarkDown(&$items, $pluginName) {
		if($pluginName != $this->getName() || !$items ) {return;}
		//$this->log->log("Marking down Alert For {$item['imsi']}",Zend_Log::DEBUG);
		$ret = array();
		$db = Billrun_Factory::db();
		$lines = $db->getCollection($db::lines_table);
		foreach($items as &$item) { 
			$ret[] = $lines->update(	array('stamp'=> array('$in' => $item['lines_stamps'])),
								array('$set' => array('event_stamp' => $item['event_stamp'])),
								array('multiple'=>1));
		}
		return $ret;
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
				'lines_stamps' => array('$addToSet' => '$stamp'),
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
				'moc_israel' => array('$gte' => $this->getConfigValue('nrtde.thresholds.moc.israel',10))
			),
		);

		$ret = array();
			
		$moc_israel = $lines->aggregate($where, $group, $project, $having);
		
		$this->normalize($ret, $moc_israel, 'moc_israel');

		$where['$match']['connectedNumber']['$regex'] = '^(?!972)';
		$group['$group']['moc_nonisrael'] = $group['$group']['moc_israel'];
		unset($group['$group']['moc_israel']);
		unset($having['$match']['moc_israel']);
		$having['$match']['moc_nonisrael'] = array('$gte' => $this->getConfigValue('nrtde.thresholds.moc.nonisrael',0));
		$project['$project']['moc_nonisrael'] = 1;
		unset($project['$project']['moc_israel']);
		$moc_nonisrael = $lines->aggregate($where, $group, $project, $having);
		$this->normalize($ret, $moc_nonisrael, 'moc_nonisrael');

		$where['$match']['record_type'] = 'MTC';
		unset($where['$match']['connectedNumber']);
		$group['$group']['mtc_all'] = $group['$group']['moc_nonisrael'];
		unset($group['$group']['moc_nonisrael']);
		unset($having['$match']['moc_nonisrael']);
		$having['$match']['mtc_all'] = array('$gte' => $this->getConfigValue('nrtde.thresholds.mtc',100));
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
		$having['$match']['sms_out'] = array('$gte' => $this->getConfigValue('nrtde.thresholds.smsout',3));
		$project['$project']['sms_out'] = 1;
		unset($project['$project']['mtc_all']);
		$sms_out = $lines->aggregate($where, $group, $project, $having);
		$this->normalize($ret, $sms_out, 'sms_out');

		print_R($ret);

		// unite all the results per imsi
	//	die;
		return $ret;
	}
	
	protected function normalize(&$ret, $items, $field) {
		if (!is_array($items) || !count($items)) {
			return false;
		}
		
		foreach ($items as $item) {
			$ret[$item['imsi']][$field] = $item[$field];
		}
		
		return true;
	}
	
	/**
	 * Add data that is needed to use the event object/DB document later
	 * @param Array|Object $event the event to add fields to.
	 * @return Array|Object the event object with added fields
	 */
	protected function addAlertData($event) {
		$type = isset($newEvent['moc_israel']) ? 'moc_israel': 
					(isset($newEvent['moc_nonisrael']) ? 'moc_nonisrael' : 
						(isset($newEvent['mtc_all']) ? 'mtc_all' : 
								'sms_out'));
		
		$newEvent['units']	= 'MIN';
		$newEvent['value']	= $newEvent[$type];
		
		switch($type) {
			case 'moc_israel':
					$newEvent['threshold']	= $this->getConfigValue('nrtde.thresholds.moc.israel', 0);
				break;
			
			case 'moc_nonisrael':				
					$newEvent['threshold']	= $this->getConfigValue('nrtde.thresholds.moc.nonisrael', 100);
				break;
			case 'mtc_all':
					$newEvent['threshold']	= $this->getConfigValue('nrtde.thresholds.mtc', 0);
				break;
			
			case 'sms_out':
					$newEvent['threshold']	= $this->getConfigValue('nrtde.thresholds.smsout', 0);
					$newEvent['units']	= 'SMS';
				break;
		}
		
		return $newEvent;
	}
	
}
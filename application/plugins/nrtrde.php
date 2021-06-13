<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Fraud NRTRDE plugin
 *
 * @package  Application
 * @subpackage Plugins
 * @since    0.5
 */
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
	public function afterFTPFileReceived($local_path, $file, $ftp, $hostName, $extraData, $hostConfig) {
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

	public function afterFTPReceived($ftp, $files, $hostname, $hostConfig) {
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

	public function afterDataParsing(&$row, $parser) {
		if ($parser->getType() == 'nrtrde') {
			// make the duration rounded by minute
			if (isset($row['callEventDuration'])) {
				$callEventDuration = $row['callEventDuration'];
				$row['callEventDurationRound'] = ceil($callEventDuration / 60) * 60;
			}

			// add record opening time UTC aligned
			if (isset($row['callEventStartTimeStamp'])) {
				$row['urt'] = new MongoDate(Billrun_Util::dateTimeConvertShortToIso($row['callEventStartTimeStamp'], $row['utcTimeOffset']));
			}
		}
	}

	/**
	 * move zip files to backup path after the processing was done
	 * @param Billrun_Processor $processor the proce
	 * @param string $file_path the path of the current processing file.
	 */
	public function afterProcessorStore($processor, &$file_path) {
		if ($processor->getType() != $this->getName()) {
			return;
		}
		$path = Billrun_Factory::config()->getConfigValue($this->getName() . '.processor.zip_move_path', false, 'string');
		if (!$path)
			return;

		if ($processor->retrievedHostname) {
			$path = $path . DIRECTORY_SEPARATOR . $processor->retrievedHostname;
		}

		$path .= DIRECTORY_SEPARATOR . date("Ym");

		if (!file_exists($path)) {
			Billrun_Factory::log()->log("Creating Directory : $path", Zend_Log::DEBUG);
			mkdir($path, 0777, true);
		}

		$srcPath = $file_path . ".zip";
		if (file_exists($srcPath)) {
			Billrun_Factory::log()->log("Saving zip file to : $path", Zend_Log::DEBUG);
			if (!rename($srcPath, $path . DIRECTORY_SEPARATOR . basename($srcPath))) {
				Billrun_Factory::log()->log(" Failed when trying to save file : " . basename($srcPath) . " to third party path : $path", Zend_Log::ERR);
			}
		}
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

	/**
	 * method to collect data which need to be handle by event
	 */
	public function handlerCollect($options) {
		if ($options['type'] != 'roaming') {
			return FALSE;
		}
		$lines = Billrun_Factory::db()->linesCollection();
		$charge_time = Billrun_Util::getLastChargeTime(true); // true means return timestamp
		// TODO: take it to config ? how to handle variables ?
		$base_match = array(
			'$match' => array(
				'source' => 'nrtrde',
			)
		);
		$where = array(
			'$match' => array(
				'record_type' => 'MOC',
				'connectedNumber' => array('$regex' => '^972'),
				'urt' => array('$gte' => new MongoDate($charge_time)),
				'event_stamp' => array('$exists' => false),
				'deposit_stamp' => array('$exists' => false),
				'callEventDurationRound' => array('$gt' => 0), // not sms
			),
		);

		$group = array(
			'$group' => array(
				"_id" => '$imsi',
				"moc_israel" => array('$sum' => '$callEventDurationRound'),
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
				'moc_israel' => array('$gte' => Billrun_Factory::config()->getConfigValue('nrtrde.thresholds.moc.israel', 1800, 'int'))
			),
		);

		$ret = array();

		Billrun_Factory::log()->log("nrtrdePlugin::handlerCollect collecting moc_israel exceeders", Zend_Log::DEBUG);
		$moc_israel = $lines->aggregate($base_match, $where, $group, $project, $having);

		$this->normalize($ret, $moc_israel, 'moc_israel');

		$where['$match']['connectedNumber']['$regex'] = '^(?!972)';
		$group['$group']['moc_nonisrael'] = $group['$group']['moc_israel'];
		unset($group['$group']['moc_israel']);
		unset($having['$match']['moc_israel']);
		$having['$match']['moc_nonisrael'] = array('$gte' => Billrun_Factory::config()->getConfigValue('nrtrde.thresholds.moc.nonisrael', 600, 'int'));
		$project['$project']['moc_nonisrael'] = 1;
		unset($project['$project']['moc_israel']);
		Billrun_Factory::log()->log("nrtrdePlugin::handlerCollect collecting moc_nonisrael exceeders", Zend_Log::DEBUG);
		$moc_nonisrael = $lines->aggregate($base_match, $where, $group, $project, $having);
		$this->normalize($ret, $moc_nonisrael, 'moc_nonisrael');

		$where['$match']['record_type'] = 'MTC';
		unset($where['$match']['connectedNumber']);
		$group['$group']['mtc_all'] = $group['$group']['moc_nonisrael'];
		unset($group['$group']['moc_nonisrael']);
		unset($having['$match']['moc_nonisrael']);
		$having['$match']['mtc_all'] = array('$gte' => Billrun_Factory::config()->getConfigValue('nrtrde.thresholds.mtc', 2400, 'int'));
		$project['$project']['mtc_all'] = 1;
		unset($project['$project']['moc_nonisrael']);
		Billrun_Factory::log()->log("nrtrdePlugin::handlerCollect collecting mtc_all exceeders", Zend_Log::DEBUG);
		$mtc = $lines->aggregate($base_match, $where, $group, $project, $having);
		$this->normalize($ret, $mtc, 'mtc_all');

		$where['$match']['record_type'] = 'MOC';
		$where['$match']['callEventDurationRound'] = 0;
		$group['$group']['sms_out'] = $group['$group']['mtc_all'];
		unset($group['$group']['mtc_all']);
		unset($having['$match']['mtc_all']);
		$group['$group']['sms_out'] = array('$sum' => 1);
		$having['$match']['sms_out'] = array('$gte' => Billrun_Factory::config()->getConfigValue('nrtrde.thresholds.smsout', 70, 'int'));
		$project['$project']['sms_out'] = 1;
		unset($project['$project']['mtc_all']);
		Billrun_Factory::log()->log("nrtrdePlugin::handlerCollect collecting sms_out exceeders", Zend_Log::DEBUG);
		$sms_out = $lines->aggregate($base_match, $where, $group, $project, $having);
		$this->normalize($ret, $sms_out, 'sms_out');

		unset($group['$group']['sms_out']);
		unset($having['$match']['sms_out']);
		unset($project['$project']['sms_out']);
		$timeWindow = strtotime("-" . Billrun_Factory::config()->getConfigValue('nrtrde.hourly.timespan', '1h'));
		$where['$match']['urt'] = array('$gt' => new MongoDate($timeWindow));
		$group['$group']['sms_hourly'] = array('$sum' => 1);
		$having['$match']['sms_hourly'] = array('$gte' => Billrun_Factory::config()->getConfigValue('nrtrde.hourly.thresholds.smsout', 250, 'int'));
		$project['$project']['sms_hourly'] = 1;
		Billrun_Factory::log()->log("nrtrdePlugin::handlerCollect collecting sms_hourly exceeders", Zend_Log::DEBUG);
		$sms_hourly = $lines->aggregate($base_match, $where, $group, $project, $having);
		$this->normalize($ret, $sms_hourly, 'sms_hourly');

		unset($group['$group']['sms_hourly']);
		unset($having['$match']['sms_hourly']);
		unset($project['$project']['sms_hourly']);
		$where['$match']['urt'] = array('$gt' => new MongoDate($timeWindow));
		$where['$match']['callEventDurationRound'] = array('$gt' => 0);
		$group['$group']['moc_nonisrael_hourly'] = array('$sum' => '$callEventDurationRound');
		$having['$match']['moc_nonisrael_hourly'] = array('$gte' => Billrun_Factory::config()->getConfigValue('nrtrde.hourly.thresholds.mocnonisrael', 3000));
		$project['$project']['moc_nonisrael_hourly'] = 1;
		Billrun_Factory::log()->log("nrtrdePlugin::handlerCollect collecting moc_nonisrael_hourly exceeders", Zend_Log::DEBUG);
		$moc_nonisrael_hourly = $lines->aggregate($base_match, $where, $group, $project, $having);
		$this->normalize($ret, $moc_nonisrael_hourly, 'moc_nonisrael_hourly');

		Billrun_Factory::log()->log("NRTRDE plugin locate " . count($ret) . " items as fraud events", Zend_Log::INFO);

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
				$ret[$imsi]['imsi'] = $imsi;
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
	 * 
	 * @todo LOOSE COUPLING !!!
	 */
	protected function addAlertData(&$event) {
		// @todo: WTF?!?! Are you real with this condition???
		$type = isset($event['moc_israel']) ? 'moc_israel' :
			(isset($event['moc_nonisrael']) ? 'moc_nonisrael' :
				(isset($event['mtc_all']) ? 'mtc_all' :
					(isset($event['sms_hourly']) ? 'sms_hourly' :
						(isset($event['sms_out']) ? 'sms_out' :
							'moc_nonisrael_hourly'))));

		$event['units'] = 'SEC';
		$event['value'] = $event[$type];
		$event['event_type'] = 'NRTRDE_VOICE';

		switch ($type) {
			case 'moc_israel':
				$event['threshold'] = Billrun_Factory::config()->getConfigValue('nrtrde.thresholds.moc.israel', 1800, 'int');
				break;

			case 'moc_nonisrael':
				$event['threshold'] = Billrun_Factory::config()->getConfigValue('nrtrde.thresholds.moc.nonisrael', 600);
				break;

			case 'mtc_all':
				$event['threshold'] = Billrun_Factory::config()->getConfigValue('nrtrde.thresholds.mtc', 7200);
				break;

			case 'sms_out':
				$event['threshold'] = Billrun_Factory::config()->getConfigValue('nrtrde.thresholds.smsout', 70);
				$event['units'] = 'SMS';
				$event['event_type'] = 'NRTRDE_SMS';
				break;

			case 'sms_hourly':
				$event['threshold'] = Billrun_Factory::config()->getConfigValue('nrtrde.hourly.thresholds.smsout', 250);
				$event['units'] = 'SMS';
				$event['event_type'] = 'NRTRDE_HOURLY_SMS';
				break;

			case 'moc_nonisrael_hourly':
				$event['threshold'] = Billrun_Factory::config()->getConfigValue('nrtrde.hourly.thresholds.mocnonisrael', 3000);
				$event['event_type'] = 'NRTRDE_HOURLY_VOICE';
				break;
		}

		$event['effects'] = array(
			'key' => 'type',
			'filter' => array('$in' => array('nrtrde', 'ggsn'))
		);

		return $event;
	}

}

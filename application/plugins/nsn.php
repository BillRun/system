<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a plguin to provide NSN support to the billing system.
 */
class nsnPlugin extends Billrun_Plugin_BillrunPluginFraud implements Billrun_Plugin_Interface_IParser, Billrun_Plugin_Interface_IProcessor {

	use Billrun_Traits_FileSequenceChecking;

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'nsn';

	const HEADER_LENGTH = 41;
	const TRAILER_LENGTH = 24;
	const MAX_CHUNKLENGTH_LENGTH = 16384;
	const RECORD_ALIGNMENT = 0x1ff0; //8176

	protected $fileStats = null;

	/**
	 * regex to identify calls originating from ILDS
	 * @var String
	 */
	protected $ild_called_number_regex = null;

	public function __construct(array $options = array()) {
		$this->nsnConfig = (new Yaf_Config_Ini(Billrun_Factory::config()->getConfigValue('nsn.config_path')))->toArray();
		$this->ild_called_number_regex = Billrun_Factory::config()->getConfigValue('016_one_way.identifications.called_number_regex');
	}

	/////////////////////////////////////////////// Reciver //////////////////////////////////////

	/**
	 * Setup the sequence checker.
	 * @param type $receiver
	 * @param type $hostname
	 * @return type
	 */
	public function beforeFTPReceive($receiver, $hostname) {
		if ($receiver->getType() != $this->getName()) {
			return;
		}
		$this->setFilesSequenceCheckForHost($hostname);
	}

	/**
	 * (dispatcher hook) 
	 * Check recieved file sequences and back them to a 3rd party.
	 * @param type $receiver
	 * @param type $filepaths
	 * @param type $hostname
	 * @return type
	 */
	public function afterFTPReceived($receiver, $filepaths, $hostname, $hostConfig) {
		if ($receiver->getType() != $this->getName()) {
			return;
		}
		$this->checkFilesSeq($filepaths, $hostname);

		$path = Billrun_Factory::config()->getConfigValue($this->getName() . '.thirdparty.backup_path', false, 'string');
		if (!$path)
			return;
		if ($hostname) {
			$path = $path . DIRECTORY_SEPARATOR . $hostname;
		}
		foreach ($filepaths as $filePath) {
			if (!$receiver->backupToPath($filePath, $path, true, true)) {
				Billrun_Factory::log()->log("Couldn't save file $filePath to third patry path at : $path", Zend_Log::ERR);
			}
		}
	}

	/**
	 * (dispatcher hook)
	 * alter the file name to match the month the file was recevied to prevent duplicate files.
	 */
	public function beforeFTPFileReceived(&$file, $receiver, &$hostName, &$extraData) {
		if ($receiver->getType() != $this->getName() || !$file->isFile()) {
			return;
		}
		$extraData['month'] = date('Ym', $file->extraData['date']);
		$extraData['week'] = date('W', $file->extraData['date']);
	}

//	/**
//	 *  (dispatcher hook)
//	 *  @param $query the query to prform on the DB to detect is the file was received.
//	 *  @param $type the type of file to check
//	 *  @param $receiver the reciver instance.
//	 */
//	public function alertisFileReceivedQuery(&$query, $type, $receiver) {
//		if ($type != $this->getName()) {
//			return;
//		}
//		//check if the file was received more then an hour ago.
//		$query['extra_data.month'] = array('$gt' => date('Ym', strtotime('previous month')));
//	}

	/**
	 * @see Billrun_Plugin_BillrunPluginFraud::handlerCollect
	 */
	public function handlerCollect($options) {
		if ($options['type'] != 'roaming') {
			return FALSE;
		}
		$monthlyThreshold = floatval(Billrun_Factory::config()->getConfigValue('nsn.thresholds.monthly_voice', 36000));
		$dailyThreshold = floatval(Billrun_Factory::config()->getConfigValue('nsn.thresholds.daily_voice', 3600));

		Billrun_Factory::log()->log("nsnPlugin::handlerCollect collecting monthly  exceedres", Zend_Log::DEBUG);
		$monthlyAlerts = $this->detectDurationExcceders(date('Y0101000000'), $monthlyThreshold);
		foreach ($monthlyAlerts as &$val) {
			$val['threshold'] = $monthlyThreshold;
		};

		Billrun_Factory::log()->log("nsnPlugin::handlerCollect collecting hourly  exceedres", Zend_Log::DEBUG);
		$dailyAlerts = $this->detectDurationExcceders(date('Y01d000000'), $dailyThreshold);
		foreach ($dailyAlerts as &$val) {
			$val['threshold'] = $dailyThreshold;
		}

		return array_merge($monthlyAlerts, $dailyAlerts);
	}

	/**
	 * Detect calls that exceed a certain duration threshold
	 * @param type $fromDate the date that from which a call is a valid call to aggregate (formated : 'YmdHis') 
	 * @param type $threshold the duration threshold in second that above it is considered an excess
	 * @return array an array conatining all the duration excedding  call  aggregated by imsi/msisdn
	 */
	protected function detectDurationExcceders($fromDate, $threshold) {
		$aggregateQuery = array(
			array(
				'$match' => array(
					'type' => 'nsn',
				),
			),
			array(
				'$match' => array(
					'event_stamp' => array('$exists' => false),
					'record_type' => array('$in' => array('01', '11','30')),
					'called_number' => array('$regex' => '^(?=10[^1]|1016|016|97216)....'),
					'duration' => array('$gt' => 0),
					//@TODO  switch to unified time once you have the time to test it
					//'urt' => array('$gt' => $charge_time),
					'charging_start_time' => array('$gte' => $fromDate),
				),
			),
			array(
				'$group' => array(
					'_id' => array('imsi' => '$calling_imsi', 'msisdn' => '$calling_number'),
					'duration' => array('$sum' => '$duration'),
					'lines_stamps' => array('$addToSet' => '$stamp'),
				),
			),
			array(
				'$project' => array(
					'_id' => 0,
					'imsi' => '$_id.imsi',
					'msisdn' => '$_id.msisdn',
					'value' => '$duration',
					'lines_stamps' => 1,
				),
			),
			array(
				'$match' => array(
					'value' => array('$gte' => $threshold),
				),
			),
		);

		$linesCol = Billrun_Factory::db()->linesCollection();
		return $linesCol->aggregate($aggregateQuery);
	}

	/**
	 * @see Billrun_Plugin_BillrunPluginFraud::addAlertData 
	 */
	protected function addAlertData(&$event) {
		$event['units'] = 'SECS';
		$event['event_type'] = 'MABAL_016';
		return $event;
	}

	////////////////////////////////////////////// Parser ///////////////////////////////////////////

	/**
	 * @see Billrun_Plugin_Interface_IParser::parseData
	 */
	public function parseData($type, $line, Billrun_Parser &$parser) {
		if ($type != $this->getName()) {
			return FALSE;
		}

		$data = array();
		$offset = 0;

		$data['record_length'] = $this->parseField(substr($line, $offset, 2), array('decimal' => 2));
		$offset += 2;
		$data['record_type'] = $this->parseField(substr($line, $offset, 1), array('bcd_encode' => 1));
		$offset += 1;
		//Billrun_Factory::log()->log("Record_type : {$data['record_type']}",Zend_log::DEBUG);
		if (isset($this->nsnConfig[$data['record_type']])) {
			foreach ($this->nsnConfig[$data['record_type']] as $key => $fieldDesc) {
				if ($fieldDesc) {
					if (isset($this->nsnConfig['fields'][$fieldDesc])) {
						$length = intval(current($this->nsnConfig['fields'][$fieldDesc]), 10);
						$data[$key] = $this->parseField(substr($line, $offset, $length), $this->nsnConfig['fields'][$fieldDesc]);
						/* if($data['record_type'] == "12") {//DEBUG...
						  Billrun_Factory::log()->log("Data $key : {$data[$key]} , offset: ".  dechex($offset),Zend_log::DEBUG);
						  } */
						$offset += $length;
					} else {
						throw new Exception("Nsn:parse - Couldn't find field: $fieldDesc  ");
					}
				}
			}
			$data['urt'] = new MongoDate(Billrun_Util::dateTimeConvertShortToIso((string) (isset($data['charging_start_time']) && $data['charging_start_time'] ? $data['charging_start_time'] : $data['call_reference_time']), date("P", strtotime($data['call_reference_time']))));
			//Use the  actual charing time duration instead of the  duration  that  was set by the switch
			if (isset($data['duration'])) {
				$data['org_dur'] = $data['duration']; // save the original duration.
			}
			if (isset($data['charging_end_time']) && isset($data['charging_start_time']) &&
					(strtotime($data['charging_end_time']) > 0 && strtotime($data['charging_start_time']) > 0)) {
				$computed_dur = strtotime($data['charging_end_time']) - strtotime($data['charging_start_time']);
				if ($computed_dur >= 0) {
					$data['duration'] = $computed_dur;
				} else {
					Billrun_Factory::log("Processor received line (cf : " . $data['call_reference'] . " , cft : " . $data['call_reference_time'] . " ) with computed duration of $computed_dur using orginal duration field : {$data['duration']} ", Zend_Log::ALERT);
				}
			}
			//Remove  the  "10" in front of the national call with an international prefix
//		if (isset($data['in_circuit_group_name']) && preg_match("/^RCEL/", $data['in_circuit_group_name']) && strlen($data['called_number']) > 10 && substr($data['called_number'], 0, 2) == "10") { // will fail when in_circuit_group_name is empty / called_number length is exactly 10
			if (isset($data['calling_number'])) {
				$data['calling_number'] = Billrun_Util::msisdn($data['calling_number']);
			}
			if (isset($data['called_number'])) {
			//Remove  the  "10" in front of the national call with an international prefix
				if (isset($data['out_circuit_group']) && in_array($data['out_circuit_group'], Billrun_Util::getIntlCircuitGroups()) && substr($data['called_number'], 0, 2) == "10") {
					$data['called_number'] = substr($data['called_number'], 2);
				} else if (in_array($data['record_type'], array('30')) && (in_array($data['out_circuit_group'], Billrun_Util::getIldsOneWayCircuitGroups())) &&  
                                          (preg_match('/^GNTV|^GBZQ|^GBZI|^GSML|^GHOT/', $data['in_circuit_group_name']))) {
                                                $data['ild_prefix'] = substr($data['in_circuit_group_name'], 0, 4);
                                                if (preg_match($this->ild_called_number_regex, $data['called_number'])){
                                                    $data['called_number'] = substr($data['called_number'], 3);
                                                } 
				}
				if (
					(!isset($data['out_circuit_group'])) 
					|| 
					(
						!(
							($data['out_circuit_group'] >= '2000' && $data['out_circuit_group'] <= '2069') 
							|| 
							($data['out_circuit_group'] >= '2500' && $data['out_circuit_group'] <= '2529') 
							|| 
							($data['out_circuit_group'] >= '1230' && $data['out_circuit_group'] <= '1233')
							||
							(in_array($data['out_circuit_group'], Billrun_Util::getIntlCircuitGroups()))
						)
					)
				) {
					$data['called_number'] = Billrun_Util::msisdn($data['called_number']);
				}
			}
		} else {
//			Billrun_Factory::log()->log("unsupported NSN record type : {$data['record_type']}",Zend_log::DEBUG);
		}

		$parser->setLastParseLength($data['record_length']);

		//@TODO add unifiom field translation. ('record_opening_time',etc...)
		return isset($this->nsnConfig[$data['record_type']]) ? $data : false;
	}

	/**
	 * @see Billrun_Plugin_Interface_IParser::parseSingleField
	 */
	public function parseSingleField($type, $data, Array $fileDesc, Billrun_Parser &$parser = null) {
		if ($type != $this->getName()) {
			return FALSE;
		}

		return $this->parseField($data, $fileDesc);
	}

	/**
	 * @see Billrun_Plugin_Interface_IParser::parseHeader
	 */
	public function parseHeader($type, $data, Billrun_Parser &$parser) {
		if ($type != $this->getName()) {
			return FALSE;
		}

		$header = array();
		foreach ($this->nsnConfig['block_header'] as $key => $fieldDesc) {
			$fieldStruct = $this->nsnConfig['fields'][$fieldDesc];
			$header[$key] = $this->parseField($data, $fieldStruct);
			$data = substr($data, current($fieldStruct));
			//Billrun_Factory::log()->log("Header $key : {$header[$key]}",Zend_log::DEBUG);
		}

		return $header;
	}

	/**
	 * @see Billrun_Plugin_Interface_IParser::parseTrailer
	 */
	public function parseTrailer($type, $data, Billrun_Parser &$parser) {
		if ($type != $this->getName()) {
			return FALSE;
		}

		$trailer = array();
		foreach ($this->nsnConfig['block_trailer'] as $key => $fieldDesc) {
			$fieldStruct = $this->nsnConfig['fields'][$fieldDesc];
			$trailer[$key] = $this->parseField($data, $fieldStruct);
			$data = substr($data, current($fieldStruct));
			//Billrun_Factory::log()->log("Trailer $key : {$trailer[$key]}",Zend_log::DEBUG);
		}
		return $trailer;
	}

	/**
	 * parse a field from raw data based on a field description
	 * @param string $data the raw data to be parsed.
	 * @param array $fileDesc the field description
	 * @return mixed the parsed value from the field.
	 */
	protected function parseField($data, $fileDesc) {
		$type = key($fileDesc);
		$length = $fileDesc[$type];
		$retValue = '';

		switch ($type) {
			case 'decimal' :
				$retValue = 0;
				for ($i = $length - 1; $i >= 0; --$i) {
					$retValue = ord($data[$i]) + ($retValue << 8);
				}
				break;

			case 'phone_number' :
				$val = '';
				for ($i = 0; $i < $length; ++$i) {
					$byteVal = ord($data[$i]);
					for ($j = 0; $j < 2; $j++, $byteVal = $byteVal >> 4) {
						$left = $byteVal & 0xF;
						$digit = $left == 0xB ? '*' :
								($left == 0xC ? '#' :
										($left == 0xA ? 'a' :
												($left == 0xF ? '' :
														($left > 0xC ? dechex($left - 2) :
																$left))));
						$val .= $digit;
					}
				}
				$retValue = $val;
				break;

			case 'long':
				$retValue = 0;
				for ($i = $length - 1; $i >= 0; --$i) {
					$retValue = bcadd(bcmul($retValue, 256), ord($data[$i]));
				}
				break;

			case 'hex' :
				$retValue = '';
				for ($i = $length - 1; $i >= 0; --$i) {
					$retValue .= sprintf('%02s', dechex(ord($data[$i])));
				}
				break;

			case 'reveresed_bcd_encode' :
			case 'datetime':
			case 'bcd_encode' :
			case 'bcd_number' :
				$retValue = '';
				for ($i = $length - 1; $i >= 0; --$i) {
					$byteVal = ord($data[$i]);
					$retValue .= ((($byteVal >> 4) < 10) ? ($byteVal >> 4) : '' ) . ((($byteVal & 0xF) < 10) ? ($byteVal & 0xF) : '');
				}
				if ($type == 'bcd_number') {
					$retValue = intval($retValue, 10);
				}
				if ('reveresed_bcd_encode' == $type) {
					$retValue = strrev($retValue);
				}
				break;

			case 'format_ver' :
				$retValue = $data[0] . $data[1] . ord($data[2]) . '.' . ord($data[3]) . '-' . ord($data[4]);
				break;

			case 'ascii':
				$retValue = preg_replace("/\W/", "", substr($data, 0, $length));
				break;

			case 'call_reference':
				$retValue = strrev(implode(unpack("h*", substr($data, 0, 2)))) . strrev(implode(unpack("h*", substr($data, 2, 2)))) . strrev(implode(unpack("h*", substr($data, 4, 1))));

				break;
			default:
				$retValue = implode(unpack($type, $data));
		}

		return $retValue;
	}

	//////////////////////////////////////////// Processor //////////////////////////////////////

	/**
	 * @see Billrun_Plugin_Interface_IProcessor::isProcessingFinished
	 */
	public function isProcessingFinished($type, $fileHandle, \Billrun_Processor &$processor) {
		if ($type != $this->getName()) {
			return FALSE;
		}
		if (!$this->fileStats) {
			$this->fileStats = fstat($fileHandle);
		}
		$process_finished = feof($fileHandle) ||
				ftell($fileHandle) + self::TRAILER_LENGTH >= $this->fileStats['size'];
		if ($process_finished) {
			$this->fileStats = null;
		}
		return $process_finished;
	}

	/**
	 * Retrive the sequence data  for a ggsn file
	 * @param type $type the type of the file being processed
	 * @param type $filename the file name of the file being processed
	 * @param type $processor the processor instace that triggered the fuction
	 * @return array containing the file sequence data or false if there was an error.
	 */
	public function getFilenameData($type, $filename, &$processor) {
		if ($this->getName() != $type) {
			return FALSE;
		}
		return $this->getFileSequenceData($filename);
	}

	/**
	 * @see Billrun_Plugin_Interface_IProcessor::processData
	 */
	public function processData($type, $fileHandle, \Billrun_Processor &$processor) {
		if ($type != $this->getName()) {
			return FALSE;
		}
		$bytes = null;

		$headerData = fread($fileHandle, self::HEADER_LENGTH);
		$header = $processor->getParser()->parseHeader($headerData);
		if (isset($header['data_length_in_block']) && !feof($fileHandle)) {
			$bytes = fread($fileHandle, $header['data_length_in_block'] - self::HEADER_LENGTH);
		}
		if (in_array($header['format_version'], $this->nsnConfig['block_config']['supported_versions'])) {
			do {
				$row = $processor->buildDataRow($bytes);
				if ($row) {
					$processor->addDataRow($row);
				}
				$bytes = substr($bytes, $processor->getParser()->getLastParseLength());
			} while (isset($bytes[self::TRAILER_LENGTH + 1]));
		} else {
			$msg  = "Got NSN block with unsupported version :  {$header['format_version']} , block header data : " . print_r($header, 1);
			Billrun_Factory::log()->log($msg, Zend_log::CRIT);
			throw new Exception($msg);
		}

		$trailer = $processor->getParser()->parseTrailer($bytes);
		//align the readhead
		$alignment = self::RECORD_ALIGNMENT * max(1, $header['charging_block_size']);
		if (($alignment - $header['data_length_in_block']) > 0) {
			fread($fileHandle, ($alignment - $header['data_length_in_block']));
		}

		//add trailer data
		$processorData = &$processor->getData();
		$processorData['trailer'] = $this->updateBlockData($trailer, $header, $processorData['trailer']);

		return true;
	}

	/**
	 * Add block related data from the processor to the log DB collection entry.
	 * @param type $trailer the block header data
	 * @param type $header the block tralier
	 * @param type $logTrailer the log db trailer entry of the paresed file
	 * @return the updated log  trailer entry. 
	 */
	protected function updateBlockData($trailer, $header, $logTrailer) {
		if (Billrun_Factory::config()->getConfigValue('nsn.processor.save_block_header', false)) {
			if (!isset($logTrailer['block_data'])) {
				$logTrailer['block_data'] = array();
			}
			if (!isset($logTrailer['batch'])) {
				$logTrailer['batch'] = array();
			}
			if (!in_array($header['batch_seq_number'], $logTrailer['batch'])) {
				$logTrailer['batch'][] = $header['batch_seq_number'];
			}
			$logTrailer['block_data'][] = array('last_record_number' => $trailer['last_record_number'],
				'first_record_number' => $header['first_record_number'],
				'seq_no' => $header['block_seq_number']);
		}
		return $logTrailer;
	}
	
	
	public function beforeProcessorStore($processor){
		$data = &$processor->getData();
		foreach ($data['data'] as $line) {
			if (isset($line['type']) && $line['type'] == 'nsn' && ((!isset($line['duration']) || ($line['duration'] <= 0)) && (isset($line['cause_for_termination']) && $line['cause_for_termination'] != '00000000'))){
				$processor->unsetQueueRow($line['stamp']);
			}
		}
	}

}

?>

<?php
require_once __DIR__ . '/AsnParsing.php';

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * This a plgunin to provide GGSN support to the billing system.
 */
class ggsnPlugin extends Billrun_Plugin_BillrunPluginFraud implements	Billrun_Plugin_Interface_IParser, 
																		Billrun_Plugin_Interface_IProcessor {
    use AsnParsing;
	
	protected $hostSequenceCheckers = array();
	
	const HEADER_LENGTH = 54;
	const MAX_CHUNKLENGTH_LENGTH = 512;
	const FILE_READ_AHEAD_LENGTH = 8196;

	
	public function __construct($options = array()) {
		parent::__construct($options);
		
		$this->ggsnConfig = parse_ini_file(Billrun_Factory::config()->getConfigValue('ggsn.config_path'), true);
	}
	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'ggsn';

	/**
	 * method to collect data which need to be handle by event
	 */
	public function handlerCollect() {
		$lines = Billrun_Factory::db()->getCollection(Billrun_DB::lines_table);
		$charge_time = $this->get_last_charge_time();

		$aggregateQuery = $this->getBaseAggregateQuery($charge_time); 
		
		$dataExceedersAlerts = $this->detectDataExceeders($lines, $aggregateQuery);
		$hourlyDataExceedersAlerts = $this->detectHourlyDataExceeders($lines, $aggregateQuery);
		
		return array_merge($dataExceedersAlerts, $hourlyDataExceedersAlerts);
	}
	
	/**
	 * Setup the sequence checker.
	 * @param type $receiver
	 * @param type $hostname
	 * @return type
	 */
	public function beforeFTPReceive($receiver,  $hostname) {
		if($receiver->getType() != 'ggsn') { return; } 
		if(!isset($this->hostSequenceCheckers[$hostname])) {
			$this->hostSequenceCheckers[$hostname] = new Billrun_Common_FileSequenceChecker(array($this,'getFileSequenceData'), $hostname, $this->getName() );
		}
	}
	
	/**
	 * Check the  received files sequence.
	 * @param type $receiver
	 * @param type $filepaths
	 * @param type $hostname
	 * @return type
	 * @throws Exception
	 */
	public function afterFTPReceived($receiver,  $filepaths , $hostname ) {
		if($receiver->getType() != 'ggsn') { return; } 
		if(!isset($this->hostSequenceCheckers[$hostname])) { 
			throw new Exception('Couldn`t find hostname in sequence checker might be a problem with the program flow.');
		}
		$mailMsg = FALSE;
		
		if($filepaths) {
			foreach($filepaths as $path) {
				$ret = $this->hostSequenceCheckers[$hostname]->addFileToSequence(basename($path));
				if($ret) {
					$mailMsg .= $ret . "\n";
				}
			}
			$ret = $this->hostSequenceCheckers[$hostname]->hasSequenceMissing();
			if($ret) {
					$mailMsg .=  "GGSN Reciever : Received a file out of sequence from host : $hostname - for the following files : \n";
					foreach($ret as $file) {
						$mailMsg .= $file . "\n";
					}
			}
		} else if ($this->hostSequenceCheckers[$hostname]->lastLogFile) {
			$timediff = time()- strtotime($this->hostSequenceCheckers[$hostname]->lastLogFile['received_time']);
			if($timediff > Billrun_Factory::config()->getConfigValue('ggsn.receiver.max_missing_file_wait',3600) ) {
				$mailMsg = 'Didn`t received any new GGSN files form host '.$hostname.' for more then '.$timediff .' Seconds';
			}
		}
		//If there were any errors log them as high issues 
		if($mailMsg) {
			Billrun_Factory::log()->log($mailMsg,  Zend_Log::ALERT);
		}
	}

	/**
	 * An helper function for the Billrun_Common_FileSequenceChecker  ( helper :) ) class.
	 * Retrive the ggsn file date and sequence number
	 * @param type $filename the full file name.
	 * @return boolea|Array false if the file couldn't be parsed or an array containing the file sequence data
	 *						[seq] => the file sequence number.
	 *						[date] => the file date.  
	 */
	public function getFileSequenceData($filename) {
		$pregResults = array();
		if(!preg_match("/\w+_-_(\d+)\.(\d+)_-_\d+\+\d+/",$filename, $pregResults) ) {
						return false;
		}
		return array('seq'=> intval($pregResults[1],10), 'date' => $pregResults[2] );
	}

	/**
	 * Detect data usage above an houlrly limit
	 * @param Mongoldoid_Collection $linesCol the db lines collection
	 * @param Array $aggregateQuery the standard query to aggregate data (see $this->getBaseAggregateQuery())
	 * @return Array containing all the hourly data excceders.
	 */
	protected function detectHourlyDataExceeders($linesCol, $aggregateQuery) {
		$exceeders = array();
		$timeWindow= strtotime("-" . Billrun_Factory::config()->getConfigValue('ggsn.hourly.timespan','4 hours'));
		$limit = floatval(Billrun_Factory::config()->getConfigValue('ggsn.hourly.thresholds.datalimit',0));
		$aggregateQuery[0]['$match']['$and'] =  array( array('record_opening_time' => array('$gte' => date('YmdHis',$timeWindow))),
														array('record_opening_time' => $aggregateQuery[0]['$match']['record_opening_time']) );						
	
		//unset($aggregateQuery[0]['$match']['sgsn_address']);
		unset($aggregateQuery[0]['$match']['record_opening_time']);
		
		$having =	array(
				'$match' => array(
					'$or' => array(
							array( 'download' => array( '$gte' => $limit ) ),
							array( 'upload' => array( '$gte' => $limit ) ),		
					),
				),
			);
		foreach($linesCol->aggregate(array_merge($aggregateQuery, array($having))) as $alert) {
			$alert['units'] = 'KB';
			$alert['value'] = ($alert['download'] > $limit ? $alert['download'] : $alert['upload']);
			$alert['threshold'] = $limit;
			$alert['event_type'] = 'GGSN_HOURLY_DATA';
			$exceeders[] = $alert;
		}
		return $exceeders;
	}
	
	/**
	 * Run arrgregation to find excess usgae of data.
	 * @param type $lines the cdr lines db collection instance.
	 * @param type $aggregateQuery the general aggregate query.
	 * @return Array containing all the exceeding events.
	 */
	protected function detectDataExceeders($lines,$aggregateQuery) {
		$limit = floatval(Billrun_Factory::config()->getConfigValue('ggsn.thresholds.datalimit',1000));
		$dataThrs =	array(
				'$match' => array(
					'$or' => array(
							array( 'download' => array( '$gte' => $limit ) ),
							array( 'upload' => array( '$gte' => $limit ) ),		
					),
				),
			);
		$dataAlerts = $lines->aggregate(array_merge($aggregateQuery, array($dataThrs)) );
		foreach($dataAlerts as &$alert) {
			$alert['units'] = 'KB';
			$alert['value'] = ($alert['download'] > $limit ? $alert['download'] : $alert['upload']);
			$alert['threshold'] = $limit;
			$alert['event_type'] = 'GGSN_DATA';
		}
		return $dataAlerts;
	}
	
	/**
	 * detected data duration usage exceeders.
	 * @param type $lines the cdr lines db collection instance.
	 * @param type $aggregateQuery the general aggregate query.
	 * @return Array containing all the exceeding  duration events.
	 */
	protected function detectDurationExceeders($lines,$aggregateQuery) {
		$threshold = floatval(Billrun_Factory::config()->getConfigValue('ggsn.thresholds.duration',2400));
		unset($aggregateQuery[0]['$match']['$or']);
		
		$durationThrs =	array(
				'$match' => array(
					'duration' => array('$gte' => $threshold )
				),
			);
		
		$durationAlert = $lines->aggregate(array_merge($aggregateQuery, array($durationThrs)) );
		foreach($durationAlert as &$alert) {
			$alert['units'] = 'SEC';
			$alert['value'] = $alert['duration'];
			$alert['threshold'] = $threshold;
			$alert['event_type'] = 'GGSN_DATA_DURATION';
		}
		return $durationAlert;
	}
	
	/**
	 * Get the base aggregation query.
	 * @param type $charge_time the charge time of the billrun (records will not be pull before that)
	 * @return Array containing a standard PHP mongo aggregate query to retrive  ggsn entries by imsi.
	 */
	protected function getBaseAggregateQuery($charge_time) {
		return array(
				array(
					'$match' => array(
						'type' => 'ggsn',
						'deposit_stamp' => array('$exists' => false),
						'event_stamp' => array('$exists' => false),
						'record_opening_time' => array('$gt' => $charge_time),
						'sgsn_address' => array('$regex' => '^(?!62\.90\.|37\.26\.)'),
						'$or' => array(
										array('fbc_downlink_volume' => array('$gt' => 0 )),
										array('fbc_uplink_volume' => array('$gt' => 0))
									),
					),
				),
				array(
					'$group' => array(
						"_id" => array('imsi'=>'$served_imsi','msisdn' =>'$served_msisdn'),
						"download" => array('$sum' => '$fbc_downlink_volume'),
						"upload" => array('$sum' => '$fbc_uplink_volume'),
						"duration" => array('$sum' => '$duration'),
						'lines_stamps' => array('$addToSet' => '$stamp'),
					),	
				),
				array(
					'$project' => array(
						'_id' => 0,
						'download' => array('$multiply' => array('$download',0.001)),
						'upload' => array('$multiply' => array('$download',0.001)),
						'duration' => 1,
						'imsi' => '$_id.imsi',
						'msisdn' => array('$substr'=> array('$_id.msisdn',5,10)),
						'lines_stamps' => 1,
					),
				),
			);
	}

	/**
	 * @see Billrun_Plugin_BillrunPluginFraud::addAlertData
	 */
	protected function addAlertData(&$event) {
		return $event;
	}

	public function parseData($type, $data, \Billrun_Parser &$parser) {
		if($this->getName() != $type) { return FALSE; }
		
		$asnObject = Asn_Base::parseASNString($data);
		$parser->setLastParseLength($asnObject->getDataLength()+8);
		
		$type = $asnObject->getType();
		$cdrLine = false;
		
		if(isset($this->ggsnConfig[$type])) {
			$cdrLine =  $this->getASNDataByConfig($asnObject, $this->ggsnConfig[$type] , $this->ggsnConfig['fields']);			
			if($cdrLine && !isset($cdrLine['record_type'])) {
				$cdrLine['record_type'] = $type;
			}
		} else {
			Billrun_Factory::log()->log("couldn't find  definition for {$type}",  Zend_Log::DEBUG);
		}
	//	Billrun_Factory::log()->log($asnObject->getType() . " : " . print_r($cdrLine,1) ,  Zend_Log::DEBUG);
		return $cdrLine;
	
	}

	public function parseHeader($type, $data, \Billrun_Parser &$parser) {
		if($this->getName() != $type) { return FALSE; }	
		
		$header = false;//$this->getASNDataByConfig($data, $this->ggsnConfig['header'], $this->ggsnConfig['fields']);		
		Billrun_Factory::log()->log(print_r($header,1),  Zend_Log::DEBUG);
		
		return $header;
	}

	public function parseSingleField($type, $data, array $fieldDesc, \Billrun_Parser &$parser) {
		return $this->parseField($fieldDesc,$data);
	}

	public function parseTrailer($type, $data, \Billrun_Parser &$parser) {
			if($this->getName() != $type) { return FALSE; }	
		
		$trailer = false;//$this->getASNDataByConfig($data, $this->ggsnConfig['trailer'], $this->ggsnConfig['fields']);		
		Billrun_Factory::log()->log(print_r($trailer,1),  Zend_Log::DEBUG);
		
		return $trailer;
	}
	
	/**
	 * Parse an binary field using a specific data structure.
	 */
	protected function parseField($type, $fieldData) {
		if (isset($fieldData)) {
			switch ($type) {
				/* //TODO remove */
				case 'debug':					
					$numarr = unpack("C*", $fieldData);
					$numData = 0;
					foreach ($numarr as $byte) {
						//$fieldData = $fieldData <<8;
						$numData = ($numData << 8 ) + $byte;
					}
					$halfBytes = unpack("C*", $fieldData);
					$tempData = "";
					foreach ($halfBytes as $byte) {
						$tempData .= ($byte & 0xF) . ((($byte >> 4) < 10) ? ($byte >> 4) : "" );
					}
					Billrun_Factory::log()->log( "DEBUG : " . $type . " | " . $numData . " | " . $tempData . " | " . implode(unpack("H*", $fieldData)) . " | " . implode(unpack("C*", $fieldData)) . " | " . $fieldData ,  Zend_Log::DEBUG);
					$fieldData = "";
					break;

				case 'string':
					$fieldData = utf8_encode($fieldData);
					break;

				case 'long':
					$numarr = unpack('C*', $fieldData);
					$fieldData = 0;
					foreach ($numarr as $byte) {						
						$fieldData = bcadd(bcmul($fieldData , 256 ), $byte);
					}
					break;

				case 'number':
					$numarr = unpack('C*', $fieldData);
					$fieldData = 0;
					foreach ($numarr as $byte) {
						$fieldData = ($fieldData << 8) + $byte;
					}
					break;
					
				case 'BCDencode' :
					$halfBytes = unpack('C*', $fieldData);
					$fieldData = '';
					foreach ($halfBytes as $byte) {
						//$fieldData = $fieldData <<8;
						$fieldData .= ($byte & 0xF) . ((($byte >> 4) < 10) ? ($byte >> 4) : '' );
					}
					break;

				case 'ip' :
					$fieldData = implode('.', unpack('C*', $fieldData));
					break;

				case 'datetime' :
					$tempTime = DateTime::createFromFormat('ymdHisT', str_replace('2b', '+', implode(unpack('H*', $fieldData))));
					$fieldData = is_object($tempTime) ? $tempTime->format('YmdHis') : '';
					break;

				case 'json' :
					$fieldData = json_encode($this->utf8encodeArr($fieldData));
					break;
				
				case 'ch_ch_selection_mode':
					$selection_mode = array(0 => 'sGSNSupplied',
											1 => 'subscriptionSpecific',
											2 => 'aPNSpecific',
											3 => 'homeDefault',
											4 => 'roamingDefault',
											5 => 'visitingDefault');
					$smode = intval(implode('.', unpack('C', $fieldData)));
					$fieldData = isset($selection_mode[$smode]) ? $selection_mode[$smode] : false; 
					break;
				
				default:
					$fieldData = is_array($fieldData) ? '' : implode('', unpack($type, $fieldData));
			}
		}
		return $fieldData;
	}

	public function isProcessingFinished($type, $fileHandle, \Billrun_Processor &$processor) {
		return !feof($fileHandle);
	}

	public function processData($type, $fileHandle, \Billrun_Processor &$processor) {
		$processedData = &$processor->getData();
		$processedData['header'] = $processor->buildHeader(fread($fileHandle, self::HEADER_LENGTH));

		$bytes = null;
		do {
			if ( !feof($fileHandle) && !isset($bytes[self::MAX_CHUNKLENGTH_LENGTH]) ) {
				$bytes .= fread($fileHandle, self::FILE_READ_AHEAD_LENGTH);
			}

			$row = $processor->buildDataRow($bytes);
			if ($row) {
				$processedData['data'][] = $row;
			}
			//Billrun_Factory::log()->log( $processor->getParser()->getLastParseLength(),  Zend_Log::DEBUG);	
			$bytes = substr($bytes, $processor->getParser()->getLastParseLength());
		} while (isset($bytes[self::HEADER_LENGTH]));
		
		$processedData['trailer'] = $processor->buildTrailer($bytes);

		return true;
	}
}
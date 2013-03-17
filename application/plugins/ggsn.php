<?php
require_once __DIR__ . '/AsnParsing.php';

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * This a plguin to provide GGSN support to the billing system.
 */
class ggsnPlugin extends Billrun_Plugin_BillrunPluginFraud implements	Billrun_Plugin_Interface_IParser, 
																		Billrun_Plugin_Interface_IProcessor {
    use AsnParsing;
		
	const HEADER_LENGTH = 54;
	const MAX_CHUNKLENGTH_LENGTH = 512;
	const FILE_READ_AHEAD_LENGTH = 8196;

	
	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'ggsn';

	/**
	 * Holds sequence checkers 
	 * @var Array of
	 */
	protected $hostSequenceCheckers = array();
	
	public function __construct($options = array()) {
		parent::__construct($options);
		
		$this->ggsnConfig = parse_ini_file(Billrun_Factory::config()->getConfigValue('ggsn.config_path'), true);
		$this->initParsing();
		$this->addParsingMethods();
	}
	
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
	
	///////////////////////////////////////////// Parser ////////////////////////////////////////////
	/**
	 * @see Billrun_Plugin_Interface_IParser::parseData
	 */
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
		//Billrun_Factory::log()->log($asnObject->getType() . " : " . print_r($cdrLine,1) ,  Zend_Log::DEBUG);
		return $cdrLine;
	
	}
	
	/**
	 * @see Billrun_Plugin_Interface_IParser::parseHeader
	 */
	public function parseHeader($type, $data, \Billrun_Parser &$parser) {
		if($this->getName() != $type) { return FALSE; }	
		
		$header = utf8_encode(base64_encode($data));//$this->getASNDataByConfig($data, $this->ggsnConfig['header'], $this->ggsnConfig['fields']);		
		
		return $header;
	}
	
	/**
	 * @see Billrun_Plugin_Interface_IParser::parseSingleField
	 */
	public function parseSingleField($type, $data, array $fieldDesc, \Billrun_Parser &$parser) {
		return $this->parseField($fieldDesc,$data);
	}

	/**
	 * @see Billrun_Plugin_Interface_IParser::parseTrailer
	 */
	public function parseTrailer($type, $data, \Billrun_Parser &$parser) {
			if($this->getName() != $type) { return FALSE; }	
		
		$trailer = utf8_encode(base64_encode($data));//$this->getASNDataByConfig($data, $this->ggsnConfig['trailer'], $this->ggsnConfig['fields']);		
	
		return $trailer;
	}
	
	/**
	 * add GGSN specific parsing methods.
	 */
	protected function addParsingMethods() {
		$newParsingMethods = array(
				'diagnostics' => function($data) {
						$ret = false;
						$diags = $this->ggsnConfig['fields_translate']['diagnostics'];
						if(!is_array($data)) {
							$diag = intval(implode('.', unpack('C', $data)));
							$ret = isset($diags[$diag]) ? $diags[$diag] : false; 
						} else {
							foreach($diags as $key => $diagnostics) {
								if(is_array($diagnostics) && isset($data[$key]) ) {
									$diag = intval(implode('.', unpack('C', $data[$key])));
									Billrun_Factory::log()->log($diag. " : " . $diagnostics[$diag],  Zend_Log::DEBUG);
									$ret = $diagnostics[$diag];

								}
							}
						}
						return $ret;
					},
					
				'ch_ch_selection_mode' => function($data) {	
						$smode = intval(implode('.', unpack('C', $data)));
						return (isset($this->ggsnConfig['fields_translate']['ch_ch_selection_mode'][$smode]) ? 
											$this->ggsnConfig['fields_translate']['ch_ch_selection_mode'][$smode] : 
											false);
					},
				'bcd_encode' => function($fieldData)	{
						$halfBytes = unpack('C*', $fieldData);
						$ret = '';
						foreach ($halfBytes as $byte) {
							$ret .=   ($byte & 0xF) . ((($byte >> 4) < 10) ? ($byte >> 4) : '' ) ;
						}
						return $ret;
					},
				'default' => function($type, $data) {
						return (is_array($data) ? '' : implode('', unpack($type, $data)));
					},
				);
					
			$this->parsingMethods  = array_merge( $this->parsingMethods, $newParsingMethods );
	}
	
	
	//////////////////////////////////////////// Processor ////////////////////////////////////////////
	
	/**
	 * @see Billrun_Plugin_Interface_IProcessor::processData
	 */
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
	
	/**
	 * @see Billrun_Plugin_Interface_IProcessor::isProcessingFinished
	 */
	public function isProcessingFinished($type, $fileHandle, \Billrun_Processor &$processor) {
		return !feof($fileHandle);
	}

}
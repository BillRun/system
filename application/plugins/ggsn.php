<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a plguin to provide GGSN support to the billing system.
 */
class ggsnPlugin extends Billrun_Plugin_Base implements Billrun_Plugin_Interface_IParser, Billrun_Plugin_Interface_IProcessor {

	use Billrun_Traits_AsnParsing,
	 Billrun_Traits_FileSequenceChecking,
	 Billrun_Traits_FraudAggregation;

	const HEADER_LENGTH = 54;
	const MAX_CHUNKLENGTH_LENGTH = 4096;
	const FILE_READ_AHEAD_LENGTH = 16384;
	const RECORD_PADDING = 4;

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'ggsn';

	public function __construct(array $options = array()) {
		$this->ggsnConfig = (new Yaf_Config_Ini(Billrun_Factory::config()->getConfigValue('ggsn.config_path')))->toArray();
		$this->initParsing();
		$this->addParsingMethods();
		$this->initFraudAggregation();
		if(!empty(Billrun_Factory::config()->getConfigValue('ggsn.fraud.db'))) {
			$this->fraudCollection = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('ggsn.fraud.db'))->linesCollection();
		}
	}

	/////////////////////////////////////////  Alerts /////////////////////////////////////////

	/**
	 * method to collect data which need to be handle by event
	 */
	public function handlerCollect($options) {
		if ($options['type'] != 'ggsn') {
			return FALSE;
		}

		$events = array();
		//@TODO  switch  these lines  once  you have the time to test it.
		//$charge_time = new MongoDate($this->get_last_charge_time(true) - date_default_timezone_get() );
		$charge_time = Billrun_Util::getLastChargeTime(true);

		$advancedEvents = array();
		if (isset($this->fraudConfig['groups'])) {
			foreach ($this->fraudConfig['groups'] as $groupName => $groupIds) {
				$baseQuery = $this->getBaseAggregateQuery($charge_time, $groupName, $groupIds, true);
				$advancedEvents = $this->collectFraudEvents($groupName, $groupIds, $baseQuery);
				
				$events = array_merge($events, $advancedEvents);
			}
		}
		
		return $events;
	}

	/**
	 * Setup the sequence checker.
	 * @param type $receiver
	 * @param type $hostname
	 * @return type
	 */
	public function beforeFTPReceive($receiver, $hostname) {
		if ($receiver->getType() != 'ggsn') {
			return;
		}
		$this->setFilesSequenceCheckForHost($hostname);
	}

	/**
	 * Check the  received files sequence.
	 * @param type $receiver
	 * @param type $filepaths
	 * @param type $hostname
	 * @return type
	 * @throws Exception
	 */
	public function afterFTPReceived($receiver, $filepaths, $hostname) {
		if ($receiver->getType() != 'ggsn') {
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
	 * Get the base aggregation query.
	 * @param type $charge_time the charge time of the billrun (records will not be pull before that)
	 * @return Array containing a standard PHP mongo aggregate query to retrive  ggsn entries by imsi.
	 */
	protected function getBaseAggregateQuery($charge_time, $groupName, $groupMatch, $clean = false) {
		$ret = array(
			'base_match' => array(
				'$match' => array(
					'type' => 'ggsn',
				)
			),
			'where' => array(
				'$match' => array(
					'event_stamp' => array('$exists' => false),
					'$or' => array(
						array('rating_group' => array('$exists' => false)),
						array('rating_group' => 0)
					),
				),
			),
			'group_match' => array(
				'$match' => $groupMatch,
			),
			'group' => array(
				'$group' => array(
					"_id" => array('imsi' => '$served_imsi','sid'=>'$sid','aid'=>'$aid'),
					"usagev" => array('$sum' => '$usagev'),
					'aprice' => array('$sum' => '$aprice'),
					"duration" => array('$sum' => '$duration'),
					'lines_stamps' => array('$addToSet' => '$stamp'),
				),
			),
			'translate' => array(
				'$project' => array(
					'_id' => 0,
					'aid' => '$_id.aid',
					'sid' => '$_id.sid',
					'usagev' => array('$multiply' => array('$usagev', 0.0009765625)),
					'aprice' => 1,
					'imsi' => '$_id.imsi',
					'lines_stamps' => 1,
				),
			),
			'project' => array(
				'$project' => array_merge(array(
					'usagev' => 1,
					'imsi' => 1,
					'sid' => 1,
					'aid' => 1,
					'aprice' => 1,
					'lines_stamps' => 1,
						), $this->addToProject(array('group' => $groupName,))),
			),
		);
		if (!$clean) {
//			$ret['base_match']['$match']['$or'] = array(
//				array('urt' => array('$gte' => new MongoDate($charge_time))),
//				array('unified_record_time' => array('$gte' => new MongoDate($charge_time))),
//			);
			$ret['base_match']['$match']['unified_record_time'] = array('$gte' => new MongoDate($charge_time));
			//$ret['where']['$match']['sgsn_address'] = array('$regex' => '^(?!62\.90\.|37\.26\.)');
		}

		return $ret;
	}

	/**
	 * @see Billrun_Plugin_BillrunPluginFraud::addAlertData
	 */
	protected function addAlertData(&$event) {
		$event['effects'] = array(
			'key' => 'type',
		);
		return $event;
	}

	///////////////////////////////////////////// Parser ////////////////////////////////////////////
	/**
	 * @see Billrun_Plugin_Interface_IParser::parseData
	 */
	public function parseData($type, $data, \Billrun_Parser &$parser) {
		if ($this->getName() != $type) {
			return FALSE;
		}

		$asnObject = Asn_Base::parseASNString($data);
		$parser->setLastParseLength($asnObject->getRawDataLength() + intval(Billrun_Util::getFieldVal($this->ggsnConfig['revision_specific'][$this->currentRevision]['record_padding'],self::RECORD_PADDING)));

		$type = $asnObject->getType();
		$cdrLine = false;

		if (isset($this->ggsnConfig[$type])) {
			$cdrLine = $this->getASNDataByConfig($asnObject, $this->ggsnConfig[$type], $this->ggsnConfig['fields']);
			if ($cdrLine && !isset($cdrLine['record_type'])) {
				$cdrLine['record_type'] = $type;
			}
			//convert to unified time GMT  time.
			$timeOffset =  date('P');
			$cdrLine['urt'] = new MongoDate(Billrun_Util::dateTimeConvertShortToIso($cdrLine['record_opening_time'], $timeOffset));
			if (isset($cdrLine['ms_timezone'])) {
				$cdrLine['callEventStartTimeStamp'] = (new DateTime('@' . ($cdrLine['urt']->sec + Billrun_Util::getTimezoneOffsetInSeconds($cdrLine['ms_timezone']))))->format('YmdHis');
			}
			else {
				$cdrLine['callEventStartTimeStamp'] = $cdrLine['record_opening_time'];
			}
			if (!empty($cdrLine['rating_group']) && is_array($cdrLine['rating_group'])) {
				$fbc_uplink_volume = $fbc_downlink_volume = 0;
				$cdrLine['org_fbc_uplink_volume'] = $cdrLine['fbc_uplink_volume'];
				$cdrLine['org_fbc_downlink_volume'] = $cdrLine['fbc_downlink_volume'];
				$cdrLine['org_rating_group'] = $cdrLine['rating_group'];

				foreach ($cdrLine['rating_group'] as $key => $rateVal) {
					if (isset($this->ggsnConfig['rating_groups'][$rateVal])) {
						$fbc_uplink_volume += $cdrLine['fbc_uplink_volume'][$key];
						$fbc_downlink_volume += $cdrLine['fbc_downlink_volume'][$key];
					}
				}
				$cdrLine['fbc_uplink_volume'] = $fbc_uplink_volume;
				$cdrLine['fbc_downlink_volume'] = $fbc_downlink_volume;
				$cdrLine['rating_group'] = 0;
			} else if (!empty($cdrLine['rating_group']) && $cdrLine['rating_group'] == 10) {
				return false;
			}
		} else {
			Billrun_Factory::log()->log("couldn't find  definition for {$type}", Zend_Log::INFO);
		}
		if (isset($cdrLine['calling_number'])) {
			$cdrLine['calling_number'] = Billrun_Util::msisdn($cdrLine['calling_number']);
		}
		if (isset($cdrLine['called_number'])) {
			$cdrLine['called_number'] = Billrun_Util::msisdn($cdrLine['called_number']);
		}

		//Billrun_Factory::log()->log($asnObject->getType() . " : " . print_r($cdrLine,1) ,  Zend_Log::DEBUG);
		return $cdrLine;
	}

	/**
	 * @see Billrun_Plugin_Interface_IParser::parseHeader
	 */
	public function parseHeader($type, $data, \Billrun_Parser &$parser) {
		if ($this->getName() != $type) {
			return FALSE;
		}
		$nx12Data = unpack("N", substr($data, 0x12, 4));
		$header['line_count'] = reset($nx12Data);
		$nx16Data = unpack("N", substr($data, 0x16, 4));
		$header['next_file_number'] = reset($nx16Data);
		//Billrun_Factory::log(print_r($header,1));
		$rev = unpack("C", substr($data, 0x7, 1));
		$this->currentRevision = $header['revision'] = decoct( reset($rev) );
		$header['raw'] = utf8_encode(base64_encode($data)); // Is  this  needed?

		return $header;
	}

	/**
	 * @see Billrun_Plugin_Interface_IParser::parseSingleField
	 */
	public function parseSingleField($type, $data, array $fieldDesc, \Billrun_Parser &$parser) {
		if ($this->getName() != $type) {
			return FALSE;
		}
		return $this->parseField($fieldDesc, $data);
	}

	/**
	 * @see Billrun_Plugin_Interface_IParser::parseTrailer
	 */
	public function parseTrailer($type, $data, \Billrun_Parser &$parser) {
		if ($this->getName() != $type) {
			return FALSE;
		}

		$trailer = utf8_encode(base64_encode($data)); // Is  this  needed?

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
				if (!is_array($data)) {
					$diag = intval(implode('.', unpack('C', $data)));
					$ret = isset($diags[$diag]) ? $diags[$diag] : false;
				} else {
					foreach ($diags as $key => $diagnostics) {
						if (is_array($diagnostics) && isset($data[$key])) {
							$diag = intval(implode('.', unpack('C', $data[$key])));
							Billrun_Factory::log()->log($diag . " : " . $diagnostics[$diag], Zend_Log::DEBUG);
							$ret = $diagnostics[$diag];
						}
					}
				}
				return $ret;
			},
			'timezone' => function ($data) {
				$smode = unpack('c*', $data);
				//$timeSaving=intval( $smode[2] & 0x3 );
				//time zone offset is repesented by multiples of 15 minutes.
				$quarterOffset = Billrun_Util::bcd_decode($smode[1] & 0xF7);
				if (abs($quarterOffset) <= 52) {//data sanity check less then 13hours  offset
					$h = str_pad(abs(intval($quarterOffset / 4)), 2, "0", STR_PAD_LEFT); // calc the offset hours
					$m = str_pad(abs(($quarterOffset % 4) * 15), 2, "0", STR_PAD_LEFT); // calc the offset minutes
					return ((($smode[1] & 0x8) == 0) ? "+" : "-") . "$h:$m";
				}
				//Billrun_Factory::log()->log($data. " : ". print_r($smode,1),Zend_Log::DEBUG );
				return false;
			},
			'ch_ch_selection_mode' => function($data) {
				$smode = intval(implode('.', unpack('C', $data)));
				return (isset($this->ggsnConfig['fields_translate']['ch_ch_selection_mode'][$smode]) ?
						$this->ggsnConfig['fields_translate']['ch_ch_selection_mode'][$smode] :
						false);
			},
			'bcd_encode' => function($fieldData) {
				$halfBytes = unpack('C*', $fieldData);
				$ret = '';
				foreach ($halfBytes as $byte) {
					$ret .= Billrun_Util::bcd_decode($byte);
				}
				return $ret;
			},
			'location_information' => function($fieldData) {
				list($locationType) = array_values(unpack('C1', $fieldData));
				$ret = ['loc_type'=> $locationType];
				if(isset($locationType)) {
					$mcc = Billrun_Util::bcd_unpack('C2', substr($fieldData,1));
					$ret['mcc'] = substr($mcc,0,3);
					$ret['mnc'] = Billrun_Util::bcd_unpack('C', substr($fieldData,3)). substr($mcc,3,1);
					$ret['lac'] = intval(Billrun_Util::bcd_unpack('C2', substr($fieldData,4)));

					switch($locationType) {
						case 0 : $ret['ci'] = intval(Billrun_Util::bcd_unpack('C*', substr($fieldData,6)));
							break;
						case 1: $ret['sac'] = intval(Billrun_Util::bcd_unpack('C*', substr($fieldData,6)));
							break;
						case 2: $ret['rac'] =  intval(Billrun_Util::bcd_unpack('C*', substr($fieldData,6)));
							break;
						default :
							Billrun_Factory::log("Unidentified location information type...z just keeping all the left overdata in 'unknown' field",Zend_log::WARN);
							$ret['unknown'] =  Billrun_Util::bcd_unpack('C*', substr($fieldData,6));
 					}
				}
// 				Billrun_Factory::log(json_encode($ret));
				return $ret;
			},
			'default' => function($type, $data) {
				return (is_array($data) ? '' : implode('', unpack($type, $data)));
			},
		);

		$this->parsingMethods = array_merge($this->parsingMethods, $newParsingMethods);
	}


	//////////////////////////////////////////// Processor ////////////////////////////////////////////

	/**
	 * @see Billrun_Plugin_Interface_IProcessor::processData
	 */
	public function processData($type, $fileHandle, \Billrun_Processor &$processor) {
		if ($this->getName() != $type) {
			return FALSE;
		}
		$processedData = &$processor->getData();
		$processedData['header'] = $processor->buildHeader(fread($fileHandle, self::HEADER_LENGTH));
		if( !empty($headerPadding = intval(Billrun_Util::getFieldVal($this->ggsnConfig['revision_specific'][$this->currentRevision]['header_padding'],0))) ) {
			fread($fileHandle,$headerPadding);
		}
		$bytes = null;
		while (true) {
			if (!feof($fileHandle) && !isset($bytes[self::MAX_CHUNKLENGTH_LENGTH])) {
				$bytes .= fread($fileHandle, self::FILE_READ_AHEAD_LENGTH);
			}
			if (!isset($bytes[self::HEADER_LENGTH])) {
				break;
			}
			$row = $processor->buildDataRow($bytes);
			if ($row) {
				$row['stamp'] = md5($bytes);
				$processedData['data'][] = $row;
			}
			//Billrun_Factory::log()->log( $processor->getParser()->getLastParseLength(),  Zend_Log::DEBUG);
			$advance = $processor->getParser()->getLastParseLength();
			$bytes = substr($bytes, $advance <= 0 ? 1 : $advance);
		}

		$processedData['trailer'] = $processor->buildTrailer($bytes);

		return true;
	}

	/**
	 * @see Billrun_Plugin_Interface_IProcessor::isProcessingFinished
	 */
	public function isProcessingFinished($type, $fileHandle, \Billrun_Processor &$processor) {
		if ($this->getName() != $type) {
			return FALSE;
		}
		return feof($fileHandle);
	}

	/**
	 * Retrive the sequence data  for a ggsn file
	 * @param type $type the type of the file being processed
	 * @param type $filename the file name of the file being processed
	 * @param type $processor the processor instace that triggered the fuction
	 * @return boolean
	 */
	public function getFilenameData($type, $filename, &$processor) {
		if ($this->getName() != $type) {
			return FALSE;
		}
		return $this->getFileSequenceData($filename);
	}

	/**
	 * Get specific data from an asn.1 structure  based on configuration
	 * @param type $data the ASN.1 data struture
	 * @param type $config the configuration of the data to retrive.
	 * @return Array an array containing flatten asn.1 data keyed by the configuration.
	 * @todo Merge this  function with the ASNParing  function
	 */
	protected function getASNDataByConfig($data, $config, $fields) {
		$dataArr = Asn_Base::getDataArray($data, true, true);
		$valueArr = array();
		foreach ($config as $key => $val) {
			$tmpVal = $this->parseASNData(explode(',', $val), $dataArr, $fields);
			if ($tmpVal !== FALSE) {
				$valueArr[preg_replace('/_\d$/','',$key)]  = $tmpVal;
			}
		}
		return count($valueArr) ? $valueArr : false;
	}

	/**
	 * convert the actual data we got from the ASN record to a readable information
	 * @param $struct 
	 * @param $asnData the parsed ASN.1 recrod.
	 * @param $fields TODO
	 * @return Array conatining the fields in the ASN record converted to readableformat and keyed by they're use.
	 * @todo Merge this  function with the ASNParing  function
	 */
	protected function parseASNData($struct, $asnData, $fields) {
		$matches = array();
		if (preg_match("/\[(\w+)\]/", $struct[0], $matches) || !is_array($asnData)) {
			$ret = false;
			if (!isset($matches[1]) || !$matches[1] || !isset($fields[$matches[1]])) {
				Billrun_Factory::log()->log(" couldn't digg into : {$struct[0]} struct : " . print_r($struct, 1) . " data : " . print_r($asnData, 1), Zend_Log::DEBUG);
			} else {
				$ret = $this->parseField($fields[$matches[1]], $asnData);
			}
			return $ret;
		}

		foreach ($struct as $val) {
			if (($val == "*" || $val == "+" || $val == "-" || $val == ".")) {  // This is  here to handle cascading  data arrays
				if (isset($asnData[0])) {// Taking as an assumption there will never be a 0 key in the ASN types 
					$newStruct = $struct;
					array_shift($newStruct);
					$sum = null;
					foreach ($asnData as $subData) {
						$sum = $this->doParsingAction($val, $this->parseASNData($newStruct, $subData, $fields), $sum);
					}
					return $sum;
				} else {
					$val = next($struct);
					array_shift($struct);
				}
			}
			if (isset($asnData[$val])) {
				$newStruct = $struct;
				array_shift($newStruct);
				return $this->parseASNData($newStruct, $asnData[$val], $fields);
			}
		}

		return false;
	}

	/**
	 * An hack to ahndle casacing  arrays  of  a given field
	 * @param type $action
	 * @param type $data
	 * @param type $prevVal
	 * @return string
	 */
	protected function doParsingAction($action, $data, $prevVal = null) {
		$ret = $prevVal;
		switch ($action) {
			case "+":
				if ($prevVal == null) {
					$ret = 0;
				}
				$ret += $data;
				break;
			case "-":
				if ($prevVal == null) {
					$ret = 0;
				}
				$ret -= $data;
				break;
			case "*":
				if ($prevVal == null) {
					$ret = array();
				}
				$ret[] = $data;
				break;
			default:
			case ".":
				if ($prevVal == null) {
					$ret = "";
				}
				$ret .= "," . $data;
				break;
		}
		return $ret;
	}

	/**
	 * updates balance of subscriber, breakdown by 3g/4g
	 * @param array $update: the update of the balance in question
	 */
	public function beforeCommitSubscriberBalance(&$row, &$pricingData, &$query, &$update, $arate, $calculator) {
		if ($row['type'] != "ggsn" || !isset($row['rat_type'])) {
			return;
		}
		if (isset($row['rat_type']) && $row['rat_type'] == "06") { //4G
			$group = "4G";
		} else {
			$group = "3G";
		}
		$update['$inc']['balance.groups.' . $group . '.usagev'] = $row['usagev'];
		$update['$inc']['balance.groups.' . $group . '.cost'] = $pricingData['aprice'];
		$update['$inc']['balance.groups.' . $group . '.count'] = 1;
	}
	
	
		/**
	 * Write all the threshold that were broken as events to the db events collection 
	 * @param type $items the broken  thresholds
	 * @param type $pluginName the plugin that identified the threshold breakage
	 * @return type
	 */
	public function handlerAlert(&$items, $pluginName) {
		if ($pluginName != $this->getName() || !$items) {
			return;
		}

		$events =  Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('fraud.db'))->eventsCollection();
		$dateHorizion =date(Billrun_Base::base_dateformat,  strtotime('-6 hours'));
		$ret = array();
		foreach ($items as &$item) {
			$event = new Mongodloid_Entity($item);
			unset($event['lines_stamps']);

			$newEvent = $this->addAlertData($event);
			$newEvent['source'] = 'billing';
			$newEvent['stamp'] = md5(serialize($newEvent));
			$newEvent['creation_time'] = date(Billrun_Base::base_dateformat);
			$newEvent['priority'] = Billrun_Util::getFieldVal($this->fraudConfig[$this->getName()][$event['event_type']]['priority'],10);

			$item['event_stamp'] = $newEvent['stamp'];
			if($events->query([ 'sid'=>$newEvent['sid'],
								'event_type'=>$newEvent['event_type'],
								'creation_time'=>['$gt'=>$dateHorizion],
								'value'=>['$gt'=>$newEvent['value']-$newEvent['threshold']],
								'returned_value.success'=>['$in'=>[true,1]]
								])->cursor()->limit(1)->current()->isEmpty()) {

				$ret[] = $events->save($newEvent);
			}
		}
		return $ret;
	}
	
	/**
	 * method to markdown all the lines that triggered the event
	 * 
	 * @param array $items the lines
	 * @param string $pluginName the plugin name which triggered the event
	 * 
	 * @return array affected lines
	 */
	public function handlerMarkDown(&$items, $pluginName) {
		if ($pluginName != $this->getName() || !$items) {
			return;
		}

		$ret = array();
		return $ret;
	}

}

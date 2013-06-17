<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * This a plguin to provide TAP3 CDRs support to the billing system.
 *
 * @author eran
 */
class tap3Plugin extends Billrun_Plugin_BillrunPluginBase implements Billrun_Plugin_Interface_IParser, Billrun_Plugin_Interface_IProcessor {

	use Billrun_Traits_AsnParsing;

use Billrun_Traits_FileSequenceChecking;

	protected $name = 'tap3';
	protected $nsnConfig = false;

	const FILE_READ_AHEAD_LENGTH = 65535;

	public function __construct(array $options = array()) {
		$this->nsnConfig = (new Yaf_Config_Ini(Billrun_Factory::config()->getConfigValue('tap3.config_path')))->toArray();
		$this->initParsing();
		$this->addParsingMethods();
	}

	/**
	 * back up retrived files that were processed to a third patry path.
	 * @param \Billrun_Processor $processor the processor instace contain the current processed file data. 
	 */
	public function afterProcessorStore(\Billrun_Processor $processor) {
		if ($processor->getType() != $this->getName()) {
			return;
		}
		$path = Billrun_Factory::config()->getConfigValue($this->getName() . '.thirdparty.backup_path', false, 'string');
		if (!$path)
			return;
		if ($processor->retrievedHostname) {
			$path = $path . DIRECTORY_SEPARATOR . $processor->retrievedHostname;
		}
		Billrun_Factory::log()->log("Saving file to third party at : $path", Zend_Log::DEBUG);
		if (!$processor->backupToPath($path, true)) {
			Billrun_Factory::log()->log("Couldn't  save file to third patry path at : $path", Zend_Log::ERR);
		}
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
	 * Check recieved file sequences
	 * @param type $receiver
	 * @param type $filepaths
	 * @param type $hostname
	 * @return type
	 */
	public function afterFTPReceived($receiver, $filepaths, $hostname) {
		if ($receiver->getType() != $this->getName()) {
			return;
		}
		$this->checkFilesSeq($filepaths, $hostname);
	}

	///////////////////////////////////////////////// Parser //////////////////////////////////

	/**
	 * @see Billrun_Plugin_Interface_IParser::parseHeader
	 */
	public function parseHeader($type, $data, \Billrun_Parser &$parser) {
		if ($this->getName() != $type) {
			return FALSE;
		}
		//Billrun_Factory::log()->log("Header data : ". print_r(Asn_Base::getDataArray( $data ,true ),1) ,  Zend_Log::DEBUG);
		$header = $this->parseASNDataRecur($this->nsnConfig['header'], Asn_Base::getDataArray($data, true, true), $this->nsnConfig['fields']);
		$this->currentFileHeader = $header;

		return $header;
	}

	/**
	 * @see Billrun_Plugin_Interface_IParser::parseData
	 */
	public function parseData($type, $data, \Billrun_Parser &$parser) {
		if ($this->getName() != $type) {
			return FALSE;
		}

		$type = $data->getType();
		$cdrLine = false;

		if (isset($this->nsnConfig[$type])) {
			$cdrLine = $this->parseASNDataRecur($this->nsnConfig[$type], Asn_Base::getDataArray($data, true, true), $this->nsnConfig['fields']);
			if ($cdrLine) {
				$cdrLine['record_type'] = $type;

				$this->surfaceCDRFields($cdrLine);
			}
		} else {
			//Billrun_Factory::log()->log("Unidetifiyed type :  $type",Zend_Log::DEBUG);
		}

		return $cdrLine;
	}

	/**
	 * @see Billrun_Plugin_Interface_IParser::parseSingleField
	 */
	public function parseSingleField($type, $data, array $fieldDesc, \Billrun_Parser &$parser) {
		if ($this->getName() != $type) {
			return FALSE;
		}
		$parsedData = Asn_Base::parseASNString($data);
		//	Billrun_Factory::log()->log(print_r(Asn_Base::getDataArray($parsedData),1),  Zend_Log::DEBUG);
		return $this->parseField($parsedData, $fieldDesc);
	}

	/**
	 * @see Billrun_Plugin_Interface_IParser::parseTrailer
	 */
	public function parseTrailer($type, $data, \Billrun_Parser &$parser) {
		if ($this->getName() != $type) {
			return FALSE;
		}

		$trailer = $this->parseASNDataRecur($this->nsnConfig['trailer'], Asn_Base::getDataArray($data, true), $this->nsnConfig['fields']);
		//Billrun_Factory::log()->log(print_r($trailer,1),  Zend_Log::DEBUG);

		return $trailer;
	}

	/**
	 * Pull required fields from the CDR nested tree to the surface.
	 * @param type $cdrLine the line to monipulate.
	 */
	protected function surfaceCDRFields(&$cdrLine) {
		if (isset($cdrLine['basicCallInformation']['CallEventStartTimeStamp']['localTimeStamp'])) {
			$offset = $this->currentFileHeader['networkInfo']['UtcTimeOffsetInfoList'][$cdrLine['basicCallInformation']['CallEventStartTimeStamp']['TimeOffsetCode']];
			$cdrLine['unified_record_time'] = new MongoDate(Billrun_Util::dateTimeConvertShortToIso($cdrLine['basicCallInformation']['CallEventStartTimeStamp']['localTimeStamp'], $offset));
		}

		if (isset($cdrLine['basicCallInformation']['chargeableSubscriber']['simChargeableSubscriber']['imsi'])) {
			$cdrLine['imsi'] = $cdrLine['basicCallInformation']['chargeableSubscriber']['simChargeableSubscriber']['imsi'];
		}

		if (isset($cdrLine['basicCallInformation']['GprsChargeableSubscriber']['chargeableSubscriber']['simChargeableSubscriber']['imsi'])) {
			$cdrLine['imsi'] = $cdrLine['basicCallInformation']['GprsChargeableSubscriber']['chargeableSubscriber']['simChargeableSubscriber']['imsi'];
		}

		if (isset($cdrLine['basicCallInformation']['chargeableSubscriber']['simChargeableSubscriber']['msisdn'])) {
			$cdrLine['calling_number'] = $cdrLine['basicCallInformation']['chargeableSubscriber']['simChargeableSubscriber']['msisdn'];
		}

		if (isset($cdrLine['basicCallInformation']['GprsChargeableSubscriber']['chargeableSubscriber']['simChargeableSubscriber']['msisdn'])) {
			$cdrLine['calling_number'] = $cdrLine['basicCallInformation']['GprsChargeableSubscriber']['chargeableSubscriber']['simChargeableSubscriber']['msisdn'];
		}

		if (isset($cdrLine['LocationInformation']['GeographicalLocation']['ServingNetwork'])) {
			$cdrLine['serving_network'] = $cdrLine['LocationInformation']['GeographicalLocation']['ServingNetwork'];
		} else {
			$cdrLine['serving_network'] = $this->currentFileHeader['header']['sending_source'];
		}
	}

	/**
	 * add GGSN specific parsing methods.
	 */
	protected function addParsingMethods() {
		$newParsingMethods = array(
			'raw_data' => function($data) {
				return $this->utf8encodeArr($data);
			},
			'bcd_number' => function($fieldData) {
				$ret = $this->parsingMethods['bcd_encode']($fieldData);

				return preg_replace('/15$/', '', $ret);
			},
			'time_offset_list' => function($data) {
				return $this->parseTimeOffsetList($data);
			},
		);

		$this->parsingMethods = array_merge($this->parsingMethods, $newParsingMethods);
	}

	/**
	 * Parse time offset list that  conatin the time offset  refecenced in each line  cal start time
	 * @param type $data the  time offset list
	 * @return rray  containing the time offset list  keyed by its offset code.
	 */
	protected function parseTimeOffsetList($data) {
		$timeOffsets = array();
		if (isset($data['e9']['e8'])) {
			$data['e9'] = array($data['e9']);
		}
		foreach ($data['e9'] as $value) {
			$key = $this->parseField('number', $value['e8']);
			$timeOffsets[$key] = $value['e7'];
		}
		return $timeOffsets;
	}

	/////////////////////////////////////////// Processor /////////////////////////////////////////
	/**
	 * @see Billrun_Plugin_Interface_IProcessor::processData
	 */
	public function processData($type, $fileHandle, \Billrun_Processor &$processor) {
		if ($this->getName() != $type) {
			return FALSE;
		}

		$processorData = &$processor->getData();
		$bytes = '';
		do {
			$bytes .= fread($fileHandle, self::FILE_READ_AHEAD_LENGTH);
		} while (!feof($fileHandle));
		$parsedData = Asn_Base::parseASNString($bytes);
		$processorData['header'] = $processor->buildHeader($parsedData);
		//$bytes = substr($bytes, $processor->getParser()->getLastParseLength());

		foreach ($parsedData->getData() as $record) {
			if (in_array($record->getType(), $this->nsnConfig['config']['data_records'])) {
				foreach ($record->getData() as $key => $data) {
					$row = $processor->buildDataRow($data);
					if ($row) {
						$processorData['data'][] = $row;
					}
				}
			} else {
				//Billrun_Factory::log()->log(print_r($record,1) ,  Zend_Log::DEBUG);
			}
		}

		$processorData['trailer'] = $processor->buildTrailer($parsedData);

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

}

?>

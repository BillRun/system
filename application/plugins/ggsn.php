<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a plguin to provide GGSN support to the billing system.
 */
class ggsnPlugin extends Billrun_Plugin_Base implements Billrun_Plugin_Interface_IParser, Billrun_Plugin_Interface_IProcessor {

	use Billrun_Traits_AsnParsing,
	 Billrun_Traits_FileSequenceChecking;

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'ggsn';

	public function __construct(array $options = array()) {
		$this->ggsnConfig = (new Yaf_Config_Ini(Billrun_Factory::config()->getConfigValue('external_parsers_config.ggsn')))->toArray();
		$this->initParsing();
		$this->addParsingMethods();
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
				Billrun_Factory::log("Couldn't save file $filePath to third patry path at : $path", Zend_Log::ERR);
			}
		}
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
							Billrun_Factory::log($diag . " : " . $diagnostics[$diag], Zend_Log::DEBUG);
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
				//Billrun_Factory::log($data. " : ". print_r($smode,1),Zend_Log::DEBUG );
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
		$maxChunklengthLength = Billrun_Factory::config()->getConfigValue('constants.ggsn_max_chunklength_length');
		$fileReadAheadLength = Billrun_Factory::config()->getConfigValue('constants.ggsn_file_read_ahead_length');
		$headerLength = Billrun_Factory::config()->getConfigValue('constants.ggsn_header_length');
		if ($headerLength > 0) {
			$processedData['header'] = $processor->buildHeader(fread($fileHandle, $headerLength));
		}
		$bytes = null;
		while (true) {
			if (!feof($fileHandle) && !isset($bytes[$maxChunklengthLength])) {
				$bytes .= fread($fileHandle, $fileReadAheadLength);
			}
			if (!isset($bytes[$headerLength])) {
				break;
			}
			$row = $processor->buildDataRow($bytes, $fileHandle);
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
	 * updates balance of subscriber, breakdown by 3g/4g
	 * @param array $update: the update of the balance in question
	 */
	public function beforeCommitSubscriberBalance(&$row, &$pricingData, &$query, &$update, $arate, $calculator, $balanceToUpdate) {
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
	 * @see Billrun_Processor::getLineVolume
	 */
	protected function getLineVolume($cdrLine) {
		$fbc_uplink_volume = $fbc_downlink_volume = 0;
		if (isset($cdrLine['rating_group'])) {
			$fbc_uplink_volume = is_array($cdrLine['fbc_uplink_volume']) ? array_sum($cdrLine['fbc_uplink_volume']) : $cdrLine['fbc_uplink_volume'];
			$fbc_downlink_volume = is_array($cdrLine['fbc_downlink_volume']) ? array_sum($cdrLine['fbc_downlink_volume']) : $cdrLine['fbc_downlink_volume'];
		}
		return $fbc_uplink_volume + $fbc_downlink_volume;
	}

	/**
	 * @see Billrun_Processor::getLineUsageType
	 */
	protected function getLineUsageType($row) {
		return 'data';
	}
	
	public function parseData($type, $data, \Billrun_Parser &$parser) {
		return;
	}


}

<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This defines an empty parser that does nothing but passing behavior to the outside plugins
 */
class Billrun_Parser_Ggsn extends Billrun_Parser_Base_Binary {

	use Billrun_Traits_AsnParsing;

	public function __construct($options) {
		parent::__construct($options);
		$this->ggsnConfig = (new Yaf_Config_Ini(Billrun_Factory::config()->getConfigValue('external_parsers_config.ggsn')))->toArray();
		$this->initParsing();
		$this->addParsingMethods();
	}
	
	public function parse($fp) {
		$this->dataRows = array();
		$this->headerRows = array();
		$this->trailerRows = array();

		$maxChunklengthLength = intval(Billrun_Util::getIn($this->ggsnConfig, 'constants.ggsn_max_chunklength_length', 0));
		$fileReadAheadLength = intval(Billrun_Util::getIn($this->ggsnConfig, 'constants.ggsn_file_read_ahead_length', 0));
		$headerLength = intval(Billrun_Util::getIn($this->ggsnConfig, 'constants.ggsn_header_length', 0));
		if ($headerLength > 0) {
			$this->headerRows[] = $this->parseHeader(fread($fp, $headerLength));
		}
		$bytes = null;
		while (true) {
			if (!feof($fp) && !isset($bytes[$maxChunklengthLength])) {
				$bytes .= fread($fp, $fileReadAheadLength);
			}
			if (!isset($bytes[$headerLength])) {
				break;
			}
			
			$this->setLine($bytes);
			$rawRow = $this->parseData('ggsn', $this->getLine($fp));
					
			if ($rawRow) {
				$this->dataRows[] = $rawRow;
				$processedData['data'][] = $rawRow;
			}
			//Billrun_Factory::log()->log( $processor->getParser()->getLastParseLength(),  Zend_Log::DEBUG);
			$advance = $this->getLastParseLength();
			$bytes = substr($bytes, $advance <= 0 ? 1 : $advance);
		}
		$this->trailerRows[] = $this->parseTrailer($bytes);

		return true;
	}
	
	public function parseData($type, $data) {
		$asnObject = Asn_Base::parseASNString($data);
		$recordPadding = Billrun_Factory::config()->getConfigValue('constants.ggsn_record_padding');
		$this->setLastParseLength($asnObject->getRawDataLength() + $recordPadding);

		$type = $asnObject->getType();
		$cdrLine = false;

		if (isset($this->ggsnConfig[$type])) {
			$cdrLine = $this->getASNDataByConfig($asnObject, $this->ggsnConfig[$type], $this->ggsnConfig['fields']);
			if ($cdrLine && !isset($cdrLine['record_type'])) {
				$cdrLine['record_type'] = $type;
			}
			//convert to unified time GMT time.
			if (!empty(Billrun_Factory::config()->getConfigValue('constants.handle_multiple_volume',TRUE))) {
				$cdrLine = $this->handleMultipleVolume($cdrLine);
				if ($cdrLine == false) {
					return false;
				}
			}
			$cdrLine['ggsn_type'] = $type;
		} else {
			Billrun_Factory::log("couldn't find definition for {$type}", Zend_Log::INFO);
		}
		
		//Billrun_Factory::log($asnObject->getType() . " : " . print_r($cdrLine,1) ,  Zend_Log::DEBUG);
		return $cdrLine;
	}

	/**
	 * Set the amount of bytes that were parsed on the last parsing run.
	 * @param $parsedBytes	Containing the count of the bytes that were processed/parsed.
	 */
	public function setLastParseLength($parsedBytes) {
		$this->parsedBytes = $parsedBytes;
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
			if (!is_null($tmpVal)) {
				$valueArr[$key] = $tmpVal;
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
			$ret = null;
			if (!isset($matches[1]) || !$matches[1] || !isset($fields[$matches[1]])) {
			//	Billrun_Factory::log()->log("Couldn't digg into : {$struct[0]} struct : " . print_r($struct, 1) . " data : " . print_r($asnData, 1), Zend_Log::DEBUG);
			} else {
				$ret = $this->parseField($fields[$matches[1]], $asnData);
			}
			return $ret;
		}

		foreach ($struct as $val) {
			if (($val == "*" || $val == "**" || $val == "+" || $val == "-" || $val == ".")) {  // This is  here to handle cascading  data arrays
				if (isset($asnData[0]) && is_array($asnData) && array_keys($asnData) == range(0, count($asnData)-1) ) {// Taking as an assumption there will never be a 0 key in the ASN types 
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
				if( preg_match("/\[(\w+)\]/", $newStruct[0]) || is_array($asnData[$val]) ) {
					return $this->parseASNData($newStruct, $asnData[$val], $fields);
				}
			}
		}

		return null;
	}
	
	protected function handleMultipleVolume($cdrLine) {
		if (isset($cdrLine['rating_group']) && is_array($cdrLine['rating_group'])) {
			$fbc_uplink_volume = $fbc_downlink_volume = 0;
			$cdrLine['org_fbc_uplink_volume'] = $cdrLine['fbc_uplink_volume'];
			$cdrLine['org_fbc_downlink_volume'] = $cdrLine['fbc_downlink_volume'];
			$cdrLine['org_rating_group'] = $cdrLine['rating_group'];
			foreach ($cdrLine['rating_group'] as $key => $rateVal) {
				if (!empty($this->ggsnConfig['rating_groups'][$rateVal])) {
					$fbc_uplink_volume += $cdrLine['fbc_uplink_volume'][$key];
					$fbc_downlink_volume += $cdrLine['fbc_downlink_volume'][$key];
				}
			}
			$cdrLine['fbc_uplink_volume'] = $fbc_uplink_volume;
			$cdrLine['fbc_downlink_volume'] = $fbc_downlink_volume;
			$cdrLine['rating_group'] = 0;
		} else if (isset($cdrLine['rating_group']) && $cdrLine['rating_group'] == 10) {
			return false;
		} else {
			if(is_array($cdrLine['fbc_uplink_volume'])) {
				$cdrLine['org_fbc_uplink_volume'] = $cdrLine['fbc_uplink_volume'];
				$cdrLine['fbc_uplink_volume'] = array_sum($cdrLine['fbc_uplink_volume']);
			}
			if(is_array($cdrLine['fbc_downlink_volume'])) {
				$cdrLine['org_fbc_downlink_volume'] = $cdrLine['fbc_downlink_volume'];
				$cdrLine['fbc_downlink_volume'] = array_sum($cdrLine['fbc_downlink_volume']);
			}
		} 
		
		
		return $cdrLine;
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
		if (is_null($data)) {
			return $ret;
		}
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
			case "**":
				if ($prevVal == null) {
					$ret = array();
				}
				$ret = array_merge($ret, $data);
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

	public function parseHeader($data) {
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

	public function parseTrailer($data) {
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
			'multi_ip' => function($fieldData) {

				return is_array($fieldData) ? array_map(function($data) {
							return implode('.', unpack('C*', $data));
						}, $fieldData) : array(implode('.', unpack('C*', $fieldData)));
			},
			);

		$this->parsingMethods = array_merge($this->parsingMethods, $newParsingMethods);
	}

}

?>

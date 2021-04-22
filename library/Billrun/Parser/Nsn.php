<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 5; see LICENSE.txt
 */

/**
 * This defines an empty parser that does nothing but passing behavior to the outside plugins
 */
class Billrun_Parser_Nsn extends Billrun_Parser_Base_Binary {
	
	use Billrun_Traits_AsnParsing;

	static protected $type = "nsn";
 
	public function __construct($options) {
		parent::__construct($options);
		$this->nsnConfig = (new Yaf_Config_Ini(Billrun_Factory::config()->getConfigValue('external_parsers_config.nsn')))->toArray();
		$this->ild_called_number_regex = Billrun_Factory::config()->getConfigValue('016_one_way.identifications.called_number_regex');
		$this->initParsing();
	}

	public function parse($fp) {
		$data = array();
		$offset = 0;
		$line = $this->getLine($fp);
		
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
					Billrun_Factory::log("Parser received line (cf : " . $data['call_reference'] . " , cft : " . $data['call_reference_time'] . " ) with computed duration of $computed_dur using orginal duration field : {$data['duration']} ", Zend_Log::ALERT);
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

		$this->setLastParseLength($data['record_length']);

		//@TODO add unifiom field translation. ('record_opening_time',etc...)
		return isset($this->nsnConfig[$data['record_type']]) ? $data : false;
	}
	
	/**
	 * parse a field from raw data based on a field description
	 * @param string $data the raw data to be parsed.
	 * @param array $fileDesc the field description
	 * @return mixed the parsed value from the field.
	 */
	public function parseField($data, $fileDesc) {
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
					$retValue .= dechex(ord($data[$i]));
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

	public function parseHeader($data) {
		$header = array();
		foreach ($this->nsnConfig['block_header'] as $key => $fieldDesc) {
			$fieldStruct = $this->nsnConfig['fields'][$fieldDesc];
			$header[$key] = $this->parseField($data, $fieldStruct);
			$data = substr($data, current($fieldStruct));
			//Billrun_Factory::log()->log("Header $key : {$header[$key]}",Zend_log::DEBUG);
		}

		return $header;
	}

	public function parseTrailer($data) {
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
	 * Set the amount of bytes that were parsed on the last parsing run.
	 * @param $parsedBytes	Containing the count of the bytes that were processed/parsed.
	 */
	public function setLastParseLength($data) {
		$this->parsedBytes = $data['record_length'];
	}

}
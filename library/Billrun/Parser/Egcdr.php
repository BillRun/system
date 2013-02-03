<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing parser class for binary size
 *
 * @package  Billing
 * @since    1.0
 * @todo should make first derivative parser text and then fixed parser will inherited text parser
 */
class Billrun_Parser_Egcdr extends Billrun_Parser_Base_Binary {

	const TYPE_PREFIX = '0x'; 
	
	public function __construct($options) {

		parent::__construct($options);

		//create the parsing structure.
		$this->data_structure = $this->buildDataMap();
	}

	/**
	 * general function to parse
	 *
	 * @return mixed
	 */
	public function parse() {

		$asnObject = Asn_Base::parseASNString($this->getLine());
		$this->parsedBytes = $asnObject->getDataLength();
		//$dataArr = Asn_Base::getDataArray($asnObject);
		$ret = $this->parseASNData($this->data_structure, $asnObject);
		return $ret;
	}

	/**
	 * Get the amount of bytes that were parsed on the last parsing run.
	 * @return int	 containing the count of the bytes that were processed/parsed.
	 */
	public function getLastParseLength() {
		return parent::getLastParseLength() + 8;
	}

	protected function calcStructureSign($structure) {
		$sign = "";
		foreach ($structure as $key => $val) {
			$sign .= $key . (is_array($val) ? $this->calcStructureSign($val) : "");
		}
		return $sign;
	}

	/**
	 * convert the actual data we got from the ASN record to a readable information
	 * @param $asnData the parsed ASN.1 recrod.
	 * @return Array conatining the fields in the ASN record converted to readableformat and keyed by they're use.
	 */
	protected function parseASNData($struct, $asnData) {
		$retArr = array();
		
		foreach ($asnData->getData() as $key => $val) {
			if (isset($struct[$key])) {
				$type = static::TYPE_PREFIX . $val->getType();
				if (is_array($val->getData())) {
					//$this->log->log(" digging into : $key", Zend_Log::DEBUG);
					$retArr = array_merge($retArr, $this->parseASNData($struct[$key], $val));
				} else if (isset($struct[$key][$type]) && isset($this->fields[$struct[$key][$type]])) {
					$field = $struct[$key][$type];
					$retArr[$field] = $this->parseField($this->fields[$field], $val);
				} else {
					$this->log->log("Couldn`t find field for : $key with type :$type", Zend_Log::DEBUG);
					$this->log->log("Structure is : " . print_r($struct, 1), Zend_Log::DEBUG);
				}
			} else {
				$this->log->log("Couldn`t find field for : $key with value :" . print_r($val, 1), Zend_Log::DEBUG);
				$this->log->log("Structure is : " . print_r($struct, 1), Zend_Log::DEBUG);
			}
		}

		return $retArr;
	}

	/**
	 * Parse an ASN field using a specific data structure.
	 */
	protected function parseField($type, $fieldData) {
		//if ($type != 'debug') {
		$fieldData = $fieldData->getData();
		//}/*///TODO remove
		if (isset($fieldData)) {
			switch ($type) {
				//TODO remove
			/*	case 'debug':
					$fieldType = $fieldData->getType();
					$fieldClass = get_class($fieldData);
					$fieldData = $fieldData->getData();
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
					$fieldData = "DEBUG : " . $fieldClass . " | " . $fieldType . " | " . $numData . " | " . $tempData . " | " . implode(unpack("H*", $fieldData)) . " | " . implode(unpack("C*", $fieldData)) . " | " . $fieldData;
					break;
*/
				case 'string':
					$fieldData = utf8_encode($fieldData);
					break;

				case 'long':
					$numarr = unpack('C*', $fieldData);
					$fieldData = 0;
					foreach ($numarr as $byte) {
						//$fieldData = $fieldData <<8;
						$fieldData = bcadd(bcmul($fieldData , 256 ), $byte);
					}
					break;

				case 'number':
					$numarr = unpack('C*', $fieldData);
					$fieldData = 0;
					foreach ($numarr as $byte) {
						//$fieldData = $fieldData <<8;
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

				default:
					$fieldData = is_array($fieldData) ? '' : implode('', unpack($type, $fieldData));
			}
		}
		return $fieldData;
	}

	/**
	 * Encode an array content in utf encoding
	 * @param $arr the array to encode.
	 * @return array with a recurcivly encoded values.
	 */
	protected function utf8encodeArr($arr) {
		foreach ($arr as &$val) {
			$val = is_array($val) ? $this->utf8encodeArr($val) : utf8_encode($val);
		}
		return $arr;
	}

	/**
	 * HACK this is an hack to solve the problem that not all the data fields are known.
	 */
	protected function buildDataMap() {
		//create the data structure  the praser will use.
		$losdArr = array(
			'0x1' => 'losd_unknown',
			'0x0' => 'losd_unknown1',
			0 => array('0x81' => 'rating_group'),
			1 => array('0x84' => 'losd_local_seq_num'),
			2 => array('0x85' => 'first_usage'),
			3 => array('0x86' => 'last_usage'),
			4 => array('0x87' => 'time_usage'),
			5 => array('0x88' => 'service_condition_change'),
			6 => array('0x89' => 'qos_info'),
			7 => array(0 => array('0x80' => 'lsod_sgsn_address')),
			8 => array('0x8b' => 'losd_sgsn_plmn_id'),
			9 => array('0x8c' => 'fbc_uplink_volume'),
			10 => array('0x8d' => 'fbc_downlink_volume'),
			11 => array('0x8d' => 'fbc_downlink_volume', '0x8e' => 'time_of_report'),
			12 => array('0x8e' => 'time_of_report',
				'0x8f' => 'losd_rat_type',
				'0x91' => 'failure_handle_continue'),
			13 => array('0x91' => 'failure_handle_continue', '0x92' => 'v'),
			14 => array('0x92' => 'service_id',
				'0x93' => 'user_location_information',
				'0x94' => 'user_location_information'),
			15 => array('0x93' => 'user_location_information', '0x94' => 'user_location_information'),
		);
		$data_structure = array(
			0 => array('0x80' => 'record_type'),
			1 => array('0x83' => 'served_imsi'),
			2 => array(0 => array('0x80' => 'ggsn_address')),
			3 => array('0x85' => 'charging_id'),
			4 => array(0 => array('0x80' => 'sgsn_address')),
			5 => array('0x87' => 'apnni'),
			6 => array('0x88' => 'pdp_type'),
			7 => array(0 => array(0 => array('0x80' => 'served_pdp_address'))),
			8 => array('0x8b' => 'dynamic_address_flag'),
			9 => array('0x8d' => 'record_opening_time'),
			10 => array('0x8e' => 'duration'),
			11 => array('0x8f' => 'cause_for_record_closing'),
			12 => array('0x91' => 'record_sequence_number',
				0 => array('0x80' => 'diagnostics')),
			13 => array('0x92' => 'node_id', '0x91' => 'record_sequence_number',),
			14 => array('0x94' => 'local_sequence_number', '0x92' => 'node_id'),
			15 => array('0x94' => 'local_sequence_number', '0x95' => 'apn_selection_mode'),
			16 => array('0x95' => 'apn_selection_mode', '0x96' => 'served_msisdn'),
			17 => array('0x96' => 'served_msisdn', '0x97' => 'charging_characteristics'),
			18 => array('0x97' => 'charging_characteristics', '0x98' => 'charging_characteristics_selection_mode',
				0 => array(0 => array('0xfield' => 'charging_characteristics_selection_mode'))),
			19 => array('0x98' => 'charging_characteristics_selection_mode', '0x9b' => 'sgsn_plmn_id'),
			20 => array('0x9b' => 'sgsn_plmn_id',
				'0x9d' => 'served_imeisv',
				0 => $losdArr,
				1 => $losdArr,
			),
			21 => array('0x9d' => 'served_imeisv',
				'0x9e' => 'rat_type',
				0 => $losdArr,
			),
			22 => array('0x9e' => 'rat_type',
				'0x1f' => 'ms_timezone',
				0 => $losdArr,
			),
			23 => array('0x1f' => 'ms_timezone',
				0 => $losdArr,
				1 => array("0x81" => 'unkonwn'),
			),
			24 => array(0 => $losdArr, 1 => $losdArr,),
			25 => array(0 => $losdArr, 1 => $losdArr,),
		);

		//set the fields and how we translate them.
		$this->fields = array(
			'rating_group' => 'number',
			'losd_local_seq_num' => 'number',
			'first_usage' => 'datetime',
			'last_usage' => 'datetime',
			'time_usage' => 'number',
			'service_condition_change' => 'H*',
			'qos_info' => 'H*',
			'sgsn_address' => 'ip',
			'sgsn_plmn_id' => 'number',
			'fbc_uplink_volume' => 'number',
			'fbc_downlink_volume' => 'number',
			'time_of_report' => 'datetime',
			'rat_type' => 'number',
			'lsod_rat_type' => 'number',
			'failure_handle_continue' => 'number',
			'service_id' => 'BCDencode',
			'record_type' => 'C',
			'served_imsi' => 'BCDencode',
			'ggsn_address' => 'ip',
			'charging_id' => 'long',
			'sgsn_address' => 'ip',
			'lsod_sgsn_address' => 'ip',
			'apnni' => 'string',
			'pdp_type' => 'C',
			'served_pdp_address' => 'ip',
			'dynamic_address_flag' => 'C',
			'record_opening_time' => 'datetime',
			'duration' => 'number',
			'cause_for_record_closing' => 'C',
			'diagnostics' => 'number',
			'record_sequence_number' => 'number',
			'node_id' => 'string',
			'local_sequence_number' => 'number',
			'apn_selection_mode' => 'C*',
			'served_msisdn' => 'BCDencode',
			'charging_characteristics' => 'H*',
			'charging_characteristics_selection_mode' => 'H*',
			'sgsn_plmn_id' => 'number',
			'losd_sgsn_plmn_id' => 'number',
			'served_imeisv' => 'BCDencode',
			'rat_type' => 'H*',
			'losd_rat_type' => 'H*',
			'ms_timezone' => 'C*',
			'user_location_information' => 'H*',
			'list_of_service_data' => 'losd',
			'record_extensions' => 'json',
			//TODO solve later...
			'unknown' => 'H*',
			'losd_unknown' => 'H*',
			'losd_unknown1' => 'H*',
			//FOR DEBUGGING
//			'debug_losd' => 'debug',
//			'debug' => 'debug',
		);

		return $data_structure;
	}

}

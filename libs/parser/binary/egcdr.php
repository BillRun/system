<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing parser class for binary size
 *
 * @package  Billing
 * @since    1.0
 * @todo should make first derivative parser text and then fixed parser will inherited text parser
 */
class parser_binary_egcdr extends parser_binary {

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
		$asnObject = ASN::parseASNString($this->getLine());
		$this->parsedBytes = ASN::$parsedLength;
		//print_r($asnObject[0]);
		//print($this->calcStructureSign($asnObject[0])."\n");
		$ret =$this->parseASNData($asnObject[0]);
		//if(!isset($ret['diagnostics']) || ( $ret['diagnostics'] > 105 || $ret['diagnostics'] < 0)) {
	//		print_r($ret);
			//print($this->calcStructureSign($asnObject[0])."\n");
	//	}
		return $ret;
	}


	/**
	 * Get the amount of bytes that were parsed on the last parsing run.
	 * @return int	 containing the count of the bytes that were processed/parsed.
	 */
	public function getLastParseLength() {
		return parent::getLastParseLength() +3;
	}

	protected function calcStructureSign($structure) {
		$sign = "";
		foreach($structure as $key => $val) {
			$sign .=  $key . (is_array($val) ?  $this->calcStructureSign($val) : "");
		}
		return $sign;
	}

	/**
	 * convert the actual data we got from the ASN record to a readable information
	 * @param $asnData the parsed ASN.1 recrod.
	 * @return Array conatining the fields in the ASN record converted to readableformat and keyed by they're use.
	 */
	protected function parseASNData($asnData) {
		$retArr= array();
		$struct = array_merge(	$this->data_structure['base'],
					$this->data_structure[$this->calcStructureSign($asnData)] );
		foreach($struct as $key => $val) {
			if($val) {
				$retArr[$key] = $this->parseField($val,$asnData);
			}
			//print("$key : {$tempData}\n");
		}
		return $retArr;
	}

	/**
	 * Parse an ASN field using a specific data structure.
	 */
	protected function parseField($fieldMap,$asnData) {
		foreach($fieldMap as $type => $pos) {
			$tempData = $asnData;
			foreach($pos as $depth) {
				if( isset($tempData[$depth])) {
					$tempData = $tempData[$depth];
				}
			}
			if(isset($tempData) ) {
				switch($type) {

					case 'string':
						$tempData = utf8_encode($tempData);
						break;
					case 'number':
						$numarr = unpack("C*",$tempData);
						$tempData =0;
						foreach($numarr as $byte) {
							//$tempData = $tempData <<8;
							$tempData =  ($tempData << 8 )+ $byte;
						}
						break;
					case 'BCDencode' :
						$halfBytes = unpack("C*",$tempData);
						$tempData ="";
						foreach($halfBytes as $byte) {
							//$tempData = $tempData <<8;
							$tempData .= ($byte & 0xF) . ((($byte >>4) < 10) ? ($byte >>4) : "" ) ;
						}
						break;
					case 'ip' :
						$tempData = implode(".",unpack("C*",$tempData));
						break;
					case 'datetime' :
						$tempTime = DateTime::createFromFormat("ymdHisT",str_replace("2b","+",implode(unpack("H*",$tempData))) );
						$tempData = is_object($tempTime) ?  $tempTime->format("H:i:s d/m/Y T") : "";
						break;
					case 'json' :

						$tempData = json_encode($this->utf8encodeArr($tempData));
						break;
					case 'l' :
						$tempData = $this->parseL($tempData);
						break;
					default:
						$tempData = is_array($tempData) ? "" : implode("",unpack($type,$tempData));
				}
			}
		}
		return $tempData;

	}

	/**
	 * TODO refractor this function should not exists!!!!s
	 */
	protected function parseL($asnData) {
		$retArr= array();
//		print_r($asnData);
		foreach($this->data_structure['list'] as $key => $val) {
			if($val) {
				$retArr[$key] = $this->parseField($val,$asnData);
			}
		}
		print_r($retArr);
		return $retArr;
	}

	protected function utf8encodeArr($arr) {
		foreach($arr as &$val) {
		    $val = is_array($val) ? $this->utf8encodeArr($val) : utf8_encode($val);
		}
		return $arr;
	}
	/**
	 * HACK this is anhack to solve the problem  that the
	 */
	protected function buildDataMap() {
		$data_structure = array(
			'list' => array(
				array('number' => array(0,0)),
				array('number' => array(0,1)),
				'first_usage' => array('datetime' => array(0,2)),
				'last_usage' => array('datetime' => array(0,3)),
				'time_usage' => array('number' => array(0,4)),
				'service_condition_change' => array('H*' => array(0,5)),
				'qos_info' => array('H*' => array(0,6)),
				'sgsn_address' => array('ip' => array(0,7,0)),
				'sgsn_plmn_id' => array('number' => array(0,8)),
				'fbc_uplink_volume' => array('number' => array(0,9)),
				'fbc_downlink_volume' => array('number' => array(0,10)),
				'time_of_report' => array('datetime' => array(0,11)),
				'rat_type' => array('number' => array(0,12)),
				'failure_handle_continue' => array('number' => array(0,13,0)),
				'service_id' => array('BCDencode' => array(0,14)),
			),
			'base' => array(
				'record_type' => array('C' => array(0)),
				'served_imsi' => array('BCDencode'=> array(1)),
				'ggsn_address' =>array('ip' => array(2,0)),
				'charging_id' => array('number' => array(3)),
				'sgsn_address' =>array('ip' => array(4,0)),
				'apnni' => array('string'=> array(5)),
				'pdp_type' => array('C' => array(6)),
				'served_pdp_address' => array('ip'=> array(7,0,0)),
				'dynamic_address_flag' => array('C'=> array(8)),
				'record_opening_time' => array('datetime'=> array(9)),
				'duration' => array('number'=> array(10)),
				'cause_for_record_closing' => array('C'=> array(11)),
				'diagnostics' => array('C*'=> array(12,0)),
				'node_id' => array('string'=> array(13)),
				'local_sequence_number' => array('number'=> array(14)),
				'apn_selection_mode' => array('C*'=> array(15)),
				'served_msisdn'	=>	array('BCDencode'=> array(16)),
				'charging_characteristics'	=>	array('C*'=> array(17)),
				'charging_characteristics_selection_mode'	=>	array('C*'=> array(18,0,0)),
				'sgsn_plmn_id'	=>	array('number'=> array(19)),
				'served_imeisv'	=>	array('BCDencode'=> array(20)),
				'rat_type'	=>	array('H*'=> array(21)),
				'ms_timezone'	=>	array('C*'=> array(22,0)),
				'user_location_information'	=>	array('C*'=> array(23)),
				'list_of_service_data'	=>	array('l'=> array(24)),
				'record_extensions'	=>	null,
			),
			'0120340567008910111201314151617181920001234567089101112'=> array(
					'served_imeisv'	=>null,
					'rat_type'	=>null,
					'ms_timezone'	=>null,
					'user_location_information'	=>null,
					'list_of_service_data'	=>array('l'=> array(20)),
				),
			'01203405670089101112131415161718192021220230240012345670891011121314'=> array(
					//'diagnostics' => null,
					//'record_sequence_number' => array('C'=> array(12)),
					'user_location_information'	=>	array('C*'=> array(23,0)),
				),
			'0120340567008910111213141516171819202122023240012345670891011121314'=> array(
					//'diagnostics' => null,
					//'record_sequence_number' => array('C*'=> array(12)),
				),
			'012034056700891011120131415161718192021220230240012345670891011121314'=> array(

				),
			'01203405670089101112013141516171819202122230240250012345670891011121314'=> array(
					'record_sequence_number' => array('L*'=> array(13)),
					'node_id' => array('string'=> array(14)),
					'local_sequence_number' => array('number'=> array(15)),
					'apn_selection_mode' => array('C*'=> array(16)),
					'served_msisdn'	=>	array('C*'=> array(17)),
					'charging_characteristics'	=>	array('C*'=> array(18)),
					'charging_characteristics_selection_mode'	=>	array('C*'=> array(19)),
					'sgsn_plmn_id'	=>	array('number'=> array(20)),
					'served_imeisv'	=>	array('BCDencode'=> array(21)),
					'rat_type'	=>	array('H*'=> array(22)),
					'ms_timezone'	=>	array('C*'=> array(23,0)),
					'user_location_information'	=>	array('C*'=> array(24,0)),
					'list_of_service_data'	=>	array('l'=> array(25)),
				),
			'0120340567008910111201314151617181920212223024250012345670891011121314'=> array(
					'record_sequence_number' => array('L*'=> array(13)),
					'node_id' => array('string'=> array(14)),
					'local_sequence_number' => array('number'=> array(15)),
					'apn_selection_mode' => array('C*'=> array(16)),
					'served_msisdn'	=>	array('C*'=> array(17)),
					'charging_characteristics'	=>	array('C*'=> array(18)),
					'charging_characteristics_selection_mode'	=>	array('C*'=> array(19)),
					'sgsn_plmn_id'	=>	array('number'=> array(20)),
					'served_imeisv'	=>	array('BCDencode'=> array(21)),
					'rat_type'	=>	array('H*'=> array(22)),
					'ms_timezone'	=>	array('C*'=> array(23,0)),
					'user_location_information'	=>	array('c*'=> array(24)),
					'list_of_service_data'	=>	array('l'=> array(25)),
				),
			'01203405670089101112013141516171819202122023240012345670891011121314'=> array(
				),
			'012034056700891011121314151617181920001234567089101112'=> array(
					'served_imeisv'	=>	null,
					'rat_type'	=>	null,
					'ms_timezone'	=>	null,
					'user_location_information'	=>	null,
					'list_of_service_data'	=>	array('l'=> array(20)),
					//'record_extensions'	=>	array('json'=> array(20)),
				),
			'01203405670089101112131415161718192021220230012345670891011121314'=> array(
					'user_location_information'	=> null,
					'list_of_service_data'	=>	array('l'=> array(23)),
				),
			'012034056700891011120131415161718192021001234567089101112'=> array(
					'record_sequence_number' => array('L*'=> array(13)),
					'node_id' => array('string'=> array(14)),
					'local_sequence_number' => array('number'=> array(15)),
					'apn_selection_mode' => array('C*'=> array(16)),
					'served_msisdn'	=>	array('C*'=> array(17)),
					'charging_characteristics'	=>	array('C*'=> array(18)),
					'charging_characteristics_selection_mode'	=>	array('C*'=> array(19)),
					'sgsn_plmn_id'	=>	array('number'=> array(20)),
					'served_imeisv'	=>	null,
					'rat_type'	=>	null,
					'ms_timezone'	=>	null,
					'user_location_information'	=>	null,
					'list_of_service_data'	=>	array('json'=> array(21)),
				),
			'0120340567008910111201314151617181920001234567089101112101234567089101112'=> array(
					'served_imeisv'	=>	null,
					'rat_type'	=>	null,
					'ms_timezone'	=>	null,
					'user_location_information'	=>	null,
					'list_of_service_data'	=>	array('json'=> array(20)),
					'record_extensions'	=>	array('json'=> array(21)),
				),
			'0120340567008910111213141516171819202122230240012345670891011121314'=> array(
				),
			'012034056700891011121314151617181920212202302400123456708910111213141012345670891011121314' =>  array(
			),
			'01203405670089101112013141516171819202122230240012345670891011121314' => array(
					'record_sequence_number' => array('L*'=> array(13)),
					'node_id' => array('string'=> array(14)),
					'local_sequence_number' => array('number'=> array(15)),
					'apn_selection_mode' => array('C*'=> array(16)),
					'served_msisdn'	=>	array('C*'=> array(17)),
					'charging_characteristics'	=>	array('C*'=> array(18)),
					'charging_characteristics_selection_mode'	=>	array('C*'=> array(19)),
					'sgsn_plmn_id'	=>	array('number'=> array(20)),
					'served_imeisv'	=>	array('BCDencode'=> array(21)),
					'rat_type'	=>	array('H*'=> array(22)),
					'ms_timezone'	=>	array('datetime'=> array(23,0)),
					'user_location_information'	=>	null,
					'list_of_service_data'	=>	array('json'=> array(23)),
				),
			'012034056700891011120131415161718192021222300123456708910111213' => array(
					'record_sequence_number' => array('L*'=> array(13)),
					'node_id' => array('string'=> array(14)),
					'local_sequence_number' => array('number'=> array(15)),
					'apn_selection_mode' => array('C*'=> array(16)),
					'served_msisdn'	=>	array('C*'=> array(17)),
					'charging_characteristics'	=>	array('C*'=> array(18)),
					'charging_characteristics_selection_mode'	=>	array('C*'=> array(19)),
					'sgsn_plmn_id'	=>	array('number'=> array(20)),
					'served_imeisv'	=>	array('BCDencode'=> array(21)),
					'rat_type'	=>	array('H*'=> array(22)),
					'ms_timezone'	=>	null,
					'user_location_information'	=>	null,
					'list_of_service_data'	=>	array('json'=> array(23)),
				),
			'0120340567008910111201314151617181920212223240250012345670891011121314' => array(
					'record_sequence_number' => array('L*'=> array(13)),
					'node_id' => array('string'=> array(14)),
					'local_sequence_number' => array('number'=> array(15)),
					'apn_selection_mode' => array('C*'=> array(16)),
					'served_msisdn'	=>	array('C*'=> array(17)),
					'charging_characteristics'	=>	array('C*'=> array(18)),
					'charging_characteristics_selection_mode'	=>	array('C*'=> array(19)),
					'sgsn_plmn_id'	=>	array('number'=> array(20)),
					'served_imeisv'	=>	array('BCDencode'=> array(21)),
					'rat_type'	=>	array('H*'=> array(22)),
					'ms_timezone'	=>	array('datetime'=> array(23)),
					'user_location_information'	=>	array('datetime'=> array(24,0)),
					'list_of_service_data'	=>	array('json'=> array(25)),
				),
			);
		return $data_structure;

	}
}

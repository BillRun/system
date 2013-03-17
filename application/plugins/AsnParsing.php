<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of AsnParser
 *
 * @author eran
 */
trait AsnParsing {
	
	/**
	 * Get specific data from an asn.1 structure  based on configuration
	 * @param type $data the ASN.1 data struture
	 * @param type $config the configuration of the data to retrive.
	 * @return Array an array containing flatten asn.1 data keyed by the configuration.
	 */
	protected function getASNDataByConfig( $data, $config , $fields ) {
		$dataArr = Asn_Base::getDataArray( $data, true );
		$valueArr= array();
		foreach($config as $key => $val) {			
			$tmpVal = $this->parseASNData(explode(',', $val), $dataArr, $fields );
			if($tmpVal) {
				$valueArr[$key] = $tmpVal;
			}
		}
		return count($valueArr) ? $valueArr : false;
	}
	
	/**
	 * convert the actual data we got from the ASN record to a readable information
	 * @param $struct TODO
	 * @param $asnData the parsed ASN.1 recrod.
	 * @param $fields TODO
	 * @return Array conatining the fields in the ASN record converted to readableformat and keyed by they're use.
	 */
	protected function parseASNData($struct, $asnData, $fields) {
		$matches = array();
		if(  preg_match("/\[(\w+)\]/",$struct[0],$matches) || !is_array($asnData) ) {
			$ret = false;
			if(!isset($matches[1]) || !$matches[1] || !isset($fields[$matches[1]])) {
				$this->log->log(" couldn't digg into : {$struct[0]} struct : ". print_r($struct,1) . " data : " . print_r($asnData,1) , Zend_Log::DEBUG);				
			} else {
				$ret = $this->parseField( $fields[$matches[1]], $asnData );
			}
			return $ret;
		}
		foreach ($struct as $val) {

			if (isset($asnData[$val])) {
					//$this->log->log(" digging into : $val  data :". print_r($asnData[$val],1), Zend_Log::DEBUG);
					$newStruct = $struct;
					array_shift($newStruct);
					return $this->parseASNData($newStruct, $asnData[$val], $fields);
				} 
		}

		return false;
	}
	
	/**
	 * parse a field from raw data based on a field description
	 * @param string $fieldData the raw data to be parsed.
	 * @param array $type the field description
	 * @return mixed the parsed value from the field.
	 */
	abstract protected function parseField( $type, $fieldData );
	
	/**
	 * Standrad field parsing methods.
	 * @param string $fieldData the raw data to be parsed.
	 * @param array $type the field description
	 * @return the parsed value of the field or null if the type wasnt found.
	 */
	protected function parseStandardFields( $type, $fieldData ) {
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
				
				case 'ascii':
						$retValue = preg_replace('/[^(\x20-\x7F)]*/','', $fieldData);
					break;
				
				case 'ascii_number':
						$retValue = intval( preg_replace('/[^(\x20-\x7F)]*/','', $fieldData),10);
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
					
				case 'bcd_number' :
				case 'bcd_encode' :
					$halfBytes = unpack('C*', $fieldData);
					$fieldData = '';
					foreach ($halfBytes as $byte) {
						$fieldData .=  ((($byte >> 4) < 10) ? ($byte >> 4) : '' ) . ($byte & 0xF) ;
					}
					if($type == 'bcd_number') {
						$retValue = intval($retValue,10);
					}
					break;
		
				case 'ip' :
					$fieldData = implode('.', unpack('C*', $fieldData));
					break;
				
				case 'ip6' :
					$fieldData = implode(':', unpack('H*', $fieldData));
					break;
				
				case 'datetime' :
					$tempTime = DateTime::createFromFormat('ymdHisT', str_replace('2b', '+', implode(unpack('H*', $fieldData))));
					$fieldData = is_object($tempTime) ? $tempTime->format('YmdHis') : '';
					break;

				case 'json' :
					$fieldData = json_encode($this->utf8encodeArr($fieldData));
					break;

				default:
					$fieldData = null;
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
}

?>

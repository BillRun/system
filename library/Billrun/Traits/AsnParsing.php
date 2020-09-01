<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This Trait is used  for classes that need to  implement ASN binary parsing for files such as GGSN/TAP3
 *
 * @author eran
 */
trait Billrun_Traits_AsnParsing {

	protected $parsingMethods = array();

	protected function initParsing() {
		$this->parsingMethods = array(
			'debug' => function($fieldData) {/* //TODO remove */
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
				Billrun_Factory::log()->log("DEBUG : " . $type . " | " . $numData . " | " . $tempData . " | " . implode(unpack("H*", $fieldData)) . " | " . implode(unpack("C*", $fieldData)) . " | " . $fieldData, Zend_Log::DEBUG);
				return "";
			},
			'string' => function($fieldData) {
				return utf8_encode($fieldData);
			},
			'ascii' => function($fieldData) {
				return preg_replace('/[^(\x20-\x7F)]*/', '', $fieldData);
			},
			'ascii_number' => function($fieldData) {
				return intval(preg_replace('/[^(\x20-\x7F)]*/', '', $fieldData), 10);
			},
			'long' => function($fieldData) {
				$numarr = unpack('C*', $fieldData);
				$ret = 0;
				foreach ($numarr as $byte) {
					$ret = bcadd(bcmul($ret, 256), $byte);
				}
				return $ret;
			},
			'number' => function($fieldData) {
				$numarr = unpack('C*', $fieldData);
				$ret = 0;
				foreach ($numarr as $byte) {
					$ret = ($ret << 8) + $byte;
				}
				return $ret;
			},
			'bcd_encode' => function($fieldData) {
				$halfBytes = unpack('C*', $fieldData);
				$ret = '';
				foreach ($halfBytes as $byte) {
					$ret .= ((($byte >> 4) < 10) ? ($byte >> 4) : '' ) . ($byte & 0xF);
				}
				return $ret;
			},
			'ip' => function($fieldData) {
				return !is_string($fieldData) ? null : (strlen($fieldData) > 4 ? preg_replace('/([\w\d]{4})(?!$)/','\1:',implode('', unpack('H*', $fieldData))) : implode('.', unpack('C*', $fieldData)));
			},
			'ip6' => function($fieldData) {
				return !is_string($fieldData) ? null : implode(':', unpack('H*', $fieldData));
			},
			'datetime' => function($fieldData) {
				$tempTime = DateTime::createFromFormat('ymdHisT', str_replace('2b', '+', implode(unpack('H*', $fieldData))));
				return is_object($tempTime) ? $tempTime->format('YmdHis') : '';
			},
			'json' => function($fieldData) {
				return json_encode($this->utf8encodeArr($fieldData));
			},
		);
	}

	/**
	 * Get specific data from an asn.1 structure  based on configuration
	 * @param type $data the ASN.1 data struture
	 * @param type $config the configuration of the data to retrive.
	 * @return Array an array containing flatten asn.1 data keyed by the configuration.
	 */
	protected function getASNDataByConfig($data, $config, $fields) {
		$dataArr = Asn_Base::getDataArray($data, true);
		$valueArr = array();
		foreach ($config as $key => $val) {
			$tmpVal = $this->parseASNData(explode(',', $val), $dataArr, $fields);
			if ($tmpVal !== FALSE) {
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

			if (isset($asnData[$val])) {
				//Billrun_Factory::log()->log(" digging into : $val  data :". print_r($asnData[$val],1), Zend_Log::DEBUG);
				$newStruct = $struct;
				array_shift($newStruct);
				return $this->parseASNData($newStruct, $asnData[$val], $fields);
			}
		}

		return false;
	}

	/**
	 * convert the actual data we got from the ASN record to a readable information
	 * @param $struct TODO
	 * @param Asn_Object $asnData the parsed ASN.1 recrod.
	 * @param $fields TODO
	 * @return Array conatining the fields in the ASN record converted to readableformat and keyed by they're use.
	 */
	protected function parseASNDataRecur($struct, $asnData, $fields) {
		$ret = false;
		if ((isset($struct['type']) && $struct['type'] != 'array') || !($asnData instanceof Asn_Object && $asnData->isConstructed())) {
			if (!isset($struct['type']) || !isset($fields[$struct['type']])) {
				Billrun_Factory::log()->log(" couldn't digg into struct : " . print_r($struct, 1) . " data : " . $asnData->getData(), Zend_Log::DEBUG);
			} else {
				$ret = $this->parseField($fields[$struct['type']], $asnData->getData());
			}
		} else {
			foreach ($asnData->getData() as $parsedElem) {
				$key = $parsedElem->getType();
				if (isset($struct[$key])) {
					$val = $this->parseASNDataRecur($struct[$key], $parsedElem, $fields);
					if ((isset($struct[$key]['type']) && $struct[$key]['type'] == 'array') || (isset($ret[$struct[$key]['name']][0])) && is_array($ret[$struct[$key]['name']])) {
						$ret[$struct[$key]['name']][] = $val;
					} else if (isset($ret[$struct[$key]['name']])) {
						$ret[$struct[$key]['name']] = array_merge(array($ret[$struct[$key]['name']]), array($val));
					} else {
						$ret[$struct[$key]['name']] = $val;
					}
				}
			}
		}
		return $ret;
	}

	/**
	 * parse a field from raw data based on a field description
	 * @param string $type the field description
	 * @param array $fieldData the raw data to be parsed.
	 * @return mixed the parsed value from the field.
	 */
	protected function parseField($type, $fieldData) {
		$ret = isset($this->parsingMethods[$type]) ? $this->parsingMethods[$type]($fieldData) : null;
		if (null === $ret && $this->parsingMethods['default']) {
			$ret = $this->parsingMethods['default']($type, $fieldData);
		}

		return $ret;
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

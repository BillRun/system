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
trait application_helpers_Traits_AsnParsing {
	
	/**
	 * Get specific data from an asn.1 structure  based on configuration
	 * @param type $data the ASN.1 data struture
	 * @param type $config the configuration of the data to retrive.
	 * @return Array an array containing flatten asn.1 data keyed by the configuration.
	 */
	protected function getASNDataByConfig( $data, $config ) {
		$dataArr = Asn_Base::getDataArray( $data, true );
		$valueArr= array();
		foreach($config as $key => $val) {			
			$tmpVal = $this->parseASNData(explode(',', $val), $dataArr);
			if($tmpVal) {
				$valueArr[$key] = $tmpVal;
			}
		}
		return count($valueArr) ? $valueArr : false;
	}
	
	/**
	 * convert the actual data we got from the ASN record to a readable information
	 * @param $asnData the parsed ASN.1 recrod.
	 * @return Array conatining the fields in the ASN record converted to readableformat and keyed by they're use.
	 */
	protected function parseASNData($struct, $asnData) {
		$matches = array();
		if(  preg_match("/\[(\w+)\]/",$struct[0],$matches) || !is_array($asnData)) {
			//$this->log->log(" digging into : {$struct[0]} data : ". print_r($asnData,1) , Zend_Log::DEBUG);
			$ret = $this->parseField( $asnData, $this->nsnConfig['fields'][$matches[1]]);
			return $ret;
		}
		foreach ($struct as $val) {

			if (isset($asnData[$val])) {
					//$this->log->log(" digging into : $val  data :". print_r($asnData[$val],1), Zend_Log::DEBUG);
					$newStruct = $struct;
					array_shift($newStruct);
					return $this->parseASNData($newStruct, $asnData[$val]);
				} 
		}

		return false;
	}
	
	/**
	 * parse a field from raw data based on a field description
	 * @param string $data the raw data to be parsed.
	 * @param array $fileDesc the field description
	 * @return mixed the parsed value from the field.
	 */
	abstract protected function parseField($data, $fileDesc);
}

?>

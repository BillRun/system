<?php

class ASN_TYPE_NUMSTR extends ASN_OBJECT {
	/**
	 * Parse the ASN data of a primitive type.
	 * (Override this to parse specific types)
	 * @param $data The ASN encoded data
	 */
	protected function  parse($data) {
		parent::parse($data);
// 		$numarr = unpack("C*",$data);
// 		$tempData =0;
// 		foreach($numarr as $byte) {
// 			$tempData =  ($tempData << 8 )+ $byte;
// 		}
//
// 		$this->parsedData = $tempData;
	}
}
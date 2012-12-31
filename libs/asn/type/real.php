<?php

class ASN_TYPE_REAL extends ASN_OBJECT {
	/**
	 * Parse the ASN data of a primitive type.
	 * (Override this to parse specific types)
	 * @param $data The ASN encoded data
	 */
	protected function  parse($data) {
	//	$this->parsedData = unpack(($this->dataLength > 4 ? "d" : "f"),$data);;
		parent::parse($data);
	}
}
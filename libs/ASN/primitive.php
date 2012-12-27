<?php

// ASN.1 parsing library
// Attribution: http://www.krisbailey.com
// license: unknown
// modified: Mike Macgrivin hide@address.com 6-oct-2010 to support Salmon auto-discovery
// from openssl public keys


abstract class ASN_PRIMITIVE extends ASN_BASE {

	function __construct($data = false)
	{
		$this->parse($this->asnData);
	}

	/**
	 * get the parsed data that was encoded in the ASN.
	 * @retrun mixed the actual that that was encoded in this field.
	 */
	 public function getData() {
		return $this->parsedData;
	 }

	/**
	 * Parse the ASN data of a primitive type.
	 * (Override this to parse specific types)
	 * @param $data The ASN encoded data
	 */
	protected function  parse($data) {
		$this->parsedData = $data;
	}

}



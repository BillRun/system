<?php

// ASN.1 parsing library
// Attribution: http://www.krisbailey.com
// license: unknown
// modified: Mike Macgrivin hide@address.com 6-oct-2010 to support Salmon auto-discovery
// from openssl public keys


class ASN_CONSTRUCT {

	function __construct($data) {
		parent::__construct($data);
		$this->parseData = array();
		while (count($data)){
				$this->parseData[] = $this->newClassFromData($data);
		}

	}

	protected function newClassFromData(&$rawData) {
		$p =0;
		$type = $this->shift($rawData);
		if( ($type & ASN_MARKERS::ASN_CONTEXT) && (($type & 0x1F) == 0x1F)) {
			$type = ord($string[$p++]);
		}
		$cls = $this->getClassForType($type);
		if(!$cls) return null;
		$data = $this->getData($rawData);
		return new $cls($data);
	}

	protected function getClassForType($type) {
		$constracted = $type &  ASN_MARKERS::ASN_CONSTRUCTOR;
		$type = $type&0x1F; // strip out context
		if(isset(ASN_BASE::$ASN_TYPES[$type])) {
			$cls = ASN_BASE::$ASN_TYPES[$type];
;
		} else {
// 			print("not detected : ". dechex($type));
			$cls = $constracted ?  ASN_PRIMITIVE : ASN_CONSTRUCT;
		}
		return $cls;
	}

}



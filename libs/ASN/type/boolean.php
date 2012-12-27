<?php

class ASN_TYPE_BOOLEAN extends ASN_PRIMITIVE {
	/**
	 * Parse the ASN data of a primitive type.
	 * (Override this to parse specific types)
	 * @param $data The ASN encoded data
	 */
	protected function  parse($data) {
		$this->parsedData = $data;
	}
}
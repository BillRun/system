<?php
/**
 * @package			ASN
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */
class ASN_TYPE_INTEGER extends ASN_OBJECT {
	/**
	 * Parse the ASN data of a primitive type.
	 * (Override this to parse specific types)
	 * @param $data The ASN encoded data
	 */
	protected function  parse($data) {
		parent::parse($data);
		//$this->parsedData = unpack("I*",$data);;
	}
}
<?php

/**
 * @package			ASN
 * @copyright		Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license			GNU Affero General Public License Version 3; see LICENSE.txt
 */
class Asn_Type_Integer extends Asn_Object {

	/**
	 * Parse the ASN data of a primitive type.
	 * (Override this to parse specific types)
	 * @param $data The ASN encoded data
	 */
	protected function parse($data) {
		return parent::parse($data);
		//$this->parsedData = unpack("I*",$data);;
	}

}

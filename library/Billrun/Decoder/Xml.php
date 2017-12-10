<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Xml Decoder
 *
 * @package  Application
 * @subpackage Plugins
 * @since    4.0
 */
class Billrun_Decoder_Xml extends Billrun_Decoder_Base {

	public function decode($str) {
		$xmlArr = (array) simplexml_load_string($str);
		return json_decode(json_encode($xmlArr), 1);
	}

}

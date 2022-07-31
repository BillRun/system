<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Json Decoder
 *
 * @package  Application
 * @subpackage Plugins
 * @since    4.0
 */
class Billrun_Decoder_Json extends Billrun_Decoder_Base {

	public function decode($str) {
		return json_decode($str, JSON_OBJECT_AS_ARRAY);
	}

}

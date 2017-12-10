<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Json Encoder
 *
 * @package  Application
 * @subpackage Plugins
 * @since    4.0
 */
class Billrun_Encoder_Json extends Billrun_Encoder_Base {

	public function encode($elem, $params = array()) {
		$addHeader = !isset($params['addHeader']) || $params['addHeader'];
		if ($addHeader) {
			header('Content-Type: application/json');
		}
		return json_encode((array) $elem);
	}

}

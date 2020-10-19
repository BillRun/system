<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Number type translator
 *
 * @package  Api
 * @since    5.11
 */
class Api_Translator_NumberModel extends Api_Translator_TypeModel {

	public function internalTranslateField($data) {
		if (is_numeric($data)) {
			return floatval($data);
		} else {
			return false;
		}
	}

}

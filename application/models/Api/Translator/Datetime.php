<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Datetime type translator
 *
 * @package  Api
 * @since    5.3
 */
class Api_Translator_DatetimeModel extends Api_Translator_TypeModel {
	
	/**
	 * Translate an array
	 * @param mixed $data - Input data
	 * @return mixed Translated value.
	 */
	public function internalTranslateField($data) {
		try {
			return new MongoDate(strtotime($data));
		} catch (MongoException $ex) {
			return false;
		}
	}
}

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
			if (isset($data['sec'])) {
				return new MongoDate($data['sec']);
			}
			$time = strtotime($data);
			if ($time > 0) {
				return new MongoDate($time);
			} else {
				return false;
			}
			
		} catch (MongoException $ex) {
			return false;
		}
	}
}

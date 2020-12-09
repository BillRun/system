<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Datetime array type translator - for complex queries with datetime included
 *
 * @package  Api
 * @since    5.6
 */
class Api_Translator_DatetimearrayModel extends Api_Translator_DatetimeModel {
	
	/**
	 * Translate an array of datetimes
	 * @param mixed $data - Input data
	 * @return mixed Translated value.
	 */
	public function internalTranslateField($data) {
		if (!is_array($data)) {
			return false;
		}
		try {
			$ret = array();
			foreach ($data as $cond => $date) {
				if ($cond === '$exists') {
					$ret[$cond] = boolval($date);
				} else {
					$ret[$cond] = parent::internalTranslateField($date);
					if ($ret[$cond] === false) {
						return false;
					}
				}
			}
			return $ret;
		} catch (MongoException $ex) {
			return false;
		}
	}
}

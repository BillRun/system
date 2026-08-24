<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2017 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Datetime type translator
 *
 * @package  Api
 * @since    5.6
 */
class Api_Translator_DatetimeInRangeModel extends Api_Translator_TypeModel {
	
	protected $queryFieldTranslate = true;
	
	/**
	 * Translate an array
	 * @param mixed $data - Input data
	 * @return mixed Translated value.
	 */
	public function internalTranslateField($data) {
		try {
			if (isset($data['sec'])) {
				$date = new Mongodloid_Date($data['sec']);
			} else {
				$time = strtotime($data);
				if ($time <= 0) {
					return false;
				}
				$date = new Mongodloid_Date($time);
			}
			return array(
					'from' => array('$lte' => $date),
					'to' => array('$gt' => $date),
			);
		} catch (MongoException $ex) {
			return false;
		}
	}
}

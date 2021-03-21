<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Boolean type translator
 *
 * @package  Api
 * @since    5.3
 */
class Api_Translator_BooleanModel extends Api_Translator_TypeModel {

	/**
	 * Translate an array
	 * @param mixed $data - Input data
	 * @return mixed Translated value.
	 */
	public function internalTranslateField($data) {
		try {
			if (is_array($data)) {
				$ret = array();
				foreach ($data as $cond => $date) {
					$ret[$cond] = boolval($date);
				}
				return $ret;
			}
			return boolval($data);
		} catch (MongoException $ex) {
			return null;
		}
	}

	/**
	 * method to validate the trasnlated value.
	 * 
	 * @param mixed $data the data to check
	 * @return boolean true if valid else false
	 */
	protected function valid($data) {
		return !is_null($data);
	}

}

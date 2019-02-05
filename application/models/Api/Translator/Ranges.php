<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Range type translator
 *
 * @package  Api
 * @since    5.6
 */
class Api_Translator_RangesModel extends Api_Translator_TypeModel {
	
	protected $queryFieldTranslate = true;
	
	/**
	 * Translate an array
	 * @param mixed $data - Input data
	 * @return mixed Translated value.
	 */
	public function internalTranslateField($data) {
		try {
			return self::getRangesFieldQuery($this->fieldName, $data);
		} catch (MongoException $ex) {
			return false;
		}
	}
	
	public static function getRangesFieldQuery($fieldName, $value) {
		return [
			$fieldName => [
				'$elemMatch' => [
					'from' => [
						'$lte' => $value,
					],
					'to' => [
						'$gte' => $value,
					],
				],
			],
		];
	}
	
	public static function getOverlapQuery($field, $ranges) {
		$ret = ['$or' => []];
		
		foreach ($ranges as $range) {
			$ret['$or'][] = self::getRangesFieldQuery($field, $range['from']);
			$ret['$or'][] = self::getRangesFieldQuery($field, $range['to']);
		}
		
		return $ret;
	}
}

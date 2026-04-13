<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Util class of ASN1 file
 *
 * @package  ASN1
 * @since    2.8
 */
class Asn1_Utils {

	/**
	 * encodes the data according to the configuration provided
	 * 
	 * @param array $data
	 * @param array $config
	 * @return array
	 */
	public static function encode($data, $config) {
		$parsedObject = self::parseData($data, $config)[0]['object'];
		return $parsedObject ? $parsedObject->getBinary() : '';
	}
	
	public static function parseData($data, $config,$parentType = null) {
		$ret = array();

		uksort($data,function($ka,$kb) use ( $config, $parentType ){
			return  (isset($config['field_order'][$parentType][$ka]) ? intval($config['field_order'][$parentType][$ka]) : 100 )
						-
					(isset($config['field_order'][$parentType][$kb]) ? intval($config['field_order'][$parentType][$kb]) : 100 );
		});

		foreach ($data as $asnType => $value) {
			$type = self::getType($asnType, $config);
			if (!$type) {
				// warning
				return array();
			}
			$applicationId = $config['application_id'][$asnType];
			if (is_array($type)) {
				$type = array_keys($type)[0];
			}
			$identifierClass = self::getIdentifierClass($type);
			if (!$identifierClass) {
				// warning
				continue;
			}
			$sons = array();
			
			switch ($type) {
				case 'choice':
				case 'sequence':
					$sons = self::parseData($value, $config, $asnType);
					break;
				case 'sequence_of':
					foreach ($value as $son) {
						$sons = array_merge($sons, self::parseData($son, $config, $asnType));
					}
					break;
				default:
					$sons[] = array(
						'object' => $value,
						'type' => $type,
					);
			}
			$ret[] = array(
				'object' => new $identifierClass($applicationId, $sons),
				'type' => $type,
			);
		}
		
		return $ret;
	}
	
	public static function getType($asnType, $config) {
		$ret = $config[$asnType];
		while (!is_array($ret) && isset($config[$ret])) {
			$ret = $config[$ret];
		}
		return $ret;
	}
	
	public static function getIdentifierClass($type) {
		return 'FG\ASN1\Tap3ExplicitlyTaggedObject';
	}
}

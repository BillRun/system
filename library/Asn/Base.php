<?php

/**
 * @package			ASN
 * @copyright		Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license			GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class is used to parse ASN.1 encoded data.
 * It defines some static functions deal with ASN data/objects
 *
 * @package  ASN
 * @since    0.5
 */
class Asn_Base {

	const USE_AUTOLOAD = false;

	/**
	 * Parse an ASN.1 binary string.
	 *
	 * This function takes a binary ASN.1 string and parses it into it's respective
	 * pieces and returns it.  It can optionally stop at any depth.
	 *
	 * @param	string	$string		The binary ASN.1 String
	 * @param	int	$level		The current parsing depth level
	 * @param	int	$maxLevel	The max parsing depth level
	 * @return	ASN	The array representation of the ASN.1 data contained in $string
	 */
	public static function parseASNString($rawData) {
		try {
			return self::newClassFromData($rawData);
		} catch (Exception $e) {
			throw new Exception("Failed parsing data cause : " . $e);
		}
	}

	/**
	 * Get the data ofan ASN object as an nested array.
	 * @param @rootObj the ASN object to get the data from.
	 * @return Array containing the object  and it`s nested childrens data.
	 */
	public static function getDataArray($rootObj, $keepTypes = false, $mergeArrays = false) {
		$retArr = array();
		foreach ($rootObj->parsedData as $val) {
			if ($keepTypes) {
				$typeKey = (($val instanceof Asn_Object ) ? $val->getType() : $rootObj->getType());
				$value = ($val instanceof Asn_Object && $val->isConstructed()) ? self::getDataArray($val, $keepTypes, $mergeArrays) : $val->getData();
				if ($mergeArrays && isset($retArr[$typeKey])) {
					if (is_array($retArr[$typeKey])) {
						if (!isset($retArr[$typeKey][0])) {
							$retArr[$typeKey] = array($retArr[$typeKey]);
						}
						array_push($retArr[$typeKey], $value);
					} else {
						$retArr[$typeKey] = array($retArr[$typeKey], $value);
					}
				} else {
					$retArr[$typeKey] = $value;
				}
			} else {
				$retArr[] = ($val instanceof Asn_Object && $val->isConstructed()) ? self::getDataArray($val, $keepTypes, $mergeArrays) : $val->getData();
			}
		}
		return $retArr;
	}

	/**
	 * Create a new ASN object from a given byte data encoded in ASN1.
	 * @param $rawData the raw byte data that we want to decode
	 * @return Asn_Object an asn object holding the parsed ASN1 data.
	 */
	protected static function newClassFromData($rawData) {
		$tmpType = $offset = 0;
		$flags = ord($rawData[$offset++]);
		$type = $flags & Asn_Markers::ASN_EXTENSION_ID;
		if (($type & Asn_Markers::ASN_EXTENSION_ID) == 0x1F) {
			$type = 0;
			do {
				$tmpType = ord($rawData[$offset++]);
				$type = ( $tmpType & 0x7F ? $type << 7 : 0 ) + ( $tmpType & 0x7F );
			} while ($tmpType & Asn_Markers::ASN_CONTEXT && ($tmpType & 0x7F));
		}

		$cls = self::getClassForType($type, $flags);
		if (!$cls) {
			print('Asn_Base::newClassFromData couldn`t create class!!');
			return null;
		}
		
		$ret =  new $cls($rawData, $type, $flags, $offset);
		
		return $ret;
	}

	/**
	 * Shift a string/byte array  by  ceratin amount  and return the shifted bytes
	 * (Notice! will alter the provided data)
	 * @param $data the data to shift (Notice! will alter the provided data).
	 * @param $len  how much to shift.
	 * @return 	the shifted data.
	 * 	 	(Notice! will alter the provided $data)
	 */
	protected static function shift(&$data, $len = 1, $from = 0) {
		$shifted = substr($data, $from, $len);
		$data = substr($data, $len + $from);
		return $shifted;
	}

	/**
	 * Get a class to hold an ASN1 type.
	 * @return String  the name of the class that should be used to handle the data.
	 */
	protected static function getClassForType($type, $flags) {
		//$constructed = $type & Asn_Markers::ASN_CONSTRUCTOR;
		$context = $flags & Asn_Markers::ASN_CONTEXT;
		$type = $type & 0x1F; // strip out context
		if (!$context && isset(Asn_Types::$TYPES[$type]) && class_exists(Asn_Types::$TYPES[$type], self::USE_AUTOLOAD)) {
			$cls = Asn_Types::$TYPES[$type];
		} else {
			//print("not detected : ". dechex($type)."\n");
			$cls = 'Asn_Object'; //!( $constructed )  ?  'ASN_PRIMITIVE' : 'ASN_CONSTRUCT';
		}
		//print("Asn_Base::getClassForType $cls  for type id  : ".dechex($type)."\n");

		return $cls;
	}

}

<?php

/**
 * @package			ASN
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * This class is used to parse ASN.1 encoded data.
 * It defines some static functions deal with ASN data/objects
 *
 * @package  ASN
 * @since    1.0
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
		return self::newClassFromData($rawData);
	}

	/**
	 * get the data ofan ASN object as an nested array.
	 * @param @rootObj the ASN object to get the data from.
	 * @return Array containing the object  and it`s nested childrens data.
	 */
	public static function getDataArray($rootObj) {
		$retArr = array();
		foreach ($rootObj->parsedData as $val) {
			$retArr[] = ($val instanceof Asn_Object && $val->isConstructed()) ? self::getDataArray($val) : $val->getData();
		}
		return $retArr;
	}

	/**
	 * Create a new ASN object from a given byte data encoded in ASN1.
	 * @param $rawData the raw byte data that we want to decode
	 * @return Asn_Object an asn object holding the parsed ASN1 data.
	 */
	protected static function newClassFromData(&$rawData) {
		$offset = 0;
		$type = ord($rawData[$offset++]);
		if (($type & Asn_Markers::ASN_CONTEXT) && (($type & Asn_Markers::ASN_EXTENSION_ID) == 0x1F)) {
			$type = ord($rawData[$offset++]);
		}
		$cls = self::getClassForType($type);
		if (!$cls) {
			print("Asn_Base::newClassFromData couldn't create class!!");
			return null;
		}
		$data = self::getObjectData($rawData, $offset);
		return new $cls($data, $type);
	}

	/**
	 * Get the Object data from the raw byte array data.
	 * @param $rawData 	(passed by ref) the raw byte data.
	 * @return 		The object data block that was reoved from $rawData.
	 * 		 	(Notice! will alter the provided $rawData)
	 */
	protected static function getObjectData(&$rawData, $offest = 0) {
		$length = ord($rawData[$offest++]);
		if (($length & Asn_Markers::ASN_LONG_LEN) == Asn_Markers::ASN_LONG_LEN) {
			$tempLength = 0;
			for ($x = ($length - Asn_Markers::ASN_LONG_LEN); $x > 0; $x--) {
				$tempLength = ord($rawData[$offest++]) + ($tempLength << 8);
			}
			$length = $tempLength;
		}
		//print("Asn_Base::getRawData data length : $length \n");
		return self::shift($rawData, $length, $offest);
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
	protected static function getClassForType($type) {
		$constructed = $type & Asn_Markers::ASN_CONSTRUCTOR;
		$context = $type & Asn_Markers::ASN_CONTEXT;
		$type = $type & 0x1F; // strip out context
		if (!$context && isset(Asn_Types::$TYPES[$type]) && class_exists(Asn_Types::$TYPES[$type],self::USE_AUTOLOAD)) {
			$cls = Asn_Types::$TYPES[$type];
		} else {
			//print("not detected : ". dechex($type)."\n");
			$cls = 'Asn_Object'; //!( $constructed )  ?  'ASN_PRIMITIVE' : 'ASN_CONSTRUCT';
		}
		//print("Asn_Base::getClassForType $cls  for type id  : ".dechex($type)."\n");

		return $cls;
	}

}


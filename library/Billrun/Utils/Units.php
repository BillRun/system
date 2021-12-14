<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2017 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Static functions for units of measures conversions.
 * @package  Util
 * @since 5.6
 */
class Billrun_Utils_Units {

	/**
	 * get all units of measure of a usage type
	 * 
	 * @param string $usaget
	 * @return array
	 */
	public static function getUnitsOfMeasure($usaget) {
		$usageTypeData = self::getUsageTypeData($usaget);
		if (!$usageTypeData || !isset($usageTypeData['property_type'])) {
			return array();
		}
		$propertyTypeData = self::getPropertyTypeData($usageTypeData['property_type']);
		return isset($propertyTypeData['uom']) ? $propertyTypeData['uom'] : array();
	}

	/**
	 * get specific unit of measure data by usage type and unit name
	 * 
	 * @param string $usaget
	 * @param string $unit
	 * @return array
	 */
	public static function getUnitsOfMeasureData($usaget, $unit) {
		$uom = self::getUnitsOfMeasure($usaget);
		return self::arrayFind($uom, $unit, 'name');
	}

	/**
	 * converts volume received to the received unit (and usage type).
	 * convert to base unit (from unit) or from base unit to unit - according to $toBaseUnit flag
	 * 
	 * @param float $volume
	 * @param string $usaget
	 * @param string $unit
	 * @param boolean $toBaseUnit
	 * @return float
	 */
	public static function convertVolumeUnits($volume, $usaget, $unit, $toBaseUnit = false) {
		$uom = self::getUnitsOfMeasureData($usaget, $unit);
		if (!$uom) {
			return $volume;
		}
		if (isset($uom['convertFunction']) && method_exists(get_class(), $uom['convertFunction']) && $toBaseUnit) {
			return call_user_func_array(array(get_class(), $uom['convertFunction']), array($volume));
		}

		if (isset($uom['function_name']) && method_exists(get_class(), $uom['function_name'])) {
			return call_user_func_array(array(get_class(), $uom['function_name']), array($volume, $uom));
		}

		if (!isset($uom['unit'])) {
			return $volume;
		}

		return ($toBaseUnit ? ($volume * $uom['unit']) : ($volume / $uom['unit']));
	}

	/**
	 * gets usage type's default unit for invoice
	 * 
	 * @param string $usaget
	 * @return unit if found, false otherwise
	 */
	public static function getInvoiceUnit($usaget) {
		$usageTypeData = self::getUsageTypeData($usaget);
		return isset($usageTypeData['invoice_uom']) ? $usageTypeData['invoice_uom'] : false;
	}

	/**
	 * gets the unit's display label
	 * 
	 * @param string $usaget
	 * @param string $unit
	 * @return string
	 */
	public static function getUnitLabel($usaget, $unit) {
		$uom = self::getUnitsOfMeasureData($usaget, $unit);
		if (
				!$uom ||
				(isset($uom['function_name']) && method_exists(get_class(), $uom['function_name'])) ||
				!isset($uom['label'])
		) {
			return '';
		}

		return $uom['label'];
	}

	/**
	 * converts volume to invoice display
	 * 
	 * @param float $volume
	 * @param string $usaget
	 * @return float
	 */
	public static function convertInvoiceVolume($volume, $usaget) {
		$unit = self::getInvoiceUnit($usaget);
		if (!$unit) {
			return $volume;
		}
		return self::convertVolumeUnits($volume, $usaget, $unit, false);
	}

	/**
	 * assistance function to get multidimensional array data be a specific field
	 * 
	 * @param array $array
	 * @param mixed $value
	 * @param string $columnName
	 * @param mixed $default
	 * @return array if found, $default value otherwise
	 */
	protected static function arrayFind($array, $value, $columnName, $default = array()) {
		$index = array_search($value, array_column($array, $columnName));
		return $index !== false && isset($array[$index]) ? $array[$index] : $default;
	}

	/**
	 * get all available usage types in the system
	 * 
	 * @return array
	 */
	protected static function getUsageTypes() {
		return Billrun_Factory::config()->getConfigValue('usage_types', array());
	}

	/**
	 * get all available property types in the system
	 * 
	 * @return array
	 */
	protected static function getPropertyTypes() {
		return Billrun_Factory::config()->getConfigValue('property_types', array());
	}

	/**
	 * get usage type data from configuration be usage type name
	 * 
	 * @param string $usaget
	 * @return array
	 */
	protected static function getUsageTypeData($usaget) {
		$usageTypes = self::getUsageTypes();
		return self::arrayFind($usageTypes, $usaget, 'usage_type');
	}

	/**
	 * get property type data from configuration be property type name
	 * 
	 * @param string $propertyType
	 * @return array
	 */
	protected static function getPropertyTypeData($propertyType) {
		$propertyTypes = self::getPropertyTypes();
		return self::arrayFind($propertyTypes, $propertyType, 'type');
	}

	/**
	 * assistance function to display time
	 * 
	 * @param float $seconds
	 * @param mixed $data structure that should contain the requested format under arguments.format
	 * @return string
	 */
	protected static function parseTime($seconds, $data = null) {
		$formating = [
			'h' => ['mod' => 12, 'div' => 3600],
			'H' => ['mod' => 24, 'div' => 3600, 'pad' => '%02s'],
			'i' => ['mod' => 60, 'div' => 60, 'pad' => '%02s'],
			's' => ['mod' => 60, 'pad' => '%02s'],
			'_' => [
				'sub_format' => true,
				'H' => ['div' => 3600, 'pad' => '%02s'],
				'I' => ['div' => 60, 'pad' => '%02s'],
				'S' => ['pad' => '%02s'],
			]
		];
		$currFormat = $formating;
		$retValue = "";
		$format = !empty($data['arguments']['format']) && is_string($data['arguments']['format']) ? $data['arguments']['format'] : "H:i:s";

		foreach (str_split($format) as $key) {
			if (!empty($currFormat[$key])) {
				if (!empty($currFormat[$key]['sub_format'])) {
					$currFormat = $currFormat[$key];
					continue;
				}
				$val = floor(empty($currFormat[$key]['div']) ? $seconds : $seconds / $currFormat[$key]['div']);
				$modedVal = floor(empty($currFormat[$key]['mod']) ? $val : $val % $currFormat[$key]['mod']);
				$retValue .= empty($currFormat[$key]['pad']) ? $modedVal : sprintf($currFormat[$key]['pad'], $modedVal);
			} else {
				$retValue .= $key;
				$currFormat = $formating;
			}
		}
		return $retValue;
	}

	/**
	 * assistance function to convert data usage to automatic units
	 * 
	 * @param int $bytes
	 * @return string
	 */
	protected static function parseDataUsage($bytes) {
		return Billrun_Util::byteFormat($bytes, '', 2, true);
	}

	public static function formatedTimeToSeconds($volume) {
		sscanf($volume, "%d:%d:%d", $hours, $minutes, $seconds);
		return isset($seconds) ? $hours * 3600 + $minutes * 60 + $seconds : $hours * 60 + $minutes;
	}

}

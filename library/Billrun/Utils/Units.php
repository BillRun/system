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
		if (!$uom || !isset($uom['unit'])) {
			return $volume;
		}

		return ($toBaseUnit ? ($volume * $uom['unit'])  : ($volume / $uom['unit']));
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
	protected static function arrayFind ($array, $value, $columnName, $default = array()) {
		$index = array_search($value, array_column($array, $columnName));
		return isset($array[$index]) ? $array[$index] : $default;
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
	
}

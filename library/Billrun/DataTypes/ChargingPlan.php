<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents the charging plan data type.
 *
 * @package  DataTypes
 * @since    4
 */
class Billrun_DataTypes_ChargingPlan {
	
	/**
	 * This variable is for the field value name of this plan.
	 * @var string
	 */
	protected $valueFieldName = null;
	
	/**
	 * Value of the balance.
	 * @var Object
	 */
	protected $value = null;
	
	/**
	 * Name of the usaget of the plan [call\sms etc].
	 * @var string
	 */
	protected $chargingBy = null;
	
	/**
	 * Type of the charge of the plan. [usaget\cost etc...]
	 * @var string.
	 */
	protected $chargingByUsaget = null;
	
	/**
	 * Create a new instance of the charging plan record type.
	 * @param array $chargingBy
	 * @param array $chargingByValue
	 */
	public function __construct($chargingBy, $chargingByValue) {
		$chargingByUsegt = $chargingBy;

		if (!is_array($chargingByValue)) {
			$this->valueFieldName= 'balance.' . $chargingBy;
			$this->value = $chargingByValue;
		} else {
			list($chargingByUsegt, $this->value) = each($chargingByValue);
			$this->valueFieldName = 'balance.totals.' . $chargingBy . '.' . $chargingByUsegt;
		}
		
		$this->chargingBy = $chargingBy;
		$this->chargingByUsaget = $chargingByUsegt;
	}
	
	/**
	 * Get the value for the current plan.
	 * @return The current plan value.
	 */
	public function getValue() {
		return $this->value;
	}
	
	/**
	 * Retur the name of the field to set the value of the balance.
	 * @return The name of the field to set the value of the balance.
	 */
	public function getFieldName() {
		return $this->valueFieldName;
	}
	
	/**
	 * Get the charging by string value.
	 * @return string
	 */
	public function getChargingBy() {
		return $this->chargingBy;
	}
	
	/**
	 * Get the charging by usage t string value.
	 * @return string
	 */
	public function getChargingByUsaget() {
		return $this->chargingByUsaget;
	}
}

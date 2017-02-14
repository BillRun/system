<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents the wallet inside the balance.
 *
 * @package  DataTypes
 * @since    4
 */
class Billrun_DataTypes_Wallet {

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
	 * Unit of the charge of the plan. [usaget\cost etc...]
	 * @var string.
	 */
	protected $chargingByUsagetUnit = null;

	/**
	 * The period for when is this wallet active.
	 * @var array
	 */
	protected $period = null;

	/**
	 * The name of the pp include.
	 * @var string
	 */
	protected $ppName = null;

	/**
	 * The ID of the pp include.
	 * @var integer
	 */
	protected $ppID = null;

	/**
	 * The wallet priority.
	 * @var number - priority.
	 */
	protected $priority = null;

	/**
	 * Boolean indicator for is the wallet unlimited
	 * @var boolean
	 */
	protected $unlimited = null;
	
	/**
	 * Boolean indicator for is the wallet shared between subscriptions
	 * @var boolean
	 */
	protected $shared = null;
	
	/**
	 * Create a new instance of the wallet type.
	 * @param array $chargingBy
	 * @param array $chargingByValue
	 * @param array $ppPair Pair of prepaid includes values.
	 */
	public function __construct($chargingBy, $chargingByValue, $ppPair) {
		$chargingByUsaget = $chargingBy;

		if (isset($ppPair['priority'])) {
			$this->priority = $ppPair['priority'];
		}

		$this->ppID = (int) $ppPair['pp_includes_external_id'];
		$this->ppName = $ppPair['pp_includes_name'];
		$this->unlimited = !empty($ppPair['unlimited']);
		$this->shared = !empty($ppPair['shared']);
		
		// The wallet does not handle the period.
		if (isset($chargingByValue['period'])) {
			$this->setPeriod($chargingByValue['period']);
			unset($chargingByValue['period']);
		}

		if (!is_array($chargingByValue) || isset($chargingByValue['value'])) {
			$this->valueFieldName = 'balance.' . str_replace("total_", "", $chargingBy);
			$this->value = isset($chargingByValue['value']) ? $chargingByValue['value'] : $chargingByValue;
		} else {
//			list($chargingBy, $this->value) = each($chargingByValue);
			$isValid = false;
			foreach (array("usagev", "cost", "total_cost", "value") as $fieldName) {
				// If more than one field name exists PRIORITIZE THE FIRST VALUE
				if(isset($chargingByValue[$fieldName])) {
					$chargingBy = $fieldName;
					$this->value = $chargingByValue[$fieldName];
					$isValid = true;
					break;
				}
			}
			if(!$isValid) {
				$error = "Invalid plan record, no value to charge " . print_r($chargingByValue, 1);
				Billrun_Factory::log($error, Zend_Log::ERR);
				throw new Exception($error);
			}
			$this->valueFieldName = 'balance.totals.' . $chargingByUsaget . '.' . $chargingBy;
		}

		$this->chargingBy = $chargingBy;
		$this->chargingByUsaget = $chargingByUsaget;
		if ($this->chargingBy == 'cost' || $this->chargingBy == 'total_cost') {
			$this->chargingByUsagetUnit = Billrun_Util::getUsagetUnit($this->chargingBy);
		} else {
			$this->chargingByUsagetUnit = Billrun_Util::getUsagetUnit($chargingByUsaget);
		}

		$this->setValue();
	}

	/**
	 * Sets the value. If unable to convert to integer, throws an exception.
	 * @throws InvalidArgumentException
	 */
	protected function setValue() {
		// Convert the value to float
		settype($this->value, 'float');
	}

	/**
	 * Get the wallet priority
	 * @return numeric
	 */
	public function getPriority() {
		if ($this->priority) {
			return $this->priority;
		}

		$col = Billrun_Factory::db()->prepaidincludesCollection();
		$query = array("external_id" => $this->ppID);
		$prepaid = $col->query($query)->cursor()->current();
		if (isset($prepaid['priority'])) {
			$this->priority = $prepaid['priority'];
		} else {
			Billrun_Factory::log("Faild to retrieve PP include. ID: " . $this->ppID, Zend_Log::WARN);
			$this->priority = 0;
		}
		return $this->priority;
	}

	/**
	 * Get the value for the current wallet.
	 * @return The current wallet value.
	 */
	public function getValue() {
		return $this->value;
	}

	/**
	 * Get the unlimited indication
	 * @return boolean
	 */
	public function getUnlimited() {
		return $this->unlimited;
	}
	
	/**
	 * Get the period for the current wallet, null if not exists.
	 * @return The current wallet period.
	 * @todo Create a period object.
	 */
	public function getPeriod() {
		return $this->period;
	}

	/**
	 * Get the period for the current wallet, null if not exists.
	 * @return The current wallet period.
	 * @todo Create a period object.
	 */
	public function setPeriod($period) {
		$this->period = $period;
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

	/**
	 * Get the charging by unit usaget string value.
	 * @return string
	 */
	public function getChargingByUsagetUnit() {
		return $this->chargingByUsagetUnit;
	}

	/**
	 * Get the pp include name.
	 * @return string
	 */
	public function getPPName() {
		return $this->ppName;
	}

	/**
	 * Get the pp include ID.
	 * @return integer
	 */
	public function getPPID() {
		return $this->ppID;
	}

	/**
	 * Get the shared boolean indication
	 * @return boolean
	 */
	public function isShared() {
		return $this->shared;
	}
	
	/**
	 * Get the partial balance record from the wallet values.
	 * @param boolean $convertToPHP - If true, convert dot array to php style array.
	 * true by deafult.
	 * @return array - Partial balance.
	 */
	public function getPartialBalance($convertToPHP = true) {
		$partialBalance['charging_by'] = $this->getChargingBy();
		$partialBalance['charging_by_usaget'] = $this->getChargingByUsaget();
		$partialBalance['charging_by_usaget_unit'] = $this->getChargingByUsagetUnit();
		$partialBalance['pp_includes_name'] = $this->getPPName();
		$partialBalance['pp_includes_external_id'] = $this->getPPID();
		$partialBalance['priority'] = $this->getPriority();
		$partialBalance['unlimited'] = $this->unlimited;
		$partialBalance['shared'] = $this->shared;
		
		// If the balance is shared, set the sid to 0
		if($this->shared) {
			$partialBalance['sid'] = 0;
		}
		$partialBalance[$this->getFieldName()] = $this->getValue();

		if($convertToPHP) {
			$this->translatePartialBalance($partialBalance);
		}
		
		return $partialBalance;
	}
	
	/**
	 * Translate a partial balance object.
	 * @param type $partialBalance
	 */
	protected function translatePartialBalance(&$partialBalance) {
		foreach ($partialBalance as $key => $value) {
			if (!is_string($key)) {
				continue;
			}

			Billrun_Util::setDotArrayToArray($partialBalance, $key, $value);
		}
	}
}

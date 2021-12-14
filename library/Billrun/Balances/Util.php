<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Util class for the balances
 *
 * @package  Util
 * @since    4.1
 */
class Billrun_Balances_Util {

	/**
	 * The default update operation value
	 * @var mixed
	 */
	public static $DEFAULT_UPDATE_OPERATION = 0;

	/**
	 * Get the operation value from an array of options
	 * @param array $options - Array of options that should have the operation field,
	 * if it doesn't have an operation field, return default operation value.
	 * @param string $operationOptions - Options to initialize the update operation instance.
	 * @param string $operationKey - The field name for the operation in the options 
	 * array, "operation" by default
	 * @return Billrun_Balances_Update_Operation - Matching operation.
	 */
	public static function getOperation($options, $operationOptions = array(), $operationKey = "operation") {
		$operationValue = self::getOperationName($options, $operationKey);

		// Check if class exists.
		if (!class_exists($operationValue)) {
			return null;
		}

		// Allocate the class.
		return new $operationValue($operationOptions);
	}

	/**
	 * Get the balance update operation name.
	 * @param type $options
	 * @param type $operationKey
	 * @return string
	 */
	protected function getOperationName($options, $operationKey) {
		if (self::$DEFAULT_UPDATE_OPERATION === 0) {
			self::$DEFAULT_UPDATE_OPERATION = Billrun_Factory::config()->getConfigValue('balances.operation.default');
		}

		if (!isset($options[$operationKey])) {
			return self::$DEFAULT_UPDATE_OPERATION;
		}

		// Get the operation value.
		$operation = $options[$operationKey];

		// Get the operation list.
		$operationList = Billrun_Factory::config()->getConfigValue("balances.operation");

		if (!isset($operationList[$operation])) {
			// Return default.
			return self::$DEFAULT_UPDATE_OPERATION;
		}

		return $operationList[$operation];
	}

	/**
	 * Get the balance value rounded to a point.
	 * @param array $balance - Balance record
	 * @param type $precision - The precision point to round the value to, default is 5.
	 * @return int Value of the balance.
	 */
	public static function getBalanceValue($balance, $precision = 5) {
		if (!$balance) {
			return 0;
		}

		if (!isset($balance['balance'])) {
			Billrun_Factory::log("Received invalid balance record!", Zend_Log::ERR);
			return 0;
		}

		$balanceValue = $balance['balance'];
		$value = Billrun_Util::getFirstValueOfMultidimentionalArray($balanceValue);
		$rounded = round($value, $precision, PHP_ROUND_HALF_UP);
		return $rounded;
	}

	/**
	 * removes the transactions from the balance to save space.
	 * @param array $row - Balance row.
	 * @return the update result.
	 */
	public static function removeTx($row) {
		$query = array(
			'sid' => array(
				'$in' => array(0, $row['sid']),
			),
			'from' => array(
				'$lte' => $row['urt'],
			),
			'to' => array(
				'$gt' => $row['urt'],
			),
		);
		$values = array(
			'$unset' => array(
				'tx.' . $row['stamp'] => 1
			)
		);
		$options = array(
			'multiple' => true,
		);

		$balances = Billrun_Factory::db()->balancesCollection();
		return $balances->update($query, $values, $options);
	}

}

<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing tax calculator using configuration to determine it's logic
 *
 * @package  calculator
 * @since 5.9
 */
class Billrun_Calculator_Tax_Usage extends Billrun_Calculator_Tax {

	/**
	 * @see Billrun_Calculator_Tax::updateRowTaxInforamtion
	 */
	protected function updateRowTaxInforamtion($line, $subscriber, $account) {
		
	}

	//================================= Static =================================

	/**
	 * get system's default tax
	 * 
	 * @return Mongodloid_Entity
	 */
	protected static function getDetaultTax($time = null) {
		$taxKey = Billrun_Factory::config()->getConfigValue('tax.default.key', '');
		if (empty($taxKey)) {
			return false;
		}

		$taxCollection = Billrun_Factory::db()->taxesCollection();
		$query = Billrun_Utils_Mongo::getDateBoundQuery($time);
		$query['key'] = $taxKey;
	}

	/**
	 * get system's default tax's rate
	 * 
	 * @return float
	 */
	protected static function getDetaultTaxRate($time = null) {
		$defaultTax = self::getDetaultTax($time);
		if (empty($defaultTax) || !isset($defaultTax['rate'])) {
			return false;
		}

		return $defaultTax['rate'] / 100;
	}

	/**
	 * @see Billrun_Calculator_Tax::addTax
	 */
	public static function addTax($untaxedPrice, $taxedLine = null) {
		$taxAmount = self::getTaxAmount($untaxedPrice, 'add', $taxedLine);
		if ($taxAmount === false) {
			return false;
		}
		
		return $taxedPrice + $taxAmount;
	}

	/**
	 *  @see Billrun_Calculator_Tax::removeTax
	 */
	public static function removeTax($taxedPrice, $taxedLine = null) {
		$taxAmount = self::getTaxAmount($taxedPrice, 'remove', $taxedLine);
		if ($taxAmount === false) {
			return false;
		}
		
		return $taxedPrice - $taxAmount;
	}

	/**
	 * get tax amount according to the action (add / remove)
	 * 
	 * @param float $price
	 * @param string $action - add/remove
	 * @param array $taxedLine
	 * @return amount if can be calculated, false otherwise
	 */
	protected static function getTaxAmount($price, $action, $taxedLine = null) {
		if (isset($taxedLine['tax_data']['tax_amount'])) {
			return $taxedLine['tax_data']['tax_amount'];
		}

		$time = $row['urt']->sec;
		$taxRate = self::getDetaultTax($time);
		if (empty($taxRate)) {
			return false;
		}

		switch ($action) {
			case 'add':
				return $price * $taxRate;
			case 'remove':
				return ($price / (1 + $taxRate)) * $taxRate;
			default:
				return false;
		}
	}

}

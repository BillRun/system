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
	use Billrun_Traits_EntityGetter;

	/**
	 * @see Billrun_Calculator_Tax::updateRowTaxInforamtion
	 */
	protected function updateRowTaxInforamtion($line, $subscriber, $account) {
		$taxData = $this->getRowTaxData($line);
		if ($taxData === false) {
			return false;
		}
		
		$line['tax_data'] = $taxData;
		return $line;
	}
	
	/**
	 * @see Billrun_Calculator_Tax::getPreTaxedRowTaxData
	 */
	protected function getPreTaxedRowTaxData($line) {
		return $this->getRowTaxData($line);
	}
	
	/**
	 * get the Tax entity for the line
	 * 
	 * @param array $line
	 * @return Mongodloid_Entity if found, false otherwise
	 */
	public function getLineTaxes($line) {
		$taxes = $this->getMatchingEntitiesByCategories($line, $params);
		
		if ($taxes !== false) {
			return $taxes;
		}
		
		return [
			'default' => self::getDetaultTax($row['urt']->sec),
		];
	}
	
		/**
	 * get row's tax data 
	 * 
	 * @param array $line
	 */
	protected function getRowTaxData($line) {
		if (!empty($line['tax_data'])) {
			return $line['tax_data'];
		}
		
		$taxes = $this->getLineTaxes($line);
		if ($taxes === false) {
			return false;
		}
		
		$totalTax = 0;
		$totalAmount = 0;
		$taxesData = [];

		foreach ($taxes as $taxCategory => $tax) {
			$taxFactor = $tax['rate'];
			$taxAmount = $line['aprice'] * $taxFactor;
			$taxesData[] = [
				'tax' => $taxFactor,
				'amount' => $taxAmount,
				'description' => $tax['description'] ?: 'VAT',
				'key' => $tax['key'],
				'type' => $taxCategory,
				'pass_to_customer' => 1,
			];
			$totalAmount += $taxAmount;
			$totalTax += $taxFactor;
		}

		return [
			'total_amount' => $totalAmount,
			'total_tax' => $totalTax,
			'taxes' => $taxesData,
		];
	}
	
	/**
	 * get line's tax's rate
	 * 
	 * @return float
	 */
	protected function getLineTaxRate($line = null) {
		if (isset($taxedLine['tax_data']['total_tax'])) {
			return $taxedLine['tax_data']['total_tax'];
		}
		
		$totalRate = 0;
		$taxes = $this->getLineTaxes($line);
		
		if (empty($taxes)) {
			return false;
		}
		
		foreach ($taxes as $tax) {
			if (!isset($tax['rate'])) {
				return false;
			}
			
			$totalRate += $tax['rate'];
		}

		return $totalRate;
	}

	//================================= Static =================================

	/**
	 * get system's default tax
	 * 
	 * @return Mongodloid_Entity
	 */
	public static function getDetaultTax($time = null) {
		$taxKey = Billrun_Factory::config()->getConfigValue('tax.default.key', '');
		if (empty($taxKey)) {
			return false;
		}

		return self::getTaxByKey($taxKey, $time);
	}
	
	/**
	 * get tax by key
	 * 
	 * @return Mongodloid_Entity
	 */
	public static function getTaxByKey($key, $time = null) {
		$taxCollection = self::getTaxCollection();
		$query = Billrun_Utils_Mongo::getDateBoundQuery($time);
		$query['key'] = $key;
		
		$tax = $taxCollection->query($query)->cursor()->limit(1)->current();
		return !$tax->isEmpty() ? $tax : false;
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
		if (isset($taxedLine['tax_data']['total_amount'])) {
			return $taxedLine['tax_data']['total_amount'];
		}

		$taxCalc = new self();
		$taxRate = $taxCalc->getLineTaxRate($taxedLine);
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

	public static function getTaxCollection() {
		return Billrun_Factory::db()->taxesCollection();
	}


	//------------------- Entity Getter functions ----------------------------------------------------
	
	protected function getCollection($params = []) {
		return self::getTaxCollection();
	}

	protected function getFilters($row = [], $params = []) {
		return Billrun_Factory::config()->getConfigValue('tax.mapping', []);
	}
	
	//------------------- Entity Getter functions - END ----------------------------------------------

}

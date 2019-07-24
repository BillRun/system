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
 * @since 5.10
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
	 * @return array with category as key, Mongodloid_Entity as value if found, false otherwise
	 */
	public function getLineTaxes($line) {
		$taxHint = $this->getLineTaxHint($line);
		$taxes = $this->getLineTaxHintOverrideData($line, $taxHint);
		
		if ($taxes === false) {
			return false;
		}
		
		$params = [
			'skip_categories' => array_keys($taxes),
		];
		
		$globalTaxes = $this->getMatchingEntitiesByCategories($line, $params);
		
		if ($globalTaxes !== false) {
			$taxes = array_merge($taxes, $globalTaxes);
		}
		
		$taxHintFallback = $this->getLineTaxHintFallbackData($line, $taxHint, $taxes);
		
		if ($taxHintFallback === false) {
			return false;
		}
		
		$taxes = array_merge($taxes, $taxHintFallback);
		
		if (empty($taxes)) {
			return false;
		}

		return array_filter($taxes, function($taxData) {
			return !empty($taxData);
		});
	}
	
	/**
	 * get tax hints of the givven line
	 * 
	 * @param array $line
	 * @return array
	 */
	protected function getLineTaxHint($line) {
		if ($line['usaget'] == 'flat') { // plan/service line
			$entity = $line;
		} else {
			$entity = Billrun_Rates_Util::getRateByRef($line['arate'] ?: null);
		}
		
		return !empty($entity['tax']) ? $entity['tax'] : $this->getDefaultTaxHint();
	}
    
    /**
     * get default tax hint for product/plan/service
     * 
     * @return array
     */
	protected function getDefaultTaxHint() {
		return [
			[
				'type' => 'vat',
				'taxation' => 'global',
			],
		];
	}
	
	/**
	 * get tax data of override taxation (hint tax calculated before general taxation)
	 * 
	 * @param array $line
	 * @param array $taxHint
	 * @return array with category as key, Mongodloid_Entity as value if found, false otherwise
	 */
	protected function getLineTaxHintOverrideData($line, $taxHint) {
		$ret = [];
		$time = $line['urt']->sec;
		
		foreach ($taxHint as $taxHintData) {
			$category = $taxHintData['type'] ?: '';
			
			switch ($taxHintData['taxation']) {
				case 'no':
					$ret[$category] = [];
					break;
				case 'default':
					$ret[$category] = self::getDetaultTax($time);
					break;
				case 'custom':
					if ($taxHintData['custom_logic'] == 'override') {
						$ret[$category] = self::getTaxByKey($taxHintData['custom_tax'], $time);
						break;
					}
				default:
					continue;
			}
			
			if (isset($ret[$category]) && $ret[$category] === false) {
				return false;
			}
		}
		
		return $ret;
	}
	
	/**
	 * get tax data of fallback taxation (hint tax calculated after general taxation)
	 * 
	 * @param array $line
	 * @param array $taxHint
	 * @param array $taxes - taxes that were found on general taxation calculation
	 * @return array with category as key, Mongodloid_Entity as value if found, false otherwise
	 */
	protected function getLineTaxHintFallbackData($line, $taxHint, $taxes = []) {
		$ret = [];
		$time = $line['urt']->sec;
		
		foreach ($taxHint as $taxHintData) {
			$category = $taxHintData['type'] ?: '';
			
			if (isset($taxes[$category])) {
				continue;
			}
			
			if ($taxHintData['taxation'] == 'custom' && $taxHintData['custom_logic'] == 'fallback') {
				$ret[$category] = self::getTaxByKey($taxHintData['custom_tax'], $time);
				if (empty($ret[$category])) {
					return false;
				}
			}
		}
		
		return $ret;
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
	
	/**
	 * see parent::isLineTaxable
	 */
	protected function isLineTaxable($line) {
		$rate = $this->getRateForLine($line);
		foreach (Billrun_Util::getIn($rate, 'tax', []) as $tax) {
			if ($tax['type'] == 'vat') {
				if ($tax['taxation'] == 'no') {
					return !$this->isLinePreTaxed($line);
				}
				
				return true;
			}
		}
		
		return parent::isLineTaxable($line);
	}

	//================================= Static =================================

	/**
	 * get system's default tax
	 * 
	 * @return Mongodloid_Entity
	 */
	public static function getDetaultTax($time = null) {
		$taxKey = Billrun_Factory::config()->getConfigValue('taxation.default.key', '');
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
		
		return $untaxedPrice + $taxAmount;
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
		return Billrun_Factory::config()->getConfigValue('taxation.mapping', []);
	}
	
	protected function getDefaultEntity($categoryFilters, $category = '', $row = [], $params = []) {
		$time = isset($row['urt']) ? $row['urt']->sec : time();
		return self::getDetaultTax($time);
	}
	
	//------------------- Entity Getter functions - END ----------------------------------------------

}

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
	use Billrun_Traits_ForeignFields;
	protected static $taxes = [];

	/**
	 * @see Billrun_Calculator_Tax::updateRowTaxInforamtion
	 */
	public function updateRowTaxInforamtion($line, $subscriber, $account, $params = []) {
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
		if (isset($line['taxes'])) {
			return $line['taxes'];
		}
		$taxes = $this->getMatchingEntitiesByCategories($line);
		
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
		
		if (empty($taxes)) {
			return is_array($taxes) ? [] : false;
		}

		return array_filter($taxes, function($taxData) {
			return !empty($taxData);
		});
	}
	
	/**
	 * get tax hints of the givven line
	 * 
	 * @param array $line
	 * @param string $category - specific category to fetch, empty to get all categories
	 * @return array
	 */
	protected function getLineTaxHint($line, $category = '') {
		if ($line['usaget'] == 'flat') { // plan/service line
			$entity = $line;
		} else {
			$entity = Billrun_Rates_Util::getRateByRef($line['arate'] ?: null);
		}
		
		$taxHints = !empty($entity['tax']) ? $entity['tax'] : $this->getDefaultTaxHint();
		if (empty($category)) {
			return $taxHints;
		}
		
		foreach ($taxHints as $taxHint) {
			$taxHintCategory = $taxHint['type'] ?: '';
			if ($taxHintCategory == $category) {
				return $taxHint;
			}
		}
		
		return false;
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
	 * get row's tax data 
	 * 
	 * @param array $line
	 */
	protected function getRowTaxData(&$line) {
		if (isset($line['tax_data'])) {
			return $line['tax_data'];
		}
		
		$taxes = $this->getLineTaxes($line);
		if ($taxes === false) {
			return false;
		}
		
		$totalTax = 0;
		$totalAmount = 0;
		$totalEmbeddedAmount = 0;
		$taxesData = [];

		foreach ($taxes as $taxCategory => $tax) {
			$isTaxEmbedded = isset($tax['embed_tax']) ? $tax['embed_tax'] : false;
			$taxFactor = $tax['rate'];
			$taxAmount = $line['aprice'] * $taxFactor;
			$foreignTaxData = $this->getForeignFields(array('tax' => $tax));
			$taxData = array_merge([
				'tax' => $taxFactor,
				'amount' => !$isTaxEmbedded ? $taxAmount : 0,
				'description' => $tax['description'] ?: 'VAT',
				'key' => $tax['key'],
				'type' => $taxCategory,
				'pass_to_customer' => 1,
			], $foreignTaxData);

			if ($isTaxEmbedded) {
				$taxData['embedded_amount'] = $taxAmount;
				$line['aprice'] += $taxAmount;
				$totalEmbeddedAmount += $taxAmount;
			} else {
				$totalAmount += $taxAmount;
				$totalTax += $taxFactor;
			}
			
			$taxesData[] = $taxData;
		}
		
		$ret = [
			'total_amount' => $totalAmount,
			'total_tax' => $totalTax,
			'taxes' => $taxesData,
		];
		
		if ($totalEmbeddedAmount > 0) {
			$ret['total_embedded_amount'] = $totalEmbeddedAmount;
		}

		return $ret;
	}

	/**
	 * Converts tax data of a given line to taxes array that can be used in "getRowTaxData"
	 * 
	 * @param array $taxData
	 * @return array
	 */
	public static function taxDataToTaxes($taxData) {
		$taxes = [];
		
		foreach (Billrun_Util::getIn($taxData, 'taxes', []) as $data) {
			$category = $data['type'];
			$taxes[$category] = [
				'key' => $data['key'],
				'rate' => $data['tax'],
				'description' => $data['description'] ?: 'VAT',
			];

			$foreignFields = Billrun_Utils_ForeignFields::getForeignFields('tax');
			foreach ($foreignFields as $foreignField) {
				$fieldName = Billrun_Util::getIn($foreignField, 'foreign.field', '');
				if (!empty($data[$fieldName])) {
					$taxes[$category][$fieldName] = $data[$fieldName];
				}
			}
		}
		
		return $taxes;
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
		if (is_null($time)) {
			$time = time();
		}
		
		$tax = false;
		if (!empty(self::$taxes[$key])) {
			foreach (self::$taxes[$key] as $cachedTax) {
				$from = $cachedTax['from']->sec;
				$to = isset($cachedTax['to']) ? $cachedTax['to']->sec : null;
				if ($from <= $time && (is_null($to) || $to >= $time)) {
					$tax = $cachedTax;
					break;
				}
			}
		}
		
		if (empty($tax)) {
			$taxCollection = self::getTaxCollection();
			$query = Billrun_Utils_Mongo::getDateBoundQuery($time);
			$query['key'] = $key;
			$tax = $taxCollection->query($query)->cursor()->limit(1)->current();
			
			if (!$tax->isEmpty()) {
				self::$taxes[$key][] = $tax;
			}
		}
		return $tax && !$tax->isEmpty() ? $tax : false;
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
	
	public function getCollection($params = []) {
		return self::getTaxCollection();
	}

	public function getFilters($row = [], $params = []) {
		return Billrun_Factory::config()->getConfigValue('taxation.mapping', []);
	}
	
	protected function getDefaultEntity($categoryFilters, $category = '', $row = [], $params = []) {
		$time = isset($row['urt']) ? $row['urt']->sec : time();
		return self::getDetaultTax($time);
	}
	
	/**
	 * get tax data of fallback taxation (hint tax calculated after general taxation)
	 * 
	 * @param array $categoryFilters
	 * @param string $category
	 * @param array $row
	 * @param array $params
	 * @return Mongodloid_Entity if found, false otherwise
	 */
	protected function getFallbackEntity($categoryFilters, $category = '', $row = [], $params = []) {
		$time = isset($row['urt']) ? $row['urt']->sec : time();
		$taxHintData = $this->getLineTaxHint($row, $category);
		
		if (empty($taxHintData)) {
			return false;
		}
		
		if ($taxHintData['taxation'] == 'custom' && $taxHintData['custom_logic'] == 'fallback') {
			return self::getTaxByKey($taxHintData['custom_tax'], $time);
		}
		
		return false;
	}
	
	/**
	 * get tax data of override taxation (hint tax calculated before general taxation)
	 * 
	 * @param array $categoryFilters
	 * @param string $category
	 * @param array $row
	 * @param array $params
	 * @return Mongodloid_Entity if found, false otherwise
	 */
	protected function getOverrideEntity($categoryFilters, $category = '', $row = [], $params = []) {
		$time = isset($row['urt']) ? $row['urt']->sec : time();
		$taxHintData = $this->getLineTaxHint($row, $category);
		
		if (empty($taxHintData)) {
			return false;
		}
			
		switch ($taxHintData['taxation']) {
			case 'default':
				return self::getDetaultTax($time);
			case 'custom':
				if ($taxHintData['custom_logic'] == 'override') {
					return self::getTaxByKey($taxHintData['custom_tax'], $time);
				}
		}
		
		return false;
	}
	
	public function shouldSkipCategory($category = '', $row = [], $params = []) {
		$taxHintData = $this->getLineTaxHint($row, $category);
		
		if (empty($taxHintData)) {
			return false;
		}
		
		if ($taxHintData['taxation'] == 'no') {
			return true;
		}
		
		return false;
	}


	//------------------- Entity Getter functions - END ----------------------------------------------

}

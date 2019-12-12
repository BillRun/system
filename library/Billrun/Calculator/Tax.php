<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Tax
 *
 * @author eran
 */
abstract class Billrun_Calculator_Tax extends Billrun_Calculator {

	static protected $type = 'tax';
	
	protected $config = array();
	protected $nonTaxableTypes = array();
	
	/**
	 * timestamp of minimum row time that can be calculated
	 * @var int timestamp
	 */
	protected $billrun_lower_bound_timestamp = 0;

	/**
	 * Minimum possible billrun key for newly calculated lines
	 * @var string 
	 */
	protected $active_billrun;
	public function __construct($options = array()) {
		parent::__construct($options);
		$this->config = Billrun_Factory::config()->getConfigValue('taxation',array());
		$this->nonTaxableTypes = Billrun_Factory::config('taxation.non_taxable_types', array());
		$this->months_limit = Billrun_Factory::config()->getConfigValue('pricing.months_limit', 0);
		$this->billrun_lower_bound_timestamp = strtotime($this->months_limit . " months ago");
		$this->active_billrun = Billrun_Billrun::getActiveBillrun();
	}

	public function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorUpdateRow', array(&$row, $this));
		$current = $row instanceof Mongodloid_Entity ? $row->getRawData() : $row;
		if (!$this->isLineTaxable($current)) {
			$newData = $this->updateNonTaxableRowTaxInformation($current);
		} else {
			if( $problemField = $this->isLineDataComplete($current) ) {
				Billrun_Factory::log("Line {$current['stamp']} is missing/has illigeal value in fields ".  implode(',', $problemField). ' For calcaulator '.$this->getType() );
				return FALSE;
			}
			$subscriberSearchData = ['sid'=>$current['sid'],'time'=>date('Ymd H:i:sP',$current['urt']->sec)];
			$accountSearchData = ['aid'=>$current['aid'],'time'=>date('Ymd H:i:sP',$current['urt']->sec)];
			$newData = $this->updateRowTaxInforamtion($current, $subscriberSearchData, $accountSearchData);
		}
		
			//If we could not find the taxing information.
			if($newData == FALSE) {
				return FALSE;
			}
		
		if($row instanceof Mongodloid_Entity ) {
			$row->setRawData($newData);
		} else {
			$row = $newData;
		}
		if($this->isLinePreTaxed($current)) {
			$row['final_charge']  = $this->getLinePriceToTax($current);
		} else {
			$row['final_charge']  = $row['tax_data']['total_amount'] + $row['aprice'];
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculatorUpdateRow', array(&$row, $this));
		return $row;
	}

	/**
	 * stab function The  will probably  be no need to prepare data for taxing
	 * @param type $lines
	 * @return nothing
	 */
	public function prepareData($lines) { }

	
	//================================= Static =================================
	/**
	 *  Get  the  total amount with taxes  for a given line
	 * @param type $taxedLine a line *after* taxation  was applied to it.
	 * @return float the  price of the line including taxes
	 *				 or the same value if the tax could not be calcualted without the taxedLine
	 */
	public static function addTax($untaxedPrice, $taxedLine = NULL) {
		return $untaxedPrice + Billrun_Util::getFieldVal($taxedLine['tax_data']['tax_amount'],0);
	}

	/**
	 *  Remove the taxes from the total amount with taxes for a given line
	 * @param type $taxedLine a line *after* taxation  was applied to it.
	 * @return float the price of the line including taxes \
	 *				 or the same value if the tax could not be calcualted without the taxedLine
	 */
	public static function removeTax($taxedPrice, $taxedLine = NULL) {
		return $taxedPrice - Billrun_Util::getFieldVal($taxedLine['tax_data']['tax_amount'],0);
	}

	/**
	 * Check if the  line is pre taxed
	 * @param $line  The Usage/Service/Plan CDR  to check for being  pretexed
	 * return TRUE if the line/CDR is pre taxed  FALSE otherwise
	 */
	 public static function isLinePreTaxed($line) {
		$usageType = $line['usaget'];
		$prepricedMapping = @Billrun_Factory::config()->getFileTypeSettings($line['type'], true)['pricing'];

		return !empty($prepricedMapping[$usageType]['tax_included']);
	 }
	
	//================================ Protected ===============================	

	/**
	 * Get the price value to be used for taxation in the CDR
	 *
	 * @param  $line the  line to  retrive the  price  from.
	 * @return price The price that ins found in the line if not found then FALSE is returned.
	 */
	protected function getLinePriceToTax($line) {
		if($this->isLinePreTaxed($line)) {
			$userFields = $line['uf'];
			$usageType = $line['usaget'];
			$prepricedMapping = Billrun_Factory::config()->getFileTypeSettings($line['type'], true)['pricing'];
			$apriceField = isset($prepricedMapping[$usageType]['aprice_field']) ? $prepricedMapping[$usageType]['aprice_field'] : null;
			$aprice = Billrun_util::getIn($userFields, $apriceField);
			if (!is_null($aprice) && is_numeric($aprice)) {
				$apriceMult = isset($prepricedMapping[$usageType]['aprice_mult']) ? $prepricedMapping[$usageType]['aprice_mult'] : null;
				if (!is_null($apriceMult) && is_numeric($apriceMult)) {
					$aprice *= $apriceMult;
				}
				return $aprice;
			}
		}

		if(!isset($line['aprice'])) {
			Billrun_Factory::log("Line {$line['stamp']} has no pricing field legitimate for taxation", Zend_Log::ALERT);
		}
		return $line['aprice'] ?: FALSE;
	}

	/**
	 * Retrive all queued lines except from those that are configured not to be retrived.
	 * @return type
	 */
	protected function getLines() {
		return $this->getQueuedLines( array( 'type' => array( '$nin' => $this->nonTaxableTypes ) ) );
	}

	public function getCalculatorQueueType() {
		return 'tax';
	}

	public function isLineLegitimate($line) {
		return (empty($line['skip_calc']) || !in_array(static::$type, $line['skip_calc'])) && 
			$line['urt']->sec >= $this->billrun_lower_bound_timestamp;
	}	
	
	protected function isLineTaxable($line) {
		$rate = $this->getRateForLine($line);
		return  (!isset($rate['vatable']) || (!empty($rate['vatable']) && !$this->isLinePreTaxed($line)));
	}
	
	protected function isLineDataComplete($line) {
		$missingFields = array_diff( array('aid'), array_keys($line) );
		return empty($missingFields) ? FALSE : $missingFields;
	}

	/**
	 * Update the line/row with it related taxing data.
	 * @param array $line The line to update it data.
	 * @param array $subscriber  the subscriber that is associated with the line
	 * @return array updated line/row with the tax data
	 */
	abstract protected function updateRowTaxInforamtion($line, $subscriberSearchData, $accountSearchData);
	/**
	 * Update the non-taxable/pre-taxed line/row with it related taxing data.
	 * @param array $line The line to update it data.
	 */
	protected function updateNonTaxableRowTaxInformation($line) {
		$newData = $line;
		$newData['final_charge'] = $newData['aprice'];
		$taxData = $this->isLinePreTaxed($newData) ? $this->getPreTaxedRowTaxData($newData) : $this->getNonTaxableRowTaxData($newData);

		if ($taxData == false) {
			return false;
		}
		
		$newData['tax_data'] = $taxData;
		return $newData;
	}
	
	/**
	 * gets tax information for pre taxed line
	 * 
	 * @param array $line
	 * @return array
	 */
	protected function getPreTaxedRowTaxData($line) {
		$taxFactor = Billrun_Billrun::getVATByBillrunKey($this->active_billrun);
		return [
			'total_amount' => $line['aprice'] * $taxFactor,
			'total_tax' => $taxFactor,
			'taxes' => [
				'tax' => $taxFactor,
				'amount' => $line['aprice'] * $taxFactor,
				'description' => Billrun_Factory::config()->getConfigValue('taxation.vat_label', 'VAT'),
				'pass_to_customer' => 1,
			],
		];
	}

	/**
	 * gets tax information for non-taxable line
	 * 
	 * @param array $line
	 * @return array
	 */
	protected function getNonTaxableRowTaxData($line) {
		return [
			'total_amount' => 0,
			'total_tax' => 0,
			'taxes' => [],
		];
	}

	protected function getRateForLine($line) {
		$rate = FALSE;
		if(!empty($line['arate'])) {
			$rate = $this->getRateByRef($line['arate'])->getRawData();
		} else {
			$flatRate = $line['type'] == 'flat' ?
				new Billrun_Plan(array('name'=> $line['name'], 'time'=> $line['urt']->sec)) : 
				new Billrun_Service(array('name'=> $line['name'], 'time'=> $line['urt']->sec));
			$rate = $flatRate->getData();
		}
		return $rate;
	}
	//TODO ====   Temporary HACK 20191212 : this should be moved to Billrun_Util::getRateByRef($rate_ref) ==========
	protected $ratesCache=[];
	/**
	 * Get a rate by reference
	 * @param type $rate_ref
	 * @return type
	 */
	public function getRateByRef($rate_ref) {
		$refStr= $rate_ref['$ref'].$rate_ref['$id'];
		if(!isset($this->ratesCache[$refStr])) {
			$rates_coll = Billrun_Factory::db()->ratesCollection();
			$this->ratesCache[$refStr] = $rates_coll->getRef($rate_ref);
		}
		return $this->ratesCache[$refStr];
	}
	//======================
}

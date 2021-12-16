<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ThirdPartyTax
 *
 * @author eran
 */
class Billrun_Calculator_Tax_Singletax extends Billrun_Calculator_Tax {

	public function __construct($options = array()) {
		parent::__construct($options);
		$this->tax = Billrun_Billrun::getVATByBillrunKey(Billrun_Billrun::getActiveBillrun());
	}
	
	public function updateRowTaxInforamtion($line, $subscriberSearchData, $accountSearchData, $params = []) {
		
		$line['tax_data'] = array(
								'total_amount'=> $line['aprice'] * $this->tax,
								'total_tax' => $this->tax,
								'taxes' =>  array(
										array('tax'=> $this->tax, 'amount' => $line['aprice'] * $this->tax, 'description' => Billrun_Factory::config()->getConfigValue('taxation.vat_label', 'Vat') , 'pass_to_customer'=> 1 )
									)
								);
		
		$line['final_charge'] = $line['tax_data']['total_amount'] + $line['aprice'];
		
		return $line;
	}
	
	//================================= Static =================================
	/**
	 * @see Billrun_Calculator_Tax::addTax
	 * 
	 * @param type $untaxedPrice
	 */
	public static function addTax($untaxedPrice, $taxedLine = NULL) {
		$defaultTax = $untaxedPrice * Billrun_Billrun::getVATByBillrunKey(Billrun_Billrun::getActiveBillrun());
		return $untaxedPrice + Billrun_Util::getFieldVal($taxedLine['tax_data']['tax_amount'], $defaultTax );
	}

	/**
	 *  @see Billrun_Calculator_Tax::removeTax
	 */
	public static function removeTax($taxedPrice, $taxedLine = NULL) {
		$defaultTax = $taxedPrice - ($taxedPrice / (1 + Billrun_Billrun::getVATByBillrunKey(Billrun_Billrun::getActiveBillrun())));
		return $taxedPrice - Billrun_Util::getFieldVal(	$taxedLine['tax_data']['tax_amount'], $defaultTax );
	}
}
	

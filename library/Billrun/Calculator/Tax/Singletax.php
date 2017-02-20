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
	
	protected function updateRowTaxInforamtion($line, $subscriber, $account) {
		
		$line['tax_data'] = array(
								'total_amount'=> $line['aprice'] * $this->tax,
								'total_tax' => $this->tax,
								'taxes' =>  array(
										array('tax'=> $this->tax, 'amount' => $line['aprice'] * $this->tax, 'description' => "Vat" , 'pass_to_customer'=> 1 )
									)
								);
		return $line;
	}
}
	
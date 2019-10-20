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
class Billrun_Calculator_Tax_Thirdpartytaxing extends Billrun_Calculator_Tax {
	

	protected $taxDataResults = array();
    protected $thirdpartyConfig = array();
        
    public function __construct($options = array()) {
		parent::__construct($options);
		$this->thirdpartyConfig = Billrun_Util::getFieldVal($this->config[$this->config['tax_type']],array());
	}

	public static function isConfigComplete($config) {
		return true;
	}
	
	protected function updateRowTaxInforamtion($line, $subscriberSearchData, $accountSearchData) {
		$subscriber = Billrun_Factory::subscriber();
		$subscriber->loadSubscriber($subscriberSearchData);
		$account = Billrun_Factory::account();
		$account->loadAccount($accountSearchData);

		Billrun_Factory::dispatcher()->trigger('onUpdateRowTaxInforamtion', array(&$line, $subscriber, $account, &$this));
		Billrun_Factory::dispatcher()->trigger('onAddManualTaxationToRow', array(&$line, $subscriber, $account, &$this));
		
		return $line;
	}
}

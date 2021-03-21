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
	
	public function updateRowTaxInforamtion($line, $subscriberSearchData, $accountSearchData, $params = []) {
		if (!empty($params['pretend'])) {
			$subscriber = $subscriberSearchData;
			$accountData = $accountSearchData;
		} else {
			$subscriber = Billrun_Factory::subscriber();
			$subscriber->loadSubscriberForQuery($subscriberSearchData);
			$account = Billrun_Factory::account();
			$account->loadAccountForQuery($accountSearchData);
			$accountData = $account->getCustomerData();
		}

		Billrun_Factory::dispatcher()->trigger('onUpdateRowTaxInforamtion', array(&$line, $subscriber, $accountData, &$this));
		Billrun_Factory::dispatcher()->trigger('onAddManualTaxationToRow', array(&$line, $subscriber, $accountData, &$this));
		
		return $line;
	}
}

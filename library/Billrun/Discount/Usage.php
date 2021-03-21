<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Billrun_Discount_Usage extends Billrun_Discount_Subscriber {
      
    public function __construct($discountRate, $eligibilityOnly = FALSE ) {
        parent::__construct($discountRate, $eligibilityOnly);
        $this->discountableUsageTypes = Billrun_Factory::config()->getConfigValue('discounts.usage.usage_types', Billrun_Factory::config()->getFileTypes());
        $this->discountableSections = Billrun_Factory::config()->getConfigValue('discounts.usage.section_types',array('out_plan'=>'usage','over_plan'=>'usage'));
    }
	
	protected function checkServiceEligiblity($service, $account, $billrun) {
		 $eligiblityData = parent::checkServiceEligiblity($service, $account, $billrun);
		 
		 return !empty($eligiblityData) && $this->serviceHasEligibleUsage($billrun, $service) ? $eligiblityData : FALSE;
	}
    
	protected function serviceHasEligibleUsage($billrun, $service) {
		 $subData = $billrun->getSubRawData($service['sid']);
		 if($this->eligibilityOnly) {
			 return TRUE;  //In case this is a query  through the API.
		 }
		foreach(Billrun_Util::getFieldVal($subData['breakdown'],array()) as $section => $types) {
            if( !isset($this->discountableSections[$section]) ) {
                continue;
            }
            foreach($types as $type => $typedUsages) {
                if( !in_array($type,  $this->discountableUsageTypes) ) {
                    continue;
                }
                foreach($typedUsages as $vat => $usages) {
                    foreach($usages as $invoiceName => $usage) {
                        if( !empty(array_intersect($usage['keys'], $this->discountData['filter_keys'])) ) { 
                             return TRUE;
                        }
                    }
                }
            }
        }
		return FALSE;
	}

}
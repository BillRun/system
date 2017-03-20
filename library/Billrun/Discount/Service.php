<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing discount class
 *
 * @package  Discounts
 * @since    2.8
 */
class Billrun_Discount_Service extends Billrun_Discount {

	/**
	 * on filtered  totals  discounts this array hold the breakdown sections  that should be included in the discount.
	 * @var type  array
	 */
	protected $discountableSections = array();
	
	public function __construct($discountRate, $eligibilityOnly = FALSE ) {
		parent::__construct($discountRate, $eligibilityOnly );
		$this->discountableSections = Billrun_Factory::config()->getConfigValue('discounts.service.section_types',array('flat'=>'flat','switched'=>'flat'));
		$this->discountableUsageTypes = Billrun_Factory::config()->getConfigValue('discounts.service.usage_types',array('usage'));
	}
	
	/**
	 * Check a single discount if an account is eligible to get it.
	 * (TODO change this hard coded logic to something more flexible)
	 * @param type $accountInvoice the account data to check the discount against	 
	 */
	public function checkEligibility($accountInvoice) {
		$ret = array();
		$this->billrunDate = static::getBillrunDate($accountInvoice->getBillrunKey());
		$this->billrunStartDate = Billrun_Billingcycle::getStartTime($accountInvoice->getBillrunKey());		
                foreach ($accountInvoice->getSubscribers() as $billableService) {
                        if ($eligibles = $this->checkServiceEligiblity($billableService, $accountInvoice)) {
                                $ret = array_merge($ret, $eligibles);
                        }
                }
                foreach ($accountInvoice->getSubscribers() as $billableService) {
                        if ($eligibles = $this->getTerminatedDiscounts($billableService, $accountInvoice)) {
                                $ret = array_merge($ret, $eligibles);
                        }
                }

		if ($ret) {
			return $ret;
		}
		return FALSE;
	}

	
	public function checkTermination($accountBillrun) {
		return array();
	}
	
	protected function checkServiceEligiblity($subscriber, $accountInvoice) {		
		$eligible = !empty(@Billrun_Util::getFieldVal($this->discountData['params'], array()));
		$multiplier = 1;
		$switch_date = $end_date = null;
                $subscriberData =  $subscriber->getData();
                $addedData = array('aid' => $accountInvoice->getRawData()['aid'], 'sid' => $subscriberData['sid']);
		foreach (@Billrun_Util::getFieldVal($this->discountData['params'], array()) as $key => $values) {
                    $eligible &= isset($subscriberData[$key])  && $subscriberData[$key] == $values 
                                    || 
                                isset($service['breakdown'][$key]) && $service['breakdown'][$key] == $values;
		}

		$ret = array(array_merge(array('modifier' => $multiplier, 'start_date' => $switch_date, 'end_date' => $end_date), $addedData));
		
		return $eligible ? $ret : FALSE;
	}
	
	protected function getDefaultEligibilityData($account, $service, $multiplier, $end_date, $switch_date) {
		$start_date = $this->billrunStartDate;
		if ($this->billrunStartDate < @Billrun_Util::getFieldVal($service['switch_date'], 0) && $service['switch_date'] <= $this->billrunDate) {
			$start_date = $switch_date = max($switch_date, $service['switch_date'], $this->billrunStartDate);
		}
		if (@Billrun_Util::getFieldVal($account['end_date'], PHP_INT_MAX) < $this->billrunDate) {
			$end_date = min(Billrun_Util::getFieldVal($end_date, PHP_INT_MAX), $account['end_date']);
		}
		$multiplier = (!empty($end_date) ? 0 : 1) + ///add next month discount
			(!empty($switch_date) || !empty($end_date) ? max(0, min(Billrun_Util::calcPartialMonthMultiplier($start_date, $this->billrunDate, $this->billrunStartDate, $end_date), $multiplier)) : 0); //add prorataed discount
		return array($start_date, $switch_date, $end_date, $multiplier);
	}

        /**
	 * 
	 * @param type $accountOpts
	 * @param type $OptToFind
	 * @return boolean
	 */
	protected static function hasOptions($accountOpts, $OptToFind, $atDate = FALSE) {
		foreach ($accountOpts as $value) {
			if (@isset($value['key']) && (@$value['key'] == $OptToFind || is_array($OptToFind) && in_array(@$value['key'],$OptToFind)) ) {
				//Should we check the date of the option...
				if(!$atDate || (empty($value['start_date']) || $value['start_date'] <= $atDate) && ( empty($value['end_date']) || $atDate < $value['end_date']) ) {
					return TRUE;
				}
			}
		}
		return FALSE;
	}	

	/**
	 * 
	 * @param type $service
	 * @param type $discountParams
	 * @param type $switch_date
	 * @param type $multiplier
	 * @param type $startDate
	 * @param type $endDate
	 * @param type $sfieldRegex
	 * @return type
	 */
	protected function checkService($service, &$discountParams, $discount, $switch_date, $startDate, $endDate = null, $sfieldRegex = FALSE) {
		$tMultiplier = 1;
		$addedValues = array();
		foreach ($discountParams as $sfield => $val) {
			$orgSFlid = $sfield;
			if (!empty($sfieldRegex)) {
				$sfield = preg_replace($sfieldRegex['regex'], $sfieldRegex['replace'], $sfield);
			}
			if (!isset($service[$sfield])) {
				continue;
			}
			if (is_array($val)) {
				foreach ($val as $vk => $vs) {
					foreach ($vs as $vsk => $v) {
						if ((is_array($service[$sfield]) && in_array($v, $service[$sfield]) ) || $service[$sfield] == $v ||
							(is_array($service[$sfield]) && static::hasOptions($service[$sfield], $v))) {
							foreach (@Billrun_Util::getFieldVal($discount['domains'], array()) as $dmKey => $domains) {
								if (in_array($service[$sfield], $domains) && strstr($dmKey, 'required_with_commitment') !== FALSE && !static::serviceWithinCommitment($service)) {
									break 2;
								}
							}
							if (@Billrun_Util::getFieldVal($startDate, FALSE) && $this->billrunStartDate < $startDate && $startDate <= $this->billrunDate) {
								$tMultiplier = max(0, min(Billrun_Util::calcPartialMonthMultiplier($startDate, $this->billrunDate, $this->billrunStartDate, $endDate), $tMultiplier));
								if (empty($endDate) || $endDate > $this->billrunDate) {
									$tMultiplier += 1;
								}
								if (!empty($tMultiplier)) {									
									$switch_date = $startDate;
								}
							}

							if (!empty($tMultiplier)) {								
								if ($vk == 'optional') {
									$addedValues['multiplier'] = $tMultiplier;
									$addedValues['end_date'] = $endDate;
									$addedValues['start_date'] = $startDate;
									
								} else {
									unset($discountParams[$orgSFlid][$vk][$vsk]);
									if ($vk == 'required') {//HACK to fix BIL-515 for NPLAY_X discounts
										if(is_array($service[$sfield]) && static::hasOptions($service[$sfield], $v)) {
											$addedValues['by_option'] =1;
										} else {
											$addedValues['required'] = $tMultiplier;
										}
										$addedValues['multiplier'] = $tMultiplier;
										$addedValues['end_date'] =  $this->billrunDate > $endDate ? $endDate : "";			
										$addedValues['start_date'] = $this->billrunStartDate < $startDate ? $startDate : "";
										
										unset($discountParams[$orgSFlid][$vk]);
									}
								}
								if (!empty($discountParams[$orgSFlid][$vk])) {
									unset($discountParams[$orgSFlid][$vk]);
								}
								
								break 2;
							}
						}
					}
				}
				if (empty($discountParams[$orgSFlid])) {
					unset($discountParams[$orgSFlid]);
				}
			} else if (preg_match('/' . $val . '/', $service[$sfield])) {
				unset($discountParams[$sfield]);
			}
		}
		return $addedValues;
	}
	
	protected function isServiceOptional($service, $discountParams) {
		return !empty($discountParams['services']['next_plan']['optional']) && in_array($service, $discountParams['services']['next_plan']['optional']);
	}
	
	protected function getOptionalCDRFields() {
		return array('sid');
	}
	
	
	/**
	 * Get the totals of the current entity in the invoice. To be used before calculating the final charge of the discount
	 * @param Billrun_Billrun $billrunObj
	 * @param type $cdr
	 */
	public function getInvoiceTotals($billrunObj, $cdr) {
		return $billrunObj->getTotals($cdr['sid']);
	}
	
	public function getEntityId($cdr) {
		return 'sid' . $cdr['sid'];
	}
	
	
	/**
	 * Get all the discounts that were terminated during the month.
	 * @param type $account
	 * @param type $billrun
	 */
	 public function getTerminatedDiscounts($service, $accountInvoice) {
		$billrunDate = static::getBillrunDate($accountInvoice->getBillrunKey());
		$billrunStartDate = Billrun_Billingcycle::getStartTime($accountInvoice->getBillrunKey());
		$terminatedDiscounts=array();
		//TODO implement only for up front discounts
		return  empty($terminatedDiscounts) ? FALSE : $terminatedDiscounts;
	}

	protected function getTotalsFromBillrun($billrun,$entityId) {
        if(empty($this->discountData['filter_keys'])) {
            return parent::getTotalsFromBillrun($billrun, $entityId);
        }
            
        $usageTotals = array('after_vat'=> 0,'before_vat'=> 0,'usage'=>[], 'flat' => [], 'miscellaneous' => [] );
        $subData = $billrun->getSubRawData($entityId);
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
						
                        if( !empty($usage['keys']) && !empty(array_intersect($usage['keys'], $this->discountData['filter_keys'])) ) { 
                                $usageTotals[$this->discountableSections[$section]][$vat] = Billrun_Util::getFieldVal($usageTotals[$this->discountableSections[$section]][$vat], 0) +  $usage['cost']; 
                                $usageTotals['after_vat'] += $usage['cost'] * ( 1 + ($vat/100) ); 
                                $usageTotals['before_vat'] += $usage['cost'];
                        }
                    }
                }
            }
        }
        return $usageTotals;
    }
}

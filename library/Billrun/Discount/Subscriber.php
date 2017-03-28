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
class Billrun_Discount_Subscriber extends Billrun_Discount {


    public function __construct($discountRate, $eligibilityOnly = FALSE) {
        parent::__construct($discountRate, $eligibilityOnly);
        $this->discountableSections = Billrun_Factory::config()->getConfigValue('discounts.service.section_types', array('flat' => 'flat', 'service' => 'service'));
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


        if ($ret) {
            return $ret;
        }
        return FALSE;
    }

    public function checkTermination($accountBillrun) {
		$ret = array();
//        foreach ($accountInvoice->getSubscribers() as $billableService) {
//            if ($eligibles = $this->getTerminatedDiscounts($billableService, $accountInvoice)) {
//                $ret = array_merge($ret, $eligibles);
//            }
//        }
		 if (!empty($ret)) {
            return $ret;
        }
        return FALSE;
    }

    protected function checkServiceEligiblity($subscriber, $accountInvoice) {
        $eligible = !empty(@Billrun_Util::getFieldVal($this->discountData['params'], array()));
        $multiplier = 1;
        $startDate = $endDate = null;
        $subscriberData = $subscriber->getData();
        $addedData = array('aid' => $accountInvoice->getRawData()['aid'], 'sid' => $subscriberData['sid']);
        foreach (@Billrun_Util::getFieldVal($this->discountData['params'], array()) as $key => $values) {
            $eligible &= isset($subscriberData[$key]) && $subscriberData[$key] == $values ||
                    isset($subscriberData['breakdown'][$key]) && is_array($values) && count($values) == count(array_intersect(array_map(function($a) { return $a['name']; }, $subscriberData['breakdown'][$key]), $values));
        }
		$endDate = $this->adjustDiscountDuration($accountInvoice->getRawData(), $multiplier, $subscriberData);
        $ret = array(array_merge(array('modifier' => $multiplier, 'start_date' => $startDate, 'end_date' => $endDate), $addedData));

        return $eligible ? $ret : FALSE;
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
        $terminatedDiscounts = array();
        //TODO implement only for up front discounts
        return empty($terminatedDiscounts) ? FALSE : $terminatedDiscounts;
    }

	/**
	 * Create  a totals structure out of fileds that  are  supported by the discount
	 * @param type $billrun
	 * @param type $entityId
	 * @return type
	 */
    protected function getTotalsFromBillrun($billrun, $entityId) {
        if (empty($this->discountData['discount_subject'])) {
            return parent::getTotalsFromBillrun($billrun, $entityId);
        }

        $usageTotals = array('after_vat' => 0, 'before_vat' => 0, 'usage' => [], 'flat' => [], 'miscellaneous' => []);
        foreach ($billrun->getSubscribers() as $sub) {
            $subData = $sub->getData();
            if ($subData['sid'] == $entityId) {
                break;
            }
        }
        foreach (Billrun_Util::getFieldVal($subData['breakdown'], array()) as $section => $types) {
            if (!isset($this->discountableSections[$section])) {
                continue;
            }
            foreach ($types as $type => $usage) {
                if (!empty($usage['name']) && (
                        isset($this->discountData['discount_subject']['service']) && in_array($usage['name'], array_keys($this->discountData['discount_subject']['service'])) ||
                        isset($this->discountData['discount_subject']['plan']) && in_array($usage['name'], array_keys($this->discountData['discount_subject']['plan']))
                        )) {
                    //$usageTotals[$this->discountableSections[$section]][$vat] = Billrun_Util::getFieldVal($usageTotals[$this->discountableSections[$section]][$vat], 0) +  $usage['cost']; 
                    $usageTotals['after_vat'] += $usage['cost'];
                    $usageTotals['before_vat'] += $usage['cost'];
                    @$usageTotals[$usage['name']] += $usage['cost'];
					@$usageTotals['sections'][$this->discountableSections[$section]] += $usage['cost'];
                }
            }
        }
        return $usageTotals;
    }

}

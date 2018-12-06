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
		$this->discountToQueryMapping =  array('plan' => 'breakdown.flat.*', 'service' => array('breakdown.service.$all' => array('*' => '*.name') ));
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


    //==================================== Protected ==========================================

	protected function priceManipulation($simpleDiscountPrice, $subjectValue, $subjectKey, $discountLimit ,$discount ) {
		$retPrice= $simpleDiscountPrice;
		foreach($this->discountData['discount_subject']['service'][$subjectKey]['operations'] as $operation) {
			switch($operation['name']) {
				case 'recurring_by_quantity':
						$quantityMultiplier = $discount[$operation['params']['name']][($this->isApplyToAnySubject() ? 'total' : $subjectKey)] % $operation['params']['name'];
						$retPrice = $retPrice + $retPrice * $quantityMultiplier;
					break;
			}
		}

		return max($retPrice,-$discountLimit);
	}

    protected function checkServiceEligiblity($subscriber, $accountInvoice) {
        $eligible = !empty(@Billrun_Util::getFieldVal($this->discountData['params'], array()));
        $multiplier = 1;
        $startDate = $endDate = null;
        $subscriberData = $subscriber->getData();
        $addedData = array('aid' => $accountInvoice->getRawData()['aid'], 'sid' => $subscriberData['sid']);
		$paramsQuery = $this->mapFlatArrayToStructure(@Billrun_Util::getFieldVal($this->discountData['params'],array()), $this->discountToQueryMapping);
		
		$eligible &=  Billrun_Utils_Arrayquery_Query::exists($subscriberData, $paramsQuery);
		
		$endDate = $this->adjustDiscountDuration($accountInvoice->getRawData(), $multiplier, $subscriberData);
        $ret = array(array_merge(array('modifier' => $multiplier, 'start' => $startDate, 'end' => $endDate), $addedData));

        return $eligible ? $ret : FALSE;
    }

    protected function isServiceOptional($service, $discountParams) {
        return !empty($discountParams['services']['next_plan']['optional']) && in_array($service, $discountParams['services']['next_plan']['optional']);
    }

    protected function getOptionalCDRFields() {
        return array('sid','start','end');
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
                        isset($this->discountData['discount_subject']['plan']) && in_array($usage['name'], array_keys($this->discountData['discount_subject']['plan'])) ||
                        $this->isApplyToAnySubject()
                        )) {
                    //$usageTotals[$this->discountableSections[$section]][$vat] = Billrun_Util::getFieldVal($usageTotals[$this->discountableSections[$section]][$vat], 0) +  $usage['cost']; 
                    $usageTotals['after_vat'] += $usage['cost'];
                    $usageTotals['before_vat'] += $usage['cost'];
                    @$usageTotals['rates'][$usage['name']] += $usage['cost'];
                    @$usageTotals['quantity'][$usage['name']] += $usage['usagev'];
                    @$usageTotals['quantity']['total'] += $usage['usagev'];
                    @$usageTotals['sections'][$this->discountableSections[$section]] += $usage['cost'];
					@$usageTotals['count'][$this->discountableSections[$section]] += $usage['usagev'];
                }
            }
        }
        return $usageTotals;
    }

	protected function mapFlatArrayToStructure($flatArray, $structuredArray) {
		if (!is_array($structuredArray)) {
			return $structuredArray;
		}
		$retArray = array();
		$structuredArrayKeys = array_keys($structuredArray);
		foreach ($flatArray as $key => $value) {
			if (isset($structuredArray[$key])) {
				if (is_array($structuredArray[$key])) {
					foreach ($structuredArray[$key] as $mapKey => $mapping) {
						$retArray[$mapKey] = $this->mapFlatArrayToStructure($value, $mapping);
					}
				} else {
					$retArray[$structuredArray[$key]] = $value;
				}
			} else if (count($structuredArray) == 1 && reset($structuredArrayKeys) == '*') {
				$retArray[$key] = array($this->mapFlatArrayToStructure($value, reset($structuredArray)) => $value);
			} else {
				$retArray[$key] = $value;
			}
		}

		return $retArray;
	}

}

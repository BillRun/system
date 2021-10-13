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

	protected static $cache;

    public function __construct($discountRate, $eligibilityOnly = FALSE) {
        parent::__construct($discountRate, $eligibilityOnly);
        $this->discountableSections = Billrun_Factory::config()->getConfigValue('discounts.service.section_types', array('flat' => 'flat', 'service' => 'service'));
		$this->discountToQueryMapping =  array('plan' => 'breakdown.flat.*', 'service' => array('breakdown.service.$all' => array('*' => '*.name') ));
		$cache = $this->initCache();
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

	public static function getByNameAndTime($name, $time) {
		if (isset(static::$cache['by_key'][$name])) {
			foreach (static::$cache['by_key'][$name] as $revision) {
				if ($revision['from'] <= $time && (!isset($revision['to']) || is_null($revision['to']) || $revision['to'] >= $time)) {
					return $revision;
				}
			}
		}
		$discountsColl = Billrun_Factory::db()->getCollection('discounts');
		$query = array_merge(['key' => $name], Billrun_Utils_Mongo::getDateBoundQuery($time->sec));
		$results = $discountsColl->query($query)->cursor()->current();
		if($results) {
			static::$cache['by_key'][$results['key']][] = $results->getRawData();
			return $results;
		}
		return false;
	}

    //==================================== Protected ==========================================

	protected function initCache() {
		if (empty(static::$cache)) {
			$this->cache = ['by_key' => []];
		}
	}
	
	protected function priceManipulation($simpleDiscountPrice, $subjectValue, $subjectKey, $discountLimit ,$totals ) {
		$retPrice= $simpleDiscountPrice;
		$pricingData = [];
		if( !empty($this->discountData['discount_subject']['service'][$subjectKey]['operations']) ) {
			foreach($this->discountData['discount_subject']['service'][$subjectKey]['operations'] as $operation) {
				switch($operation['name']) {
					case 'recurring_by_quantity':
							//Multiply the discount amount  by some intrval  over the quantity of the service.
							$quantityMultiplier = 0;
							foreach($operation['params'] as $param) {
								if(empty($param['value'])) { continue; }
								$quantityMultiplier += floor($totals[$param['name']][($this->isApplyToAnySubject() ? 'total' : $subjectKey)] / $param['value']);
							}
							$pricingData[] = ['name' => 'recurring_by_quantity', 'multiplier' => $quantityMultiplier , 'base_price' => $retPrice ];
							$retPrice = $retPrice * $quantityMultiplier;
						break;
						case 'unquantitative_amount':
							//retrive the original  service price form a quantitve service.
							$quantityMultiplier = 0;
							foreach($operation['params'] as $param) {
								$quantityMultiplier +=  $totals[$param['name']][($this->isApplyToAnySubject() ? 'total' : $subjectKey)];
							}
							$pricingData[] = ['name' => 'dequtitive_amount', 'multiplier' => $quantityMultiplier , 'base_price' => $retPrice ];
							$retPrice = $retPrice / $quantityMultiplier;
						break;
				}
			}
		}

		return [ 'price' => max(min(0,$retPrice),$discountLimit) , 'pricing_breakdown' => [ $subjectKey => $pricingData] ];
	}

    protected function checkServiceEligiblity($subscriber, $accountInvoice) {
        $eligible = !empty(@Billrun_Util::getFieldVal($this->discountData['params'], array()));

        $startDate = $endDate = null;
        $subscriberData = $subscriber->getData();
        $addedData = array('aid' => $accountInvoice->getRawData()['aid'], 'sid' => $subscriberData['sid']);
		$paramsQuery = $this->mapFlatArrayToStructure(@Billrun_Util::getFieldVal($this->discountData['params'],array()), $this->discountToQueryMapping);
		
		$eligible &=  Billrun_Utils_Arrayquery_Query::exists($subscriberData, $paramsQuery);
		$cover = [ 'start' => new MongoDate($this->billrunStartDate), 'end' => new MongoDate($this->billrunDate-1) ];
		if($eligible && !empty($this->discountData['prorated'])) {
			$arrayArggregator = new Billrun_Utils_Arrayquery_Aggregate();
			$matchedDocs = $arrayArggregator->aggregate([ ['$unwind' => '$breakdown.flat'],['$unwind' => '$breakdown.service'],['$project' => ['flat'=> ['$push'=>'$breakdown.flat'],'service'=>['$push'=>'$breakdown.service']]] ], [$subscriberData]);
			foreach($matchedDocs as $matchedDoc ) {
				foreach($matchedDoc as $type => $matchedType ) {
					foreach($matchedType as $matched) {
						if ($type == 'flat' && !empty($matched['name']) && !empty($this->discountData['params']['plan']) && $this->discountData['params']['plan'] != $matched['name']) {
							continue;
						}
						if ($type == 'service' && !empty($matched['name']) && !empty($this->discountData['params']['service']) && !in_array($matched['name'], $this->discountData['params']['service'])) {
							continue;
						}
						if(!empty($matched['start']) && $cover['start'] < $matched['start'] && $cover['end'] > $matched['start']) {
							$cover['start'] = $matched['start'];
						}
						if(!empty($matched['end']) && $cover['end'] > $matched['end'] && $cover['start'] < $matched['end']) {
							$cover['end'] = $matched['end'];
						}
					}
				}
			}
        }
        $startDate = $cover['start'];
        $multiplier = !empty($this->discountData['prorated']) ? Billrun_Utils_Time::getMonthsDiff(date('Ymd',$cover['start']->sec) ,date('Ymd',$cover['end']->sec)) : 1;
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

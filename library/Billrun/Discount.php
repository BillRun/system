<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract discount class
 *
 * @package  Discounts
 * @since    3.0
 */
abstract class Billrun_Discount {

	const SECS_IN_AN_YEAR = 31557600;

	/**
	 *
	 * @var array
	 */
	protected $discountData;

	protected $eligibilityOnly = FALSE;
	
	public function __construct($discountRate, $eligibilityOnly = FALSE ) {
		$this->discountData = $discountRate;
		$this->eligibilityOnly = $eligibilityOnly;
	}

	abstract public function checkEligibility($accountBillrun);
	abstract public function checkTermination($accountBillrun);

	public function generateCDRs($eligibleData, $accountInvoice) {
		$discountLines = array();
		$prcisn = 10000000;
		$discountsCount= 0;
		foreach ($eligibleData as $eligibleRow) {
			$discountsCount++;
			//Apply the maximum limit of the discount
			if(!empty($this->discountData['max_limit']) && $discountsCount > $this->discountData['max_limit']) {
				Billrun_Factory::log("Account {$eligibleRow['aid']} has reached its maximum limit for discount : {$this->discountData['key']}",Zend_Log::INFO);
				break;
			}
			$groupingId = rand(0, 1 << 31);
			$orgMOdifier = $modifier = $eligibleRow['modifier'];
			$quantity = !empty($eligibleRow['quantity']) ? $eligibleRow['quantity'] : 1;
			while (abs($modifier) >= 1 / ($prcisn * 10)) {
				$lineModifier = !empty($modifier * $prcisn % $prcisn) ? ($modifier * $prcisn % $prcisn) / $prcisn : ($modifier / abs($modifier));
				$modifier = round($modifier - $lineModifier, 3);
				if ($lineModifier != 1 && !empty($eligibleRow['switch_date'])) {
					$creationTime = $eligibleRow['switch_date'];
				} else {
					$creationTime = (!empty($accountInvoice) ? static::getBillrunDate($accountInvoice->getBillrunKey()) : time() );
				}
				$serviceType = $this->getDiscountVatType($accountInvoice);
				$vat = 0.1;//TODO  replace  with  actual tax
				$discountLine = array(
					'key' => $this->discountData['key'],
					'type' => 'credit',
					'invoice_label' => $this->discountData['description'],					
					'usaget' => 'discount',//TODO move to  disocunt rate data?
					'discount_type' => $this->discountData['discount_type'],
					'urt' => new MongoDate($creationTime),
					'creation_time' => $creationTime,
					'modifier' => $lineModifier,
					'orig_mod' => $orgMOdifier,
					'arate' => $this->discountData->createRef(Billrun_Factory::db()->ratesCollection()),
					'aid' => $eligibleRow['aid'],
					'source' => 'billrun',
					'billrun' => $accountInvoice->getBillrunKey(),
					'usagev' => $quantity,
				);
				foreach ($this->getOptionalCDRFields() as $field) {
					if (isset($eligibleRow[$field])) {
						$discountLine[$field] = $eligibleRow[$field];
					}
				}
				if (!empty($this->discountData['duration'])) {
					$discountLine['discount_duration'] = $this->discountData['duration'];
				}
                                foreach ($this->discountData['discount_subject'] as $subjectType => $subjects) {
                                    foreach ($subjects as $key => $val) {					
                                            if ($this->discountData['discount_type'] == 'monetary') {
                                                    $discountLine['discount'][$key]['value'] = -(abs($val)) * $lineModifier;						
                                            } else { //Calualte  Percent  avarage (not preceise but very close)
                                                    $discountLine['is_percent'] = true;//TODO change  all references to work with 'discount_type' field
                                                    $discountLine['discount'][$key]['value'] = $val;
                                            }
                                    }
                                }

				if (!empty($this->discountData['limit'])) {
					$discountLine['limit'] = $this->discountData['limit'] * $lineModifier;
				}

				if (!empty($eligibleRow)) {
					if (empty($eligibleRow['end_date'])) {
						unset($eligibleRow['end_date']);
					} else {
						$eligibleRow['end_date'] = new MongoDate($eligibleRow['end_date']);
					}
					if (empty($eligibleRow['start_date'])) {
						unset($eligibleRow['start_date']);
					} else if(is_numeric($eligibleRow['start_date'])){
						$eligibleRow['start_date'] = new MongoDate($eligibleRow['start_date']);
					}
					$discountLine = array_merge($eligibleRow, $discountLine);
					if ($lineModifier == 1) {
						unset($discountLine['switch_date']);
						unset($discountLine['start_date']);
					}
				}

				$discountLine['grouping'] = $groupingId;
				$discountLine['process_time'] = date(Billrun_Base::base_dateformat);
				if (!empty($accountInvoice)) {
					$discountLine['received_count'] = static::countReceivedDiscountsOfKey(null, $this->discountData['key'], $accountInvoice->getRawData()['aid']);
				}
								
				$discountLines[] = $discountLine;
			}
		}
		//Apply the minimum limit of the discount
		if(!empty($this->discountData['min_limit']) && $discountsCount < $this->discountData['min_limit']) {
			Billrun_Factory::log("Account {$eligibleRow['aid']} hasn't reached it minimum limit for discount : {$this->discountData['key']}",Zend_Log::INFO);
			return array();
		}
		
		return $discountLines;
	}

	/**
	 * returns the total discount value (charge) or FALSE on error
	 * @param type $discount
	 * @param type $totals
	 * @param type $unitType
	 * @param type $callback
	 * @throws Exception
	 */
	public function calculatePrice($discount, $billrun) {
		if (isset($discount['sid'])) {
			$entityId = $discount['sid'];
		} else {
			$entityId = null;
		}
		$discountVAT = $this->getDiscountVat($discount, $billrun);
		$totals = $this->getTotalsFromBillrun($billrun, $entityId);		
		$discountLimit = max(Billrun_Util::getFieldVal($discount['limit'], -PHP_INT_MAX), -($totals['after_vat'] / (1 + $discountVAT)));
		
		if (!isset($discount['discount'])) {
			Billrun_Factory::log('Missing  discount field in conditional discount : ' . $discount['key']);
			return FALSE;
		}
		$charge = $totalPrice = 0;

		foreach ($discount['discount'] as $key => $val) {
			/*if(!isset($totals[$key])) { TODO reinstate  when the $totals is pointing on the  right totals
					continue;
			}*/
			if ($discount['discount_type'] == 'monetary') {
				$callback = array($this, 'calculatePriceEuro');
			} else  {
				$callback = array($this, 'calculatePricePercent');
			}
			$aprice = call_user_func_array($callback, array($discount, 10, $val['value'], $discountLimit, $discountVAT));
			$totalPrice += $aprice;
		}
		if (!empty($totalPrice)) {
			$charge = $totalPrice > 0 ?  $totalPrice : max($totalPrice, $discountLimit);
		}
		if ($charge < $this->discountData['limit']) {
			$charge = $discountLimit;
		}
		$charge *= $discount['usagev'];
		return $charge;
	}
	
	//TODO  Rename!
	 public function getDiscountType() {
		 foreach($this->discountData['rates'] as $usage => $values ) {
			 if(!empty($values['units'])) {
				 return $values['units'];
			 }
		 }
			 
		return null;
	}

	public function getRateCategoryKeys($totalsSections = array()) {
		$filteredSections = array_filter($totalsSections,function($value) { return !empty($value); });
		$intersected = empty($filteredSections) ? $this->discountData['rates'] : array_intersect_key($this->discountData['rates'],$filteredSections);
		return array_keys( $intersected );
	}
	/**
	 * 
	 * @param type $discount
	 * @param type $totals
	 * @param type $value
	 * @param type $limit
	 * @param type $discountVat
	 * @return type
	 */
	public function calculatePricePercent($discount, $totals, $value, $limit, $discountVat, &$updatedTotals = array()) {
		$priceCorrection = 0;
		$aprice = 0;		
		if (is_array($totals)) {
			foreach ($totals as $vat => $pr) {				
				$vatRate = ( (1 + ($discountVat)) / (1 + ($vat / 100)) ); //get discount to charge rate
				$discountValue = $pr * floatval($value) / $vatRate;
				$oldAPrice = Billrun_Util::getFieldVal($aprice, 0);
				$aprice = max(Billrun_Util::getFieldVal($aprice, 0) - $discountValue, $limit);
				$discountValueLeft = $aprice - $oldAPrice;
				$totals[$vat] += $discountValueLeft * $vatRate;
				$priceCorrection += $totals[$vat] / $vatRate;
			}
		} else {
			$discountValue = $totals * floatval($value);
			$aprice = max(Billrun_Util::getFieldVal($aprice, 0) - $discountValue, $limit);
			$totals += $aprice;
			$priceCorrection = $totals;
		}
		// if the total gone  below 0  correct the discount value to keep it equal to 0
		if ($priceCorrection < 0 && $priceCorrection > $aprice) {
			$aprice -= $priceCorrection;
		}
		if(!empty($updatedTotals)) {
			$updatedTotals = $totals;
		}
		return min($aprice, 0);
	}

	/**
	 * 
	 * @param type $discount The discount cdr
	 * @param type $totals The VAT array from the relevant totals
	 * @param type $value
	 * @param type $limit
	 * @param type $discountVAT
	 * @return type
	 */
	protected function calculatePriceEuro($discount, $totals, $value, $limit, $discountVAT) {
		if ($value > 0 || Billrun_Util::getFieldVal($discount['termination'], FALSE)) {//if the  discount is a terminataed charge no need to  compare against existing totals
			return $value;
		}
		$discountLeft = $value;
		$vat = null;
		foreach ($totals as $vat => &$pr) {
			$vatRate = ( (1 + ($discountVAT)) / (1 + ($vat / 100)) ); //get discount to charge rate
			$totals[$vat] += $discountLeft * $vatRate;
			$discountLeft = ($pr < 0 ? $pr : 0 ) / $vatRate;
			$totals[$vat] -= $discountLeft;
		}
		if ($vat !== null) {
			$totals[$vat] += $discountLeft; // revert last carry if theres still discount value Left
		}
		return $value > $discountLeft ? 0 : //if the totals was negative before the discount application no discount needed.
			max((($discountLeft < 0 ) ? $value - $discountLeft : $value), $limit);
	}
	
	protected function adjustDiscountDuration($billrun, &$multiplier, $service = FALSE) {
		$billrunStartDate = Billrun_Billingcycle::getStartTime($billrun['billrun_key']);
		$receivedCount = empty($service) ? static::countReceivedDiscountsOfKey(null, $this->discountData['key'], $billrun->getAid() )
							: static::countReceivedDiscountsOfKey(null, $this->discountData['key'], $billrun->getAid(),'sid',$service['sid']);
		$eligible = $receivedCount < $this->discountData['duration'] &&
			( $receivedCount > 0 || empty($this->discountData['end_publication']) || $this->discountData['end_publication']->sec > $billrunStartDate );
		$followingBillrunKey = Billrun_Util::getFollowingBillrunKey($billrun->getBillrunKey());
		$end_date = Billrun_Util::getEndTime($followingBillrunKey);
		if ($eligible && $receivedCount > $this->discountData['duration'] - 1) {
			$multiplier = min($multiplier, $this->discountData['duration'] - $receivedCount);
			if ($multiplier < 1) {
				$end_date = Billrun_Util::calcEndDateByMonthMultiplier($multiplier, Billrun_Util::getEndTime($followingBillrunKey), Billrun_Billingcycle::getStartTime($followingBillrunKey));
			}
		}
		return $eligible ? $end_date : FALSE;
	}

	/**
	 * 
	 * @param type $billrun
	 * @return type
	 */
	protected static function getBillrunDate($billrunKey) {
		return Billrun_Billingcycle::getEndTime($billrunKey);
	}

	protected static function serviceWithinCommitment($service, $billrunTime = FALSE) {
//		if ($billrunTime) {
//			Billrun_Factory::log($service['engagement_end_date']);
//		}
		return !empty($service['engagement_end_date']) && ( empty($billrunTime) || $service['engagement_end_date'] > $billrunTime );
	}

	protected static function isDiscountUnderServicesDomains($discount, $services, $key, $billrunTime = FALSE) {
		foreach (@Billrun_Util::getFieldVal($services, array()) as $service) {
			foreach (@Billrun_Util::getFieldVal($discount['domains'], array()) as $domainKey => $domains) {
				if (!empty($domains) && isset($service[$key]) && in_array($service[$key], $domains) &&
					( (strstr($domainKey, 'with_commitment') === FALSE) || static::serviceWithinCommitment($service, $billrunTime))
				) {
					return TRUE;
				}
			}
		}
		//If the  dicount  domains are empty  it  eligible for all domains/services in other words it ignore which services the  account has
		return empty($discount['domains']); 
	}

	protected static function simpleFieldCompare($field, $cmpVal) {
		if (!is_array($cmpVal)) {
			return !empty(preg_match('/' . $cmpVal . '/', $field));
		} else if (is_array($field)) {
			return !empty(@array_intersect($field, $cmpVal));
		} else {
			$ret = TRUE;
			foreach ($cmpVal as $operator => $value) {
				if (!is_numeric($operator)) {
					switch ($operator) {
						case 'gte':
							$ret = $ret && ($field >= $value);
							unset($cmpVal[$operator]);
							break;
						case 'gt':
							$ret = $ret && ($field > $value);
							unset($cmpVal[$operator]);
							break;
						case 'lte':
							$ret = $ret && ($field <= $value);
							unset($cmpVal[$operator]);
							break;
						case 'lt':
							$ret = $ret && ($field < $value);
							unset($cmpVal[$operator]);
							break;
						default:
							break;
					}
				}
			}
			if ($ret && $cmpVal) {
				return in_array($field, $cmpVal) || !empty(array_filter($cmpVal, function($regex) use ($field) {
							return preg_match('/' . $regex . '/', $field);
						}));
			}
			return $ret;
		}
	}

	/**
	 * Retrive a match inside a timed object.
	 * @param type $fieldsArr 
	 * @param type $values
	 * @return mixed the identified timed field that matched the $values or false if none found
	 */
	protected static function findTimedField($fieldsArr, $values) {
		if (is_array($fieldsArr)) {
			foreach ($fieldsArr as $fieldVal) {
				//Check that the value is actually timed.
				if (!isset($fieldVal['name']) || (!isset($fieldVal['start_date']) && !isset($fieldVal['end_date']))) {
					continue;
				}
				if (static::simpleFieldCompare($fieldVal['name'], $values)) {
					return $fieldVal;
				}
			}
		}
		return FALSE;
	}

	/**
	 * Count all discount with a given type.
	 * @param Billrun_Billrun $billrun
	 * @param string $discountType
	 * @param int $entityId
	 * @param string $entityType
	 * @return float
	 */
	public static function countReceivedDiscountsOfKey($billrun, $discountType, $entityId, $entityType = 'aid') {
		if ($entityType != 'aid') {
			$entityType = 'sid';
		}
		$linesColl = Billrun_Factory::db()->linesCollection();
		$elements[] = array(
			'$match' => array(
				'type' => array('$in' => array('discount', 'credit')),
				$entityType => intval($entityId),
				'credit_type' => array('$in' => array('discount', 'conditional_discount', 'refund', 'credit')),
				'usaget' => 'conditional_discount',
			)
		);
		if (!empty($billrun)) {
			$elements[count($elements) - 1]['$match']['billrun'] = $billrun->getBillrunKey();
		}
		$elements[] = array(
			'$project' => array(
				'key' => array(
					'$ifNull' => array(
						'$key', '$service_name',
					),
				),
				'modifier' => array(
					'$ifNull' => array(
						'$modifier', 1,
					),
				),
			),
		);
		$elements[] = array(
			'$match' => array(
				'key' => $discountType,
			),
		);
		$elements[] = array(
			'$group' => array(
				'_id' => NULL,
				'sum' => array(
					'$sum' => '$modifier',
				),
			),
		);
		$res = $linesColl->aggregate($elements)->current();
		if ($res) {
			return round($res[0]['sum'], 10);
		}
		return 0;
	}

	abstract protected function getOptionalCDRFields();

	/**
	 * 
	 * @param type $discount
	 * @return type
	 */
	public static function isConditional($discount) {
		return !empty($discount['usaget']) && $discount['usaget'] == 'conditional_discount';
	}

	/**
	 * Get the totals of the current entity in the invoice. To be used before calculating the final charge of the discount
	 * @param Billrun_Billrun $billrunObj
	 * @param type $cdr
	 */
	abstract public function getInvoiceTotals($billrunObj, $cdr);

	abstract public function getEntityId($cdr);

	public function getId() {
		return $this->discountData['key'];
	}
        
        /**
         * Get Totals from the billrun object
         * @param type $billrun
         * @param type $entityId
         * @return type
         */
        protected function getTotalsFromBillrun( $billrun, $entityId ) {
            return $billrun->getTotals($entityId);
	}
	
	protected function getDiscountVatType($billrun) {
		return (isset($this->discountData['vat_type']) ? $this->discountData['vat_type'] : 'mobile');
	}
	
	protected function getDiscountVat($discount, $billrun) {
            //TODO implement!!!
		return 0.1; //!empty($discount['vatable']) ? $discount['vatable'] : $billrun->getEligibleVat($billrun->getInvoiceDate()->sec)['rates'][$this->getDiscountVatType($billrun)]['rate'][0]['percent'];
	}
	
	protected function getSuppportedVats($rate) {
		$retArr = array();
		if(!empty($this->discountData['variable_vat']) && is_array($this->discountData['variable_vat'])) {
			foreach( $this->discountData['variable_vat'] as $vatType) {
				$retArr[] = '' . intval(Billrun_Calculator_Rate_Vat::getVatFromRate($rate, $vatType) * 100);
			}
		}
		return $retArr;
	}
	
	protected function getRequiredOptions() {
		return isset($this->discountData['params']['discount']['services']['options']['required']) 
				?	$this->discountData['params']['discount']['services']['options']['required'] 
				:	array();
	}

}

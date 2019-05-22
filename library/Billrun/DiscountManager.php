<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of DiscountManager
 *
 * @author eran
 */
class Billrun_DiscountManager {

	static $discountRatesCache = array();

	/**
	 * Basic discounts rates query
	 * @var string
	 */
	protected $discountQueryFilter = array('params' => array('$exists' => 1));
	protected $config = array(
		'rate_identification' => array(
			'Billrun_Discount_Subscriber' => array(
				array('key' => '.*'), //check a mandatory field to  make this  discount the  default discount
				array('level' => 'service'),
			),
			'Billrun_Discount_Usage' => array(
				array('level' => 'usage'),
			),
			'Billrun_Discount_Account' => array(
				array('level' => 'account'),
			),
		)
	);

	public function __construct() {
		$this->config = Billrun_Factory::config()->getConfigValue('discounts.config', $this->config);
	}

	/**
	 * Check Eligible discount for account
	 * @param Billrun_Billrun $invoice
	 * @param type $billableAccount
	 * @return type
	 */
	public function getEligibleDiscounts($invoice, $types = array('monetary', 'percentage'), $eligibilityOnly = FALSE) {
		//Load discount rates
		if (empty(static::getCache())) {
			static::addToCache($this->loadDiscountsRates(array(), Billrun_Billingcycle::getEndTime($invoice->getBillrunKey())));
		}
		Billrun_Factory::log("Checking eligible discount... ", Zend_Log::INFO);
		$discountCdrs = $discountInstances = $eligibilityData = array();
		//Check eligiblility of  each discount rate
		foreach (static::getCache() as $discountRate) {
			$dis = $this->getDiscountFromRate($discountRate, $eligibilityOnly);
			if ($dis == NULL) {
				Billrun_Factory::log("Couldn't identify discount rate {$discountRate['key']}", Zend_Log::ERR);
				continue;
			}
			if ($eligibility = $dis->checkEligibility($invoice)) {
				$eligibilityData[$dis->getId()] = $eligibility;
			}
			$discountInstances[$dis->getId()] = $dis;
		}

		foreach ($eligibilityData as $discountId => $eligibleRows) {
			$discountCdrs[$discountId] = $discountInstances[$discountId]->generateCDRs($eligibleRows, $invoice);
		}

		//  initial pricing of the  discount.
		foreach ($discountCdrs as $discountId => &$discountCdr) {
			foreach ($discountCdr as &$cdr) {
				$pricingData = $discountInstances[$discountId]->calculatePriceAndTax($cdr, $invoice);
				$cdr['aprice'] = $pricingData['price'];
				$cdr['tax_info'] = $pricingData['tax_info'];
				$cdr['pricing_data'] = $pricingData['discount_pricing_data']; 
			}
		}

		// resolve conflicts between discounts
		$discountCdrs = $this->resolveDiscountConflict($discountCdrs);

		// Only apply the requested discount type (PERCENT/EURO(MONETARY)) to the account  
		foreach ($discountCdrs as $discountId => &$discountCdr) {
			foreach ($discountCdr as $idx => &$cdr) {
				if (!in_array($discountInstances[$discountId]->getDiscountType(), $types)) {
					unset($discountCdr[$idx]);
				}
			}
		}
		
		 if(empty($eligibilityOnly)) {
		  // Reprice the Discounts so they won't pass the charges in the account.
			  $this->repriceCDRs($discountCdrs ,$discountInstances, $invoice);
		  }

		$returnedCdrs = array();
		//Transform $discountCdrs  to a simple array.
		foreach ($discountCdrs as $discountId => &$discountCdr) {
			$returnedCdrs = array_merge($returnedCdrs, $discountCdr);
		}


		$finalCdrs = static::getFinalCDRs($returnedCdrs);
		// Apply Taxation 
		$taxedLines = $this->getTaxationDataForDiscounts($finalCdrs);
		foreach ($finalCdrs as &$cdr) {
			$cdr = $this->addTaxationToLine($cdr, $taxedLines[$cdr['stamp']]);
			unset($cdr['tax_info']);
			$cdr = Billrun_Utils_Plays::addPlayToLineDuringCycle($cdr);
		}

		return $finalCdrs;
	}

	protected function addTaxationToLine($cdr, $taxedData) {
		$entryWithTax = FALSE;
		$totalTaxData = array('total_amount' => 0, 'total_tax' => 0, 'taxes' => array());
		foreach ($taxedData as $taxed) {
			$totalTaxData['total_amount'] += $taxed['total_amount'];
			foreach (Billrun_Util::mapArrayToStructuredHash($taxed['taxes'], array('description')) as $taxKey => $tax) {
				@$totalTaxData['taxes'][$taxKey]['amount'] += $tax['amount'];
				@$totalTaxData['taxes'][$taxKey]['tax'] += $tax['tax'];
				@$totalTaxData['taxes'][$taxKey]['description'] = $tax['description'];
				@$totalTaxData['taxes'][$taxKey]['pass_to_customer'] = $tax['pass_to_customer'];
			}
		}
		$totalTaxData['taxes'] = array_values($totalTaxData['taxes']);
		$totalTaxData['total_tax'] = empty($cdr['aprice']) ? 0 : $totalTaxData['total_amount'] / $cdr['aprice'];

		$cdr['tax_data'] = $totalTaxData;
		$cdr['final_charge'] = $totalTaxData['total_amount'] + $cdr['aprice'];

		return $cdr;
	}

	/**
	 * Get Taxation data for each part of the discounts base on the axation inforamation save in calcualtePriceAndTax
	 * @param type $discounts the  discounts CDRs to get taxation to.
	 * @return type
	 */
	protected function getTaxationDataForDiscounts($discounts) {
		$dataForTaxation = array();
		$originalCdrMapping = array();
		foreach ($discounts as $cdr) {
			foreach ($cdr['tax_info'] as $taxInfo) {
				//Work around for copy on write behavior of PHP
				$cdrForTaxation = new Mongodloid_Entity($cdr->getRawData());
				$cdrForTaxation['aprice'] = $taxInfo['price'];
				$cdrForTaxation['arate'] = $taxInfo['tax_rate'];
				$cdrForTaxation['stamp'] = Billrun_Util::generateArrayStamp($cdrForTaxation);
				$dataForTaxation[$cdrForTaxation['stamp']] = $cdrForTaxation;
				$originalCdrMapping[$cdrForTaxation['stamp']] = $cdr['stamp'];
			}
		}
		$taxCalc = Billrun_Calculator::getInstance(array('autoload' => false, 'type' => 'tax'));
		$taxCalc->prepareData($dataForTaxation);

		$seperatlyTaxedLineParts = array();
		foreach ($dataForTaxation as &$line) {
			$line = $taxCalc->updateRow($line);
			if ($line) {
				$seperatlyTaxedLineParts[$originalCdrMapping[$line['stamp']]][] = $line['tax_data'];
			} else {
				Billrun_Factory::log("Couldn't tax part fo discount {$cdr['key']}", Zend_Log::WARN);
			}
		}
		return $seperatlyTaxedLineParts;
	}

	/**
	 * Conflict resolution  between all eligible discounts
	 */
	public function resolveDiscountConflict($discounts) {

		foreach ($discounts as $discountId => &$eligibleDiscounts) {
			foreach ($discounts as $compareId => &$compareDiscounts) {
				foreach ($eligibleDiscounts as $dscntIdx => $discount) {
					$discountRate = static::getCache()[$discount['key']];
					foreach ($compareDiscounts as $oldDscntIdx => $oldDiscount) {
						//dont compare the discount  with itself :)
						if ($discountId == $compareId) {
							continue;
						}
						// Does the  discounts exclude  each other?
						if ((!empty($discountRate['bill_exclude']) && in_array($oldDiscount['key'], $discountRate['bill_exclude']) ) ||
							(!empty($discountRate['plan_exclude']) &&
							( Billrun_Util::getFieldVal($oldDiscount['sid'], true) === Billrun_Util::getFieldVal($discount['sid'], false) ) &&
							in_array($oldDiscount['key'], $discountRate['plan_exclude']) )) {
							unset($compareDiscounts[$oldDscntIdx]);
						}
						//Does the discounts apply to the same subject? choose the higher discount
						if (!empty(array_intersect(array_keys($oldDiscount['discount']), array_keys($discount['discount']))) &&
							( Billrun_Util::getFieldVal($oldDiscount['sid'], true) === Billrun_Util::getFieldVal($discount['sid'], false) )) {
							if (abs($oldDiscount['aprice']) < abs($discount['aprice'])) {
								unset($compareDiscounts[$oldDscntIdx]);
							}
						}
					}
				}
			}
		}
		return $discounts;
	}

	/**
	 * 
	 * @param type $discounts
	 * @param type $discountInstances
	 * @param Billrun_Billrun $invoiceObj
	 * @return array
	 */
	protected function repriceCDRs(&$discounts, $discountInstances, $invoiceObj) {
		// if the  totals  equal to zero (aproximate 0.00000001)  then the  discount aprice will be adjasted to 0.
		//else apply discount to totals
		//if it cause negative value decrease thata value from the discount.
		
		//Reorder discounts to discount from the most amount of affected section to the least amount of section affected.
		$accountTotals = $invoiceObj->getTotals();
		uksort($discounts, function ($discountId1, $discountId2) use ($discountInstances, $accountTotals) {
			$categoryKeys1 = $discountInstances[$discountId1]->getRateCategoryKeys($accountTotals);
			$categoryKeys2 = $discountInstances[$discountId2]->getRateCategoryKeys($accountTotals);
			return (count($categoryKeys1) < count($categoryKeys2) ? -1 : (count($categoryKeys1) == count($categoryKeys2) ? 0 : 1));
		});

		$accountEntityId = 'aid' . $invoiceObj->getRawData()['aid'];
		$beforeVatTotals[$accountEntityId] = $invoiceObj->getTotals();
		foreach ($discounts as $discountId => &$typeDiscounts) {
			$instance = $discountInstances[$discountId];
			foreach ($typeDiscounts as &$discount) {
				$entityId = $instance->getEntityId($discount);
				if (!isset($entityTotals[$entityId])) {
					$beforeVatTotals[$entityId] = $instance->getInvoiceTotals($invoiceObj, $discount);
					$entityTotals[$entityId] = $instance->getInvoiceTotals($invoiceObj, $discount);
				}

				$discount['aprice'] = $this->getUpdatedCharge($discount, $entityTotals[$entityId], $accountTotals);
			}
		}
		return $discounts;
	}


	/**
	 * 
	 * @param array $cdr The discount cdr
	 * @param type $totalArr totals array in "after VAT" format
	 * @return float
	 */
	protected function getUpdatedCharge($cdr, &$totalArr, &$accountTotals) {
		if ($cdr['aprice'] < 0) {
			$availableCharge = abs($cdr['aprice']);
			$adjustedDiscount = 0;
			foreach ($cdr['affected_sections'] as $sectionKey) {
				if ($totalArr[$sectionKey]['before_vat'] <= 0) {
					continue;
				} else {
					$repriceDiff = min($availableCharge, $totalArr[$sectionKey]['before_vat'], $accountTotals[$sectionKey]['before_vat'], $totalArr['before_vat']);
					$totalArr[$sectionKey]['before_vat'] -= $repriceDiff;
					$accountTotals[$sectionKey]['before_vat'] -= $repriceDiff;
					$availableCharge -= $repriceDiff;
					$adjustedDiscount -= $repriceDiff;
				}
			}
			$adjustedDiscount = $adjustedDiscount - min(0, $adjustedDiscount + $accountTotals['before_vat']);
			$accountTotals['before_vat'] += $adjustedDiscount;
			$cdr['aprice'] = $adjustedDiscount;
		}
		return $cdr['aprice'];
	}

	/**
	 * Inisiate discount object for discount rate
	 * @param type $discountRate
	 * @return Billrun_AbstractDiscount
	 */
	protected function getDiscountFromRate($discountRate, $eligibilityOnly = FALSE) {

		$matchRate = function ($requiredFields) use ($discountRate) {
			$matchedArr = array();
			foreach ($requiredFields as $key => $val) {
				if (isset($discountRate[$key]) && preg_match('/' . $val . '/', $discountRate[$key])) {
					$matchedArr[$key] = $discountRate[$key];
				}
			}
			return $matchedArr;
		};

		foreach ($this->config['rate_identification'] as $clas => $queries) {
			foreach ($queries as $requiredFields) {
				if (count($requiredFields) == count($matchRate($requiredFields))) {
					return new $clas($discountRate, $eligibilityOnly);
				}
			}
		}

		return null;
	}

	/**
	 * 
	 * @param type $params
	 * @return type
	 */
	static protected function &getCache($params = FALSE) {
		return static::$discountRatesCache[$params];
	}

	/**
	 * 
	 * @param type $params
	 * @return type
	 */
	static protected function addToCache($discounts, $params = FALSE) {
		if (!isset(static::$discountRatesCache[$params])) {
			static::$discountRatesCache[$params] = array();
		}
		static::$discountRatesCache[$params] = array_merge(static::$discountRatesCache[$params], $discounts);
		return static::$discountRatesCache[$params];
	}

	/**
	 * load discount  from the DB by a given query
	 * @param type $query
	 */
	public function loadDiscountsRates($query = array(), $activeDate = FALSE) {
		$activeDate = $activeDate ? $activeDate : time();
		$discountColl = Billrun_Factory::db()->discountsCollection();
		$loadedDiscounts = $discountColl->query(array_merge($this->discountQueryFilter, $query))->cursor();
		$ret = array();
		foreach ($loadedDiscounts as $discount) {
			if ($discount['from']->sec < $activeDate && $activeDate < $discount['to']->sec) { //Should this  be  on the entire pasy month or just on billrun time?
				$ret[$discount['key']] = $discount;
			}
		}
		return $ret;
	}


	public static function generateDiscountStamp($discount) {
		$releventKeys = array(
			'key', 'process_time', 'modifier','original_modifier',
			'billrun', 'usaget', 'source',
			'arate', 'sid', 'aid', 'type',  
		);
		//Dont stamp the price for discounts in which thier price is affected from elements other  then to target of the discounts (Remise embasedor, precentage discounts, etc...)
		if (empty($discount['is_percent']) && !($discount['usaget'] == 'discount' && empty($discount['sid']))) {
			$releventKeys[] = 'aprice';
		}
		return Billrun_Util::generateFilteredArrayStamp($discount, $releventKeys);
	}

	public static function getFinalCDRs($cdrs) {
		foreach ($cdrs as &$cdr) {
			if (!isset($cdr['stamp'])) {
				$cdr['stamp'] = static::generateDiscountStamp($cdr);
			}
			$cdr = is_array($cdr) ? new Mongodloid_Entity($cdr) : $cdr;
		}
		return $cdrs;
	}

}

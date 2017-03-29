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

	/**
	 *
	 * @var array
	 */
	protected $discountData;
	protected $eligibilityOnly = FALSE;

	/**
	 * on filtered  totals  discounts this array hold the breakdown sections  that should be included in the discount.
	 * @var type  array
	 */
	protected $discountableSections = array();

	public function __construct($discountRate, $eligibilityOnly = FALSE) {
		$this->discountData = $discountRate;
		$this->eligibilityOnly = $eligibilityOnly;
	}

	abstract public function checkEligibility($accountBillrun);

	abstract public function checkTermination($accountBillrun);

	/**
	 * Generate all eligible discount CDRs for a given account.
	 * @param type $eligibleData
	 * @param type $accountInvoice
	 * @return type
	 */
	public function generateCDRs($eligibleData, $accountInvoice) {
		$discountLines = array();
		$prcisn = 10000000;
		$discountsCount = 0;
		foreach ($eligibleData as $eligibleRow) {
			$discountsCount++;
			//Apply the maximum limit of the discount
			if (!empty($this->discountData['max_count_limit']) && $discountsCount > $this->discountData['max_count_limit']) {
				Billrun_Factory::log("Account {$eligibleRow['aid']} has reached its maximum limit of discounts : {$this->discountData['key']}", Zend_Log::INFO);
				break;
			}

			$orgModifier = $modifier = $eligibleRow['modifier'];
			$quantity = !empty($eligibleRow['quantity']) ? $eligibleRow['quantity'] : 1;
			while (abs($modifier) >= 1 / ($prcisn * 10)) {
				$lineModifier = !empty($modifier * $prcisn % $prcisn) ? ($modifier * $prcisn % $prcisn) / $prcisn : ($modifier / abs($modifier));
				$modifier = round($modifier - $lineModifier, 3);
				$creationTime = (!empty($accountInvoice) ? static::getBillrunDate($accountInvoice->getBillrunKey()) : time() );

				$discountLines[] = $this->generateCDR($lineModifier, $creationTime, $orgModifier, 
													  $eligibleRow, $accountInvoice->getBillrunKey(), $quantity);
			}
		}
		//Apply the minimum limit of the discount
		if (!empty($this->discountData['min_limit']) && $discountsCount < $this->discountData['min_limit']) {
			Billrun_Factory::log("Account {$eligibleRow['aid']} hasn't reached it minimum limit for discount : {$this->discountData['key']}", Zend_Log::INFO);
			return array();
		}

		return $discountLines;
	}

	/**
	 * Generate a single discount CDR
	 */
	protected function generateCDR($lineModifier, $creationTime, $orgModifier, $eligibleRow, $billrunKey, $quantity) {
		$discountLine = array(
			'key' => $this->discountData['key'],
			'name' => $this->discountData['description'],
			'type' => 'credit',
			'description' => $this->discountData['description'],
			'usaget' => 'discount', //TODO move to  disocunt rate data?
			'discount_type' => $this->discountData['discount_type'],
			'urt' => new MongoDate($creationTime),
			'process_time' => date(Billrun_Base::base_dateformat, $creationTime),
			'modifier' => $lineModifier,
			'orignal_modifier' => $orgModifier,
			'arate' => $this->discountData->createRef(Billrun_Factory::db()->ratesCollection()),
			'aid' => $eligibleRow['aid'],
			'source' => 'billrun',
			'billrun' => $billrunKey,
			'usagev' => $quantity,
		);
		foreach ($this->getOptionalCDRFields() as $field) {
			if (isset($eligibleRow[$field])) {
				$discountLine[$field] = $eligibleRow[$field];
			}
		}
		if (!empty($this->discountData['cycles'])) {
			$discountLine['cycles'] = $this->discountData['cycles'];
		}
		foreach ($this->discountData['discount_subject'] as $subjects) {
			foreach ($subjects as $key => $val) {
				if ($this->discountData['discount_type'] == 'monetary') {
					$discountLine['discount'][$key]['value'] = -(abs($val)) * $lineModifier;
				} else {
					$discountLine['is_percent'] = true; //TODO change  all references to work with 'discount_type' field
					$discountLine['discount'][$key]['value'] = $val;
				}
			}
			$discountLine['affected_sections'] = array_keys($this->discountableSections);
		}

		if (!empty($this->discountData['limit'])) {
			$limit = 0 < $this->discountData['limit'] ? -$this->discountData['limit'] : $this->discountData['limit'];
			$discountLine['limit'] = $limit * $lineModifier;
		}
		

		$discountLine['process_time'] = date(Billrun_Base::base_dateformat);
		if (!empty($accountInvoice)) {
			$discountLine['received_count'] = static::countReceivedDiscountsOfKey(null, $this->discountData['key'], $accountInvoice->getRawData()['aid']);
		}

		return $discountLine;
	}

	/**
	 * returns the total discount value (charge) or FALSE on error
	 * @param type $discount
	 * @param type $totals
	 * @param type $unitType
	 * @param type $callback
	 * @throws Exception
	 */
	public function calculatePriceAndTax($discount, $billrun) {
		if (isset($discount['sid'])) {
			$entityId = $discount['sid'];
		} else {
			$entityId = null;
		}
		$totals = $this->getTotalsFromBillrun($billrun, $entityId);
		$discountLimit = Billrun_Util::getFieldVal($discount['limit'], -PHP_INT_MAX);

		if (!isset($discount['discount'])) {
			Billrun_Factory::log('Missing discount field in conditional discount : ' . $discount['key']);
			return FALSE;
		}
		$charge = $totalPrice = 0;
		//discount each of the subject  included in the discount
		foreach ($discount['discount'] as $key => $val) {
			//If the  discount discount several subjects increase the  discount to fit
			$usagev = isset($totals['count'][$key]) ? $totals['count'][$key] : 1; 
			if ($discount['discount_type'] == 'monetary') {
				$callback = array($this, 'calculatePriceEuro');
				$val['value'] *= $usagev;
			} else {
				$callback = array($this, 'calculatePricePercent');
			}
			
			$price = call_user_func_array($callback, array($totals[$key], $val['value'], $discountLimit * $usagev));
			$taxationInformation[] = $this->getTaxationDataForPrice($price, $key, $discount);
			$totalPrice += $price ;
		}
		//make sure that the  discount is not lees then it  limit
		if (!empty($totalPrice)) {
			$charge = $totalPrice > 0 ? $totalPrice : max($totalPrice, $discountLimit);
		}

		return array('price' => $charge, 'tax_info' => $taxationInformation);
		;
	}

	protected function getTaxationDataForPrice($price, $identifingKey, $discount) {
		$taxRate = FALSE;
		$retTaxInfo = array();
		//Get the tax rate by the subject key
		$collMapping = array(	'plan' => array('coll' => 'plans', 'key_field' => 'name'),
								'service' => array('coll' => 'services', 'key_field' => 'name'),
								'usage' => array('coll' => 'rates', 'key_field' => 'key')	);

		foreach ($collMapping as $subjectType => $mapping) {
			if (empty($this->discountData['discount_subject'][$subjectType])) {
				continue;
			}

			foreach ($this->discountData['discount_subject'][$subjectType] as $key => $val) {
				if ($identifingKey == $key) {
					$rateColl = Billrun_Factory::db()->getCollection($mapping['coll']);
					$query = array_merge(array($mapping['key_field'] => $key), Billrun_Utils_Mongo::getDateBoundQuery($discount['urt']->sec));
					$taxRate = $rateColl->query($query)->cursor()->limit(1)->current();

					break;
				}
			}
		}

		if ($taxRate) {
			$retTaxInfo = array('tax_rate' => $taxRate->createRef($rateColl), 'price' => $price);
		} else {
			Billrun_Factory::log("Cloudn't find taxation rate for discount {$disocunt['key']} for discount subject {$$identifingKey}.", Zend_Log::ERR);
		}

		return $retTaxInfo;
	}

	public function getDiscountType() {
		return $this->discountData['discount_type'];
	}

	public function getRateCategoryKeys($totalsSections = array()) {
		$filteredSections = array_filter($totalsSections, function($value) {
			return !empty($value);
		});
		$intersected = empty($filteredSections) ? $this->discountableSections : array_intersect_key($this->discountableSections, $filteredSections);
		return array_keys($intersected);
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
	public function calculatePricePercent($totals, $value, $limit, &$updatedTotals = array()) {
		$discountValue = $totals * floatval($value);
		$aprice = max(Billrun_Util::getFieldVal($aprice, 0) - $discountValue, $limit);
		$totals += $aprice;
		$priceCorrection = $totals;
		// if the total gone  below 0  correct the discount value to keep it equal to 0
		if ($priceCorrection < 0 && $priceCorrection > $aprice) {
			$aprice -= $priceCorrection;
		}
		if (!empty($updatedTotals)) {
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
	protected function calculatePriceEuro($total, $value, $limit) {

		$discountLeft = $total + $value;
		return $value > $discountLeft ? 0 : //if the totals was negative before the discount application no discount needed.
			max((($discountLeft < 0 ) ? $value - $discountLeft : $value), $limit);
	}

	protected function adjustDiscountDuration($invoice, &$multiplier, $subscriber = FALSE) {
		$billrunStartDate = Billrun_Billingcycle::getStartTime($invoice['billrun_key']);
		$receivedCount = empty($subscriber) ? static::countReceivedDiscountsOfKey(null, $this->discountData['key'], $invoice['aid']) : static::countReceivedDiscountsOfKey(null, $this->discountData['key'], $subscriber['sid'], 'sid');
		$cycleLimited = !empty($this->discountData['cycles']) && $receivedCount > $this->discountData['cycles'] && ( $receivedCount > 0 );
		$followingBillrunKey = Billrun_Billingcycle::getFollowingBillrunKey($invoice['billrun_key']);
		$end_date = Billrun_Billingcycle::getEndTime($followingBillrunKey);
		if ($cycleLimited && $receivedCount >= $this->discountData['cycles']) {
			$multiplier = max(0, min($multiplier, $this->discountData['cycles'] - $receivedCount));
			if ($multiplier < 1) {
				//$end_date = Billrun_ calcEndDateByMonthMultiplier($multiplier, Billrun_Util::getEndTime($followingBillrunKey), Billrun_Billingcycle::getStartTime($followingBillrunKey));
			}
		}
		return $cycleLimited ? $end_date : FALSE;
	}

	/**
	 * 
	 * @param type $billrun
	 * @return type
	 */
	protected static function getBillrunDate($billrunKey) {
		return Billrun_Billingcycle::getEndTime($billrunKey);
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
				'type' => array('$in' => array('credit')),
				$entityType => intval($entityId),
				'usaget' => 'discount',
			)
		);
		if (!empty($billrun)) {
			$elements[count($elements) - 1]['$match']['billrun'] = $billrun->getBillrunKey();
		}
		$elements[] = array(
			'$project' => array(
				'key' => array(
					'$ifNull' => array(
						'$key', '$name',
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
		if ($res && !empty(reset($res))) {
			return round(reset($res)['sum'], 10);
		}
		return 0;
	}

	abstract protected function getOptionalCDRFields();

	/**
	 * 
	 * @param type $discount
	 * @return type
	 */
	public static function isDiscount($discount) {
		return !empty($discount['usaget']) && $discount['usaget'] == 'discount';
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
	protected function getTotalsFromBillrun($billrun, $entityId) {
		return $billrun->getTotals($entityId);
	}

}

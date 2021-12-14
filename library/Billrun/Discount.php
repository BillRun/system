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

	use Billrun_Traits_ForeignFields;

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

				$discountLine = $this->generateCDR($lineModifier, $creationTime, $orgModifier,
						$eligibleRow, $accountInvoice->getBillrunKey(), $quantity);
				//Add foreign fields to the discount line
				$discountLine = $this->addForeginFields($discountLine, $eligibleData, $accountInvoice);

				$discountLines[] = $discountLine;
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
			'urt' => new Mongodloid_Date($creationTime),
			'process_time' => new Mongodloid_Date($creationTime),
			'modifier' => $lineModifier,
			'orignal_modifier' => $orgModifier,
			'arate' => $this->discountData->createRef(Billrun_Factory::db()->ratesCollection()),
			'aid' => $eligibleRow['aid'],
			'source' => 'billrun',
			'billrun' => $billrunKey,
			'usagev' => $quantity,
			'is_percent' => !$this->isMonetray(),
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
				$val = is_array($val) ? $val['value'] : $val; // Backward compatibility with amount/perecnt only discount subject.
				if ($this->isMonetray()) {
					$discountLine['discount'][$key]['value'] = -(abs($val)) * $lineModifier;
				} else {
					$discountLine['discount'][$key]['value'] = $val;
				}
			}
			$discountLine['affected_sections'] = array_keys($this->discountableSections);
		}

		if (!empty($this->discountData['limit'])) {
			$limit = 0 < $this->discountData['limit'] ? -$this->discountData['limit'] : $this->discountData['limit'];
			$discountLine['limit'] = $limit * $lineModifier;
		}

		$discountLine['process_time'] = new Mongodloid_Date();
		if (!empty($accountInvoice)) {
			$discountLine['received_count'] = static::countReceivedDiscountsOfKey(null, $this->discountData['key'], $accountInvoice->getRawData()['aid']);
		}

		return $discountLine;
	}

	protected function addForeginFields($discountLine, $eligibleData, $accountInvoice) {
		$addedFields = $this->getForeignFields(array('billrun' => $accountInvoice), $discountLine, true);
		return array_merge($discountLine, $addedFields);
	}

	/**
	 * returns the total discount value (charge) or FALSE on error
	 * @param type $discount
	 * @param type $totals
	 * @param type $unitType
	 * @param type $callback
	 * @throws Exception
	 */
	public function calculatePriceAndTax($discount, $invoice) {
		if (isset($discount['sid'])) {
			$entityId = $discount['sid'];
		} else {
			$entityId = null;
		}
		$totals = $this->getTotalsFromBillrun($invoice, $entityId);
		$discountLimit = Billrun_Util::getFieldVal($discount['limit'], -PHP_INT_MAX);

		if (!isset($discount['discount'])) {
			Billrun_Factory::log('Missing discount field in conditional discount : ' . $discount['key']);
			return FALSE;
		}
		$charge = $totalPrice = 0;
		$addedPricingData = [];
		//discount each of the subject  included in the discount
		foreach (($totals['rates'] ?: []) as $key => $ratePrice) {
			if (empty($discount['discount'][$key]) && !$this->isApplyToAnySubject()) {
				Billrun_Factory::log('discount generated invoice totals that  arer not  in the discount subject', Zend_Log::WARN);
				continue;
			}
			$val = $this->isApplyToAnySubject() ? $discount['discount']['any_subject']['value'] - $totalPrice : $discount['discount'][$key]['value'];
			//If the  discount discount several subjects increase the  discount to fit
			$usagev = isset($totals['count'][$key]) ? $totals['count'][$key] : 1;
			if ($this->isMonetray()) {
				$val *= $usagev;
				$callback = array($this, 'calculatePriceEuro');
			} else {
				$callback = array($this, 'calculatePricePercent');
			}
			$simplePrice = call_user_func_array($callback, array($ratePrice, $val, $discountLimit));
			$pricingData = $this->priceManipulation($simplePrice, $val, $key, $discountLimit, $totals);
			$addedPricingData[] = $pricingData;
			$price = $pricingData['price'];
			$taxationInfo = $this->getTaxationDataForPrice($price, $key, $discount);
			$taxationInformation[] = $taxationInfo;
			$totalPrice += $this->repriceForUpfront($price, @$taxationInfo['tax_rate'], $discount, $invoice, $callback, $val, $ratePrice);
		}
		//make sure that the  discount is not lees then it  limit
		if (!empty($totalPrice)) {
			$charge = $totalPrice > 0 ? $totalPrice : max($totalPrice, $discountLimit);
		}

		return array('price' => $charge, 'tax_info' => $taxationInformation, 'discount_pricing_data' => $addedPricingData);
	}

	protected function getTaxationDataForPrice($price, $identifingKey, $discount) {
		$rate = FALSE;
		$retTaxInfo = array();
		//Get the tax rate by the subject key
		$collMapping = array('plan' => array('coll' => 'plans', 'key_field' => 'name'),
			'service' => array('coll' => 'services', 'key_field' => 'name'),
			'usage' => array('coll' => 'rates', 'key_field' => 'key'));

		foreach ($collMapping as $subjectType => $mapping) {
			//is the mappling collection exist in the discount subject or the discount apply to all subjects?
			if (empty($this->discountData['discount_subject'][$subjectType]) && !$this->isApplyToAnySubject()) {
				continue;
			}
			//is identifying key exists in discount subjects or the discount apply to all subjects?
			if (!$this->isApplyToAnySubject() && empty($discount['discount'][$identifingKey])) {
				continue;
			}

			$rateColl = Billrun_Factory::db()->getCollection($mapping['coll']);
			$query = array_merge(array($mapping['key_field'] => $identifingKey), Billrun_Utils_Mongo::getDateBoundQuery($discount['urt']->sec));
			//TODO this should use a cache! who programmed this huging function!.
			$tmpRate = $rateColl->query($query)->cursor()->limit(1)->current();
			if ($tmpRate && !$tmpRate->isEmpty()) {
				$rate = $tmpRate;
				break;
			}
		}

		if ($rate) {
			$retTaxInfo = array('tax_rate' => $rate->createRef($rateColl), 'price' => $price);
		} else {
			Billrun_Factory::log("Cloudn't find taxation rate for discount {$discount['key']} for discount subject {$identifingKey}.", Zend_Log::ERR);
		}

		return $retTaxInfo;
	}

	public function getDiscountType() {
		return $this->discountData['discount_type'];
	}

	public function getRateCategoryKeys($totalsSections = array()) {
		$filteredSections = array_filter($totalsSections, function ($value) {
			return !empty($value);
		});
		$intersected = empty($filteredSections) ? $this->discountableSections : array_intersect_key($this->discountableSections, $filteredSections);
		return array_keys($intersected);
	}

	/**
	 * Calcuate a precentage price form a given total amount
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
	 * @return type
	 */
	protected function calculatePriceEuro($total, $value, $limit) {

		$discountLeft = $total + $value;
		return $value > $discountLeft ? 0 : //if the totals was negative before the discount application no discount needed.
				max((($discountLeft < 0 ) ? $value - $discountLeft : $value), $limit);
	}

	/**
	 * audjust price for terminated discounts that have upfront subjects.
	 * @return float with the new price taking the upfront subject into account.
	 */
	protected function repriceForUpfront($price, $rateRef, $discount, $invoice, $callback, $discountValue, $currentCharge) {
		$adjustAmount = 0;
		$previousBillrunKey = Billrun_Billingcycle::getPreviousBillrunKey($invoice->getBillrunKey());
		$entityId = empty($discount['sid']) ? $discount['aid'] : $discount['sid'];
		$entityType = empty($discount['sid']) ? 'aid' : 'sid';
		//discount has ended in the current billing cycle and was given in the last billrun.
		if ($price == 0 && $currentCharge < 0
				//!empty($discount['end']) && $discount['end']->sec < static::getBillrunDate($billrun->getBillrunKey()) 
				&& $this->countReceivedDiscountsOfKey($previousBillrunKey, $discount['key'], $entityId, $entityType)) {

			$rate = Billrun_Factory::db()->getByDBRef($rateRef);
			//If the subject of the discount was upfront then charge 
			if ($rate && !empty($rate['upfront']) && $rate = $rate->getRawData()) {
				$discountCharge = call_user_func_array($callback, array(-$currentCharge, $discountValue, $this->getLimit()));
				if ($this->isMonetray()) {
					//for monetry discount adjust  the amount  based on the precetage of the  charge the was returned  
					$charger = new Billrun_Plans_Charge();
					$rate['cycle'] = new Billrun_DataTypes_CycleTime($previousBillrunKey);
					$fullPrice = $charger->charge($rate, $rate['cycle'])['charge'];
					$discountCharge = $discountCharge * (abs($currentCharge) / $fullPrice);
				}
				$adjustAmount -= max($discountCharge, $this->getLimit());
			}
		}
		return $price + $adjustAmount;
	}

	protected function adjustDiscountDuration($invoice, &$multiplier, $subscriber = FALSE) {
		$billrunStartDate = Billrun_Billingcycle::getStartTime($invoice['billrun_key']);
		$receivedCount = empty($subscriber) ? static::countReceivedDiscountsOfKey(null, $this->discountData['key'], $invoice['aid']) : static::countReceivedDiscountsOfKey(null, $this->discountData['key'], $subscriber['sid'], 'sid');
		$cycleLimited = !empty($this->discountData['cycles']) && $receivedCount >= $this->discountData['cycles'] && ( $receivedCount > 0 );
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
	public static function countReceivedDiscountsOfKey($billrunKey, $discountType, $entityId, $entityType = 'aid') {
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
		if (!empty($billrunKey)) {
			$elements[count($elements) - 1]['$match']['billrun'] = $billrunKey;
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

	abstract protected function getOptionalCDRFields();

	abstract public function getEntityId($cdr);

	public function getId() {
		return $this->discountData['key'];
	}

	//=================================== Protected ======================================

	protected function priceManipulation($simpleDiscountPrice, $subjectValue, $subjectKey, $discountLimit, $discount) {
		return [
			'price' => max($min(0, simpleDiscountPrice), $discountLimit),
			'pricing_breakdown' => [$subjectKey => [['base_price' => $simpleDiscountPrice]]]
		];
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

	protected function isApplyToAnySubject() {
		return !empty($this->discountData['any_subject']);
	}

	protected function isMonetray() {
		return $this->discountData['discount_type'] == 'monetary';
	}

	protected function getLimit() {
		return empty($this->discountData['limit']) ? -(PHP_INT_MAX - 1) : $this->discountData['limit'];
	}

	static public function remove($stamps) {
		$query = array(
			'stamp' => array(
				'$in' => array_values($stamps)
			),
			'type' => 'credit',
			'$or' => array(
				array(
					'billrun' => array(
						'$exists' => false,
					),
				),
				array(
					'billrun' => array(
						'$gte' => Billrun_Billingcycle::getBillrunKeyByTimestamp(),
					),
				),
			),
		);
		$discountColl = Billrun_Factory::db()->linesCollection();
		return $discountColl->remove($query);
	}

}

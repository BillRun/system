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
 * @since    3.0
 */
class Billrun_Discount_Account extends Billrun_Discount {

	/**
	 * Check a single discount if an account is eligible to get it.
	 * (TODO change this hard coded logic to something more flexible)
	 * @param type $account the account data to check the discount against
	 * @param Billrun_Billrun $billrun
	 */
	public function checkEligibility($account, $billrun) {
		$this->billrunDate = $billrunDate = static::getBillrunDate($billrun);
		$this->billrunStartDate = $billrunStartDate = Billrun_Util::getStartTime($billrun->getBillrunKey());
		$addedData = array('aid' => $billrun->getAid());
		$eligible = $this->discountData['from']->sec < $billrunDate && $billrunDate < $this->discountData['to']->sec ; 


		return $eligible ? array(array_merge(array('modifier' => $multiplier, 'start' => $switch_date, 'end' => $end_date), $addedData)) : FALSE;
	}

	public function checkTermination($account, $billrun) {
		return array();
	}
	
	
	
	protected function getOptionalCDRFields() {
		return array();
	}

	/**
	 * Get the totals of the current entity in the invoice. To be used before calculating the final charge of the discount
	 * @param Billrun_Billrun $billrunObj
	 * @param type $cdr
	 */
	public function getInvoiceTotals($billrunObj, $cdr) {
		return $billrunObj->getTotals();
	}
	
	public function getEntityId($cdr) {
		return 'aid' . $cdr['aid'];
	}

}

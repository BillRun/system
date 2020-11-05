<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing plans's tariff step
 *
 * @package  Rate
 * @since    5.12
 */
class Billrun_Plans_Step extends Billrun_Rate_Step {

    /**
	 * Get the relative to offset price to charge of plan's step
     * 
	 * @param int $startOffset
	 * @param int $endOffset
	 * @return float
	 */
	public function getRelativePrice($startOffset, $endOffset ,$activation = FALSE, $currency = '') {
		if (!$this->validate($startOffset, $endOffset)) {
			return 0;
		}

		$endPricing = $endOffset;
		$startPricing = $startOffset;

		if ($this->get('from') > $startOffset) {
			$startPricing = $this->get('from');
			// HACK :  fix for the month length differance between the  activation and the  plan change , NOTICE will only work on monthly charges
			if(round($endOffset -1,6) == round($startOffset,6) && $activation && $startOffset > 0) {
				$startFratcion = 1 -($startOffset-floor($startOffset));
				$currentDays = date('t',Billrun_Plan::monthDiffToDate($endOffset, $activation)-1);
				$startPricing += ((($startFratcion * date('t',$activation)) /  $currentDays) - $startFratcion);
			}
		}
		if (!Billrun_Plan::isValueUnlimited($this->get('to')) && $this->get('to') < $endOffset) {
			$endPricing = $this->get('to');
			// HACK :  fix for the month length differance between the  activation and the  plan change , NOTICE will only work on monthly charges
			if(round($endOffset -1,6) == round($startOffset,6) && $activation && $startOffset > 0) {
				$endFratcion = 1 -($startOffset - floor($startOffset));
				$currentDays = date('t',Billrun_Plan::monthDiffToDate($endOffset, $activation)-1);
				$endPricing += (( ($endFratcion * date('t',$activation)) / $currentDays) - $endFratcion);
			}
		}
		//If the tariff is of expired service/plan don't charge anything
		if(!Billrun_Plan::isValueUnlimited($this->get('to')) && $this->get('to') <= $startPricing && $this->get('from') < $startPricing) {
            return 0;
		}
        $fullMonth = (round(($endPricing - $startPricing), 5) == 1 || $endPricing == $startPricing);
        $fullPrice = $this->getPrice($currency);
		return array(
            'start' => $fullMonth ? FALSE : $startPricing,
			'end' => $fullMonth ? FALSE : $endPricing,
            'price' => ($endPricing - $startPricing) * $fullPrice,
            'full_price' => $fullPrice,
            'orig_price' => ($endPricing - $startPricing) * $this->getPrice(),
        );
	}
    
    /**
	 * Validate the step and the input to the getPrice function
     * 
	 * @param int $startOffset
	 * @param int $endOffset
	 * @return boolean
	 */
	protected function validate($startOffset, $endOffset) {
		if ($this->get('from') > $this->get('to') && !Billrun_Plan::isValueUnlimited($this->get('to')) ) {
			Billrun_Factory::log("Billrun_Plans_Step->getRelativePrice received invalid tariff.", Zend_Log::CRIT);
			return false;
		}

		if ($startOffset > $endOffset) {
			Billrun_Factory::log("Billrun_Plans_Step->getRelativePrice received invalid offset values.", Zend_Log::CRIT);
			return false;
		}

		if ($startOffset > $this->get('to') && !Billrun_Plan::isValueUnlimited($this->get('to'))) {
			Billrun_Factory::log("Billrun_Plans_Step->getRelativePrice start offset is out of bounds.", Zend_Log::CRIT);
			return false;
		}

		if ($endOffset < $this->get('from')) {
			Billrun_Factory::log("Billrun_Plans_Step->getRelativePrice end offset is out of bounds.", Zend_Log::CRIT);
			return false;
		}
		return true;
	}
}

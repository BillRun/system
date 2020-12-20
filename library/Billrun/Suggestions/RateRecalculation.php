<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract suggestions class
 *
 * @package  Billing
 */
class Billrun_Suggestions_RateRecalculation extends Billrun_Suggestions {

	public function __construct() {
		parent::__construct();
	}

	protected function getRecalculateType() {
		return 'rates';
	}

	protected function getCollectionName() {
		return 'rates';
	}

	protected function checkIfValidRetroactiveChange($retroactiveChange) {
		//check if price change
		if (!$this->isFirstTierPriceChange($retroactiveChange)) {
			return false;
		}

		//Doesn't contain more than one tier and if that tier's interval is 1
		if ($this->getNewRateNumberOfTiers($retroactiveChange) === 1 && $this->getNewRateFirstTierInterval($retroactiveChange) === 1) {
			return true;
		}

		//Contains one tier with from=0, to=interval and others have a 0 price.
		$excludeTiers = [1];
		if ($this->getNewRateFirstTierInterval($retroactiveChange) === $this->getNewRateFirstTierTo($retroactiveChange) && $this->checkAllTiersPriceAreZero($retroactiveChange, $excludeTiers)) {
			return true;
		}
		return false;
	}

	protected function getFieldNameOfLine(){
		return 'arate_key';
	}
	
	protected function recalculationPrice($line){
		$keyName = $this->getFieldNameOfLine();
		$updateRate = Billrun_Rates_Util::getRateByName($line[$keyName], $line['urt']->sec);
		if(!empty($updateRate)){
			$usageType = Billrun_Rates_Util::getUsageTypeFromRate($updateRate);
			$newPrice = Billrun_Rates_Util::getTotalCharge($updateRate, $usageType, $line['usagev']);//todo:: check this!!
		}
		return $newPrice;
	}

	protected function addFiltersToFindMatchingLines($retroactiveChange) {
		//check if this enough/right to know that 
		//product isn't included / overridden in some plan/service
		return array(
			'arategroups' => array('$exists' => false)
		);
	}
	
	private function isFirstTierPriceChange($retroactiveChange) {
		$tier = 1;
		$oldPrice = $this->getRateTierPrice($retroactiveChange, $tier, 'old');
		$newPrice = $this->getRateTierPrice($retroactiveChange, $tier, 'new');
		return $oldPrice !== $newPrice;
	}

	private function getNewRateNumberOfTiers($retroactiveChange) {
		$unitType = $this->getRateUnitType($retroactiveChange);
		return count($retroactiveChange['new']['rates'][$unitType]['BASE']['rate']);
	}

	private function getNewRateFirstTierInterval($retroactiveChange) {
		$unitType = $this->getRateUnitType($retroactiveChange);
		return $retroactiveChange['new']['rates'][$unitType]['BASE']['rate'][0]['interval'];
	}

	private function getRateTierPrice($retroactiveChange, $tier, $type = 'new') {
		$unitType = $this->getRateUnitType($retroactiveChange);
		return $retroactiveChange[$type]['rates'][$unitType]['BASE']['rate'][$tier - 1]['price'];
	}

	private function getNewRateFirstTierTo($retroactiveChange) {
		$unitType = $this->getRateUnitType($retroactiveChange);
		return $retroactiveChange['new']['rates'][$unitType]['BASE']['rate'][0]['to'];
	}

	private function checkAllTiersPriceAreZero($retroactiveChange, $excludeTiers) {
		foreach ($this->getNewRateNumberOfTiers($retroactiveChange) as $tier) {
			if (in_array($tier, $excludeTiers)) {
				continue;
			}
			if ($this->getRateTierPrice($retroactiveChange, $tier) !== 0) {
				return false;
			}
		}
		return true;
	}

	private function getRateUnitType($retroactiveChange) {
		return key($retroactiveChange['new']['rates']);
	}

}

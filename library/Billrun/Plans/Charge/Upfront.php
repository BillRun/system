<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Calculates an upfront charge
 *
 * @package  Plans
 * @since    5.2
 */
abstract class Billrun_Plans_Charge_Upfront extends Billrun_Plans_Charge_Base {

	protected $seperatedCrossCycleCharges = false;
	
	public function __construct($plan) {
		parent::__construct($plan);
		$this->seperatedCrossCycleCharges = Billrun_Util::getFieldVal($plan['seperate_cross_cycle_charges'],
																		Billrun_Factory::config()->getConfigValue('billrun.seperate_cross_cycle_charges',
																												$this->seperatedCrossCycleCharges) );
	}

	/**
	 * 
	 */
	protected abstract function getFractionOfMonth();
	
	public abstract function getRefund(Billrun_DataTypes_CycleTime $cycle);
	
	/**
	 * Get the price of the current plan.
	 * @return int, null if no charge
	 */
	public function getPrice($quantity = 1) {

		$fraction = $this->getFractionOfMonth();
		if($fraction === null) {
			return null;
		}
		$cycles = [['cycle'=> $this->cycle , 'fraction'=> $fraction]];
		if($this->seperatedCrossCycleCharges && $this->activation < $this->cycle->end() && $this->activation >= $this->cycle->start() && $fraction > 1) {
		$nextCycle = $this->getUpfrontCycle($this->cycle);
		$cycles = [
					['cycle'=> $this->cycle , 'fraction'=> $fraction  - 1],
					['cycle'=> $nextCycle , 'fraction'=> 1 ],
				];
		}
		$retCahrges = [];
		foreach($cycles as $cycleData) {
			$price = $this->getPriceForCycle($cycleData['cycle']);
			$retCahrges[] = array_merge($this->getProrationData($this->price,$cycleData['cycle']),array(
				'value'=> $price * $cycleData['fraction'],
				'full_price' => floatval($price)
				));
		}
		return empty($retCahrges) ? null :  $retCahrges;
	}

	protected function getPriceForCycle($cycle) {
			$formatStart = date(Billrun_Base::base_dateformat, strtotime('-1 day', $cycle->end()));
			$formatActivation = date(Billrun_Base::base_dateformat, $this->activation);
			$startOffset = Billrun_Utils_Time::getMonthsDiff($formatActivation, $formatStart);
			return $this->getPriceByOffset($startOffset);
	}
	
	/**
	 * Get the price of the current plan
	 * @param type $startOffset
	 * @return price
	 */
	protected function getPriceByOffset($startOffset) {
		foreach ($this->price as $tariff) {
			if ($tariff['from'] <= $startOffset && (Billrun_Plan::isValueUnlimited($tariff['to']) ? PHP_INT_MAX : $tariff['to']) > $startOffset) {
				return $tariff['price'];
			}
		}
		
		return 0;
	}

	protected function getProrationData($price,$cycle = false) {
			$startOffset = Billrun_Utils_Time::getMonthsDiff( date(Billrun_Base::base_dateformat, $this->activation), date(Billrun_Base::base_dateformat, strtotime('-1 day', $this->cycle->end() )) );
			$nextCycle = $cycle ?  $cycle  : $this->getUpfrontCycle($this->cycle);
			return ['start' => $this->activation,
					'prorated_start_date' => new Mongodloid_Date($this->activation > $this->cycle->start() ? $this->activation  :  $nextCycle->start()),
					'end' =>  $this->deactivation < $this->cycle->end() ? $this->deactivation : $this->cycle->end(),
					'prorated_end_date' => new Mongodloid_Date($this->deactivation && $this->deactivation < $this->cycle->end() ? $this->deactivation : $nextCycle->end()),
					'start_date' =>new Mongodloid_Date(Billrun_Plan::monthDiffToDate($startOffset,  $this->activation )),
					'end_date' => new Mongodloid_Date($this->deactivation < $this->cycle->end() ? $this->deactivation : $this->cycle->end())];
	}

	protected function getUpfrontCycle($regularCycle) {
		$nextCycleKey = Billrun_Billingcycle::getFollowingBillrunKey($regularCycle->key());
		return new Billrun_DataTypes_CycleTime($nextCycleKey);
	}
	
}

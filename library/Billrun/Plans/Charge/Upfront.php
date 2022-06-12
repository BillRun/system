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
		$this->seperatedCrossCycleCharges = Billrun_Util::getFieldVal(  $plan['separate_cross_cycle_charges'],
                                                                        Billrun_Factory::config()->getConfigValue('billrun.separate_cross_cycle_charges',
																		$this->seperatedCrossCycleCharges) );
	}

	/**
	 * 
	 */
	protected abstract function getFractionOfMonth();
	
	public abstract function getRefund(Billrun_DataTypes_CycleTime $cycle, $quantity=1);
	
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
				'value'=> $price * $cycleData['fraction'] * $quantity,
				'full_price' => floatval($price)
				));
		}
		return empty($retCahrges) ? null :  $retCahrges;
	}

	protected function getPriceForCycle($cycle) {
        $formatStart = date(Billrun_Base::base_dateformat,  $cycle->start());
        $formatActivation = date(Billrun_Base::base_dateformat, $this->activation);
        $cycleCount = Billrun_Utils_Time::getMonthsDiff($formatActivation, $formatStart);
        return $this->getPriceByOffset($cycleCount);
	}
	
	/**
	 * Get the price of the current plan
	 * @param type $cycleCount
	 * @return price
	 */
	protected function getPriceByOffset($cycleCount) {
		foreach ($this->price as $tariff) {
			if ($tariff['from'] <= ceil($cycleCount) && (Billrun_Plan::isValueUnlimited($tariff['to']) ? PHP_INT_MAX : $tariff['to']) > $cycleCount) {
				return $tariff['price'];
			}
		}
		
		return 0;
	}

	protected function getProrationData($price,$cycle = false) {
			$startOffset = Billrun_Utils_Time::getMonthsDiff( date(Billrun_Base::base_dateformat, $this->activation), date(Billrun_Base::base_dateformat, strtotime('-1 day', $this->cycle->end() )) );
			$cycle = empty($cycle) ? $this->cycle : $cycle;
			$nextCycle = $this->getUpfrontCycle($cycle);
			//"this->deactivation < $this->cycle->end()" as the  deactivation date euqal the end of the current (and not next) cycle mean that the deactivation is in the future
			return ['start' => $this->activation,
					'prorated_start_date' => new Mongodloid_Date($this->activation > $cycle->start() ? $this->activation  : ($this->seperatedCrossCycleCharges ? $cycle->start() :$nextCycle->start())),
					'end' =>  $this->deactivation < $this->cycle->end() ? $this->deactivation : $cycle->end(),
					'prorated_end_date' => new Mongodloid_Date($this->deactivation && $this->deactivation < $this->cycle->end() ? $this->deactivation : (seperatedCrossCycleCharges ? $cycle->end() : $nextCycle->end()) ),
					'start_date' =>new Mongodloid_Date(Billrun_Plan::monthDiffToDate($startOffset,  $this->activation )),
					'end_date' => new Mongodloid_Date($this->deactivation < $this->cycle->end() ? $this->deactivation : $cycle->end())];
	}

	protected function getUpfrontCycle($regularCycle) {
		$nextCycleKey = Billrun_Billingcycle::getFollowingBillrunKey($regularCycle->key());
		return new Billrun_DataTypes_CycleTime($nextCycleKey);
	}
	
}

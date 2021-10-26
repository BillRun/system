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
	
	public function __construct($plan) {
		parent::__construct($plan);
		
		// Check if a deactivation date exists.
		
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
		
		$price = $this->getPriceForcycle($this->cycle);
		$fraction = $this->getFractionOfMonth();
		if($fraction === null) {
			return null;
		}

		return array_merge($this->getProrationData($this->price),array(
			'value'=> $price * $fraction, 
			'full_price' => floatval($price)
			));
	}

	protected function getPriceForcycle($cycle) {
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

	protected function getProrationData($price) {
			$startOffset = Billrun_Utils_Time::getMonthsDiff( date(Billrun_Base::base_dateformat, $this->activation), date(Billrun_Base::base_dateformat, strtotime('-1 day', $this->cycle->end() )) );
			return ['start' => $this->activation > $this->cycle->start() ? $this->activation  :  $this->cycle->start(),
					'end' => $this->deactivation && $this->deactivation < $this->cycle->end() ? $this->deactivation : $this->cycle->end(),
					'start_date' =>new MongoDate(Billrun_Plan::monthDiffToDate($startOffset,  $this->activation )),
					'end_date' => new MongoDate($this->deactivation < $this->cycle->end() ? $this->deactivation : $this->cycle->end())];
	}
	
}

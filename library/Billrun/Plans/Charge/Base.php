<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * A basic class to implement the charge logic
 *
 * @package  Plans
 * @since    5.2
 */
abstract class Billrun_Plans_Charge_Base {
	use Billrun_Traits_DateSpan;
	
	protected $price;
	
	/**
	 *
	 * @var Billrun_DataTypes_CycleTime
	 */
	protected $cycle;
	
	/**
	 * Create a new instance of the plans charge base class
	 * @param array $plan - Raw plan data
	 */
	public function __construct($plan) {
		$this->cycle = $plan['cycle'];
		$this->price = $plan['price'];
		$this->proratedStart = !isset($plan['prorated_start']) || $plan['prorated_start'] != FALSE;
		$this->proratedEnd = !isset($plan['prorated_end']) || $plan['prorated_end'] != FALSE;
		$this->proratedTermination = !isset($plan['prorated_termination']) || $plan['prorated_termination'] != FALSE;
		$this->subscriberDeactivation = !empty($plan['deactivation_date']) &&  $plan['deactivation_date'] instanceof MongoDate ?
											$plan['deactivation_date']->sec : FALSE ;
		
		$this->setSpan($plan);
	}
	
	/**
	 * Get the price of the current plan.
	 * @return float the price of the plan without VAT.
	 */
	public abstract function getPrice($quantity = 1);

}

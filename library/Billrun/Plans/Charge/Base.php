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
	protected $proratedStart;
	protected $proratedEnd;
	protected $proratedTermination;
	protected $subscriberDeactivation;
	
	/**
	 * currency on which we want to get prices by
	 *
	 * @var string
	 */
	protected $currency;
	
	/**
	 * stores default currency
	 *
	 * @var string
	 */
	protected $defaultCurrency;
	
	/**
	 * Create a new instance of the plans charge base class
	 * @param array $plan - Raw plan data
	 */
	public function __construct($plan) {
		$this->cycle = $plan['cycle'];
		$this->price = $plan['price'];
		$this->currency = $plan['currency'] ?? '';
		$this->defaultCurrency = Billrun_CurrencyConvert_Manager::getDefaultCurrency();
		$this->proratedStart = !isset($plan['prorated_start']) || $plan['prorated_start'] != FALSE;
		$this->proratedEnd = !isset($plan['prorated_end']) || $plan['prorated_end'] != FALSE;
		$this->proratedTermination = !isset($plan['prorated_termination']) || $plan['prorated_termination'] != FALSE;
		$this->subscriberDeactivation = !empty($plan['deactivation_date']) &&  $plan['deactivation_date'] instanceof Mongodloid_Date ?
											$plan['deactivation_date']->sec : FALSE ;
		
		$this->setSpan($plan);
	}
	
	/**
	 * Get the price of the current plan.
	 * @return float the price of the plan without VAT.
	 */
	public abstract function getPrice($quantity = 1);
	
	/**
	 * whether or not currency was done and required to add original currency to the charge response
	 *
	 * @return boolean
	 */
	protected function shouldAddOriginalCurrency() {
		return Billrun_CurrencyConvert_Manager::isMultiCurrencyEnabled() &&
			(!empty($this->currency) && $this->currency !== $this->defaultCurrency);
	}

	/**
	 * The currency a tariff price should be resolved in. Returns the account currency
	 * only when a real conversion is required (multi-currency on and the account is
	 * billed in a non-default currency); otherwise returns an empty string so the
	 * tariff/step pricing yields the untouched default-currency price. This keeps the
	 * behaviour for default-currency accounts (and single-currency systems) identical.
	 *
	 * @return string
	 */
	protected function getChargeCurrency() {
		return $this->shouldAddOriginalCurrency() ? $this->currency : '';
	}

}

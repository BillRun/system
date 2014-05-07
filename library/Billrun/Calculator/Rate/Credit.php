<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing calculator class for credit records
 *
 * @package  calculator
 * @since    0.5
 */
class Billrun_Calculator_Rate_Credit extends Billrun_Calculator_Rate {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = "credit";

	public function __construct($options = array()) {
		parent::__construct($options);
		$this->loadRates();
	}

	/**
	 * Write the calculation into DB
	 */
	public function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorUpdateRow', array($row, $this));
		$usage_type = $this->getLineUsageType($row);
		$volume = $this->getLineVolume($row, $usage_type);
		$rate = $this->getLineRate($row, $usage_type);

		$current = $row->getRawData();

		$added_values = array(
			'usaget' => $usage_type,
			'usagev' => $volume,
			$this->ratingField => $rate ? $rate->createRef() : $rate,
		);
		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);

		Billrun_Factory::dispatcher()->trigger('afterCalculatorUpdateRow', array($row, $this));
		return true;
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineVolume
	 */
	protected function getLineVolume($row, $usage_type) {
		return $row['amount_without_vat'];
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineUsageType
	 */
	protected function getLineUsageType($row) {
		return 'credit';
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineRate
	 */
	protected function getLineRate($row, $usage_type) {

		$rate_key = $row['vatable'] ? "CREDIT_VATABLE" : "CREDIT_VAT_FREE";
		$rate = $this->rates[$rate_key];

		return $rate;
	}

	/**
	 * Caches the rates in the memory for fast computations
	 */
	protected function loadRates() {
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$query = array(
			'key' => array(
				'$in' => array('CREDIT_VATABLE', 'CREDIT_VAT_FREE'),
			),
		);
		$rates = $rates_coll->query($query)->cursor()->setReadPreference(Billrun_Factory::config()->getConfigValue('read_only_db_pref'));
		$this->rates = array();
		foreach ($rates as $rate) {
			$rate->collection($rates_coll);
			$this->rates[$rate['key']] = $rate;
		}
	}

}

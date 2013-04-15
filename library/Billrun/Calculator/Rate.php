<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing calculator rate class
 * The class is basic rate that can evaluate record rate by different factors
 * 
 * @package  calculator
 * @since    0.5
 */
abstract class Billrun_Calculator_Rate extends Billrun_Calculator {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'rate';

	protected function load($initData = true) {
		parent::load($initData);
		// load the rate data
	}

	/**
	 * method to get calculator lines
	 */
	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();

		return $lines->query()
			->in('type', array('nsn', 'tap3'))
			->notExists('price_customer');

	}

	/**
	 * write the calculation into DB
	 */
	protected function updateRow($row) {
		
	}

	/**
	 * identify if the row belong to calculator
	 * 
	 * @return boolean true if the row identify as belonging to the calculator, else false
	 */
	protected function identify($row) {
		return true;
	}

	/**
	 * execute the calculation process
	 */
	public function calc() {
		
	}

	/**
	 * execute write the calculation output into DB
	 */
	public function write() {
		
	}
}

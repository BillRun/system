<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing calculator class
 *
 * @package  calculator
 * @since    1.0
 */
abstract class Billrun_Calculator extends Billrun_Base {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'calculator';

	/**
	 * the container data of the calculator
	 * @var array
	 */
	protected $data = array();

	/**
	 * load the data to calculate
	 */
	public function load($initData = true) {
		$lines = $this->db->getCollection(self::lines_table);

		// @todo refactoring query to be able to extend
//		$customer_query = "{'price_customer':{\$exists:false}}";
//		$provider_query = "{'price_provider':{\$exists:false}}";
//		$query = "{\$or: [" . $customer_query . ", " . $provider_query . "]}";
//		$query = "price_customer NOT EXISTS or price_provider NOT EXISTS";

		if ($initData) {
			$this->data = array();
		}

		$resource = $lines->query()
			->notExists('price_customer');
//			->notExists('price_provider'); // @todo: check how to do or between 2 not exists

		foreach ($resource as $entity) {
			$this->data[] = $entity;
		}

		$this->log->log("entities loaded: " . count($this->data), Zend_Log::INFO);
		
		$this->dispatcher->trigger('afterCalculatorLoadData', array('calculator' => $this));
	}

	/**
	 * write the calculation into DB
	 */
	abstract protected function updateRow($row);

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
	abstract public function calc();

	/**
	 * execute write the calculation output into DB
	 */
	abstract public function write();
}

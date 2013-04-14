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
 * @since    0.5
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

	public function __construct($options = array()) {
		parent::__construct($options);
		
		if (!isset($options['autoload']) || !$options['autoload']) {
			$this->load();
		}
	}

	/**
	 * method to get calculator lines
	 */
	abstract protected function getLines();

	/**
	 * load the data to run the calculator for
	 * 
	 * @param boolean $initData reset the data in the calculator before loading
	 * 
	 */
	public function load($initData = true) {
		
		if ($initData) {
			$this->data = array();
		}

		$resource = $this->getLines();
		
		foreach ($resource as $entity) {
			$this->data[] = $entity;
		}

		Billrun_Factory::log()->log("entities loaded: " . count($this->data), Zend_Log::INFO);

		Billrun_Factory::dispatcher()->trigger('afterCalculatorLoadData', array('calculator' => $this));
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

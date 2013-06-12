<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing calculator rate class
 * The class is basic rate that can evaluate record rate by different factors
 * 
 * @package  calculator
 * @since    0.5
 */
abstract class Billrun_Calculator_Base_Rate extends Billrun_Calculator {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'rate';

	/**
	 * The rating field to update in the CDR line.
	 * @var string
	 */
	protected $ratingField = 'customer_rate';

	/**
	 * The mapping of the fileds in the lines to the 
	 * @var array
	 */
	protected $rateMapping = array();
	
	public function __construct($options = array()) {
		parent::__construct($options);
		if(isset($options['calculator']['rate_mapping'])) {
			$this->rateMapping = $options['calculator']['rate_mapping'];
			//Billrun_Factory::log()->log("receive options : ".print_r($this->rateMapping,1),  Zend_Log::DEBUG);
		}
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
		Billrun_Factory::dispatcher()->trigger('beforeRateData', array('data' => $this->data));
		foreach ($this->lines as $item) {
			//Billrun_Factory::log()->log("Calcuating row : ".print_r($item,1),  Zend_Log::DEBUG);
			Billrun_Factory::dispatcher()->trigger('beforeRateDataRow', array('data' => &$item));
			$this->updateRow($item);
			$this->data[] = $item;
			$this->writeLine($item);
			Billrun_Factory::dispatcher()->trigger('afterRateDataRow', array('data' => &$item));
		}
		Billrun_Factory::dispatcher()->trigger('afterRateData', array('data' => $this->data));
	}

	/**
	 * execute write the calculation output into DB
	 */
	public function write() {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteData', array('data' => $this->data));
		//no need  the  line is now  written right after update
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteData', array('data' => $this->data));
	}

	/**
	 * Save a modified line to the lines collection.
	 */
	public function writeLine($line) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteLine', array('data' => $line));		
		$line->save( Billrun_Factory::db()->linesCollection());
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteLine', array('data' => $line));
	}
	
}

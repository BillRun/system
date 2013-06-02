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
 *
 */
class Billrun_Calculator_Data extends Billrun_Calculator_Base_Rate {
	
	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'data';
	
	protected $rateMaping = array('searchBy'=>'key','searchValue'=>'INTERNET_BILL_BY_VOLUME');
	
	public function __construct($options = array()) {
		parent::__construct($options);
		if(isset($options['rate_mapping'])) {
			$this->rateMaping = $options['rate_maping'];
		}
		
	}

	/**
	 * method to get calculator lines
	 */
	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();
		
		return $lines->query()
			->in('type', array('ggsn'))
			->notExists('customer_rate')->cursor()->limit($this->limit);

	}	
	
	/**
	 * write the calculation into DB
	 */
	protected function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteRow', array('row' => $row));

		$current = $row->getRawData();
		$rate = $this->getLineRate($row);	
		if($rate !== FALSE) {			
			$added_values = array(
				'customer_rate' => $rate['_id'],
			);
			$newData = array_merge($current, $added_values);
			$row->setRawData($newData);
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteRow', array('row' => $row));
	}
	
	protected function getLineRate($row) {
		if(preg_match('/^(?=62\.90\.|37\.26\.)/', $row['sgsn_address'])) {			
			$rate = Billrun_Factory::tariff()->get($this->rateMaping);
			return  $rate->getRawData();
		}
	//	Billrun_Factory::log()->log("International row : ".print_r($row,1),  Zend_Log::DEBUG);
		return FALSE;
	}
}

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
	
	protected $rateMapping = array('key'=>'INTERNET_BILL_BY_VOLUME');
	
	public function __construct($options = array()) {
		parent::__construct($options);
		
	}

	/**
	 * method to get calculator lines
	 */
	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();
		
		return $lines->query()
			->in('type', array('ggsn'))
			->notExists($this->ratingField)->cursor()->limit($this->limit);

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
				$this->ratingField => ($rate ? $rate['_id'] : $rate),
			);
			$newData = array_merge($current, $added_values);
			$row->setRawData($newData);
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteRow', array('row' => $row));
	}
	
	protected function getLineRate($row) {
		if(preg_match('/^(?=62\.90\.|37\.26\.)/', $row['sgsn_address']) && 
			(!isset($row['rating_group']) || $row['rating_group'] == 0)) {			
			$rate = Billrun_Factory::db()->ratesCollection()->query($this->rateMapping)->cursor()->current();
			return  $rate->getRawData();
		}
		return FALSE;
	}
}

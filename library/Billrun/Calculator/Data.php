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
class Billrun_Calculator_Data  extends Billrun_Calculator {
	
	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'data';	

	/**
	 * method to get calculator lines
	 */
	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();
		
		return $lines->query()
			->in('type', array('ggsn'))
			->notExists('rate_customer')->cursor()->limit($this->limit);

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
				'rate_customer' => $rate['_id'],
			);
			$newData = array_merge($current, $added_values);
			$row->setRawData($newData);
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteRow', array('row' => $row));
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
		Billrun_Factory::dispatcher()->trigger('beforeCalculateData', array('data' => $this->data));
		foreach ($this->lines as $item) {
			//Billrun_Factory::log()->log("Calcuating row : ".print_r($item,1),  Zend_Log::DEBUG);			
			$this->updateRow($item);
			$this->data[] = $item;
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculateData', array('data' => $this->data));
	}

	/**
	 * execute write the calculation output into DB
	 */
	public function write() {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteData', array('data' => $this->data));
		$lines = Billrun_Factory::db()->linesCollection();
		foreach ($this->data as $item) {
			$item->save($lines);
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteData', array('data' => $this->data));
	}
	
	protected function getLineRate($row) {
		if(preg_match('/^(?=62\.90\.|37\.26\.)/', $row['sgsn_address'])) {
			//TODO  replace this  with  a call to the rating collection API. 
			$rate = Billrun_Factory::tariff()->get(array('searchBy'=>'key','searchValue'=>'INTERNET_BILL_BY_VOLUME'));
			return  $rate->getRawData();
		}
	//	Billrun_Factory::log()->log("International row : ".print_r($row,1),  Zend_Log::DEBUG);
		return FALSE;
	}
}

?>

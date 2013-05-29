<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Tap3
 *
 * @author eran
 */
class Billrun_Calculator_Tap3Pricing extends Billrun_Calculator {
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
			->in('type', array('tap3'))
			->notExists('price_customer')->cursor()->limit($this->limit);

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
			Billrun_Factory::dispatcher()->trigger('afterRateDataRow', array('data' => &$item));
		}
		Billrun_Factory::dispatcher()->trigger('afterRateData', array('data' => $this->data));
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

	/**
	 * write the calculation into DB.
	 * @param $row the line CDR to update. 
	 */
	protected function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteRow', array('row' => $row));
		$header = $this->getLineHeader($row);
		$current = $row->getRawData();
		$priceData = $row['BasicServiceUsedList']['BasicServiceUsed']['ChargeInformationList']['ChargeInformation']['ChargeDetailList']['ChargeDetail'];	
		if($priceData !== FALSE ) {			
			//Billrun_Factory::log()->log("Header: ".print_r($header,1),  Zend_Log::DEBUG);
			$price = $priceData['Charge'] / pow(10, $header['trailer']['data']['trailer']['tap_decimal_places'] );
			$added_values = array(
				'price_customer' => Billrun_Util::convertCurrency($price, $header['trailer']['data']['trailer']['local_currency'], 'ILS'),
			);
			$newData = array_merge($current, $added_values);
			$row->setRawData($newData);
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteRow', array('row' => $row));
	}

	
	/**
	 * Get the header data  of the file that a given TAP3 CDR line belongs to. 
	 * @param type $line the cdr  lline to get the header for.
	 * @return Object representing the file header of the line.
	 */
	protected function getLineHeader($line) {
		return Billrun_Factory::db()->logCollection()->query(array('header.stamp'=> $line['header_stamp']))->cursor()->current();
	}
	
}

?>

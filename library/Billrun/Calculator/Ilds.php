<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing calculator class for ilds records
 *
 * @package  calculator
 * @since    1.0
 */
class Billrun_Calculator_Ilds extends Billrun_Calculator {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = "ilds";
	
	/**
	 * method to receive the lines the calculator should take care
	 * 
	 * @return Mongodloid_Cursor Mongo cursor for iteration
	 */
	protected function getLines() {
		
		$lines = Billrun_Factory::db()->linesCollection();

		return $lines->query()
			->equals('source', static::$type)
			->notExists('prepaid')
			->notExists('price_customer');
//			->notExists('price_provider'); // @todo: check how to do or between 2 not exists		
	}
	
	/**
	 * Execute the calculation process
	 */
	public function calc() {

		Billrun_Factory::dispatcher()->trigger('beforeCalculateData', array('data' => $this->data));
		foreach ($this->data as $item) {
			$this->updateRow($item);
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculateData', array('data' => $this->data));
	}

	/**
	 * Execute write down the calculation output
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
	 * Write the calculation into DB
	 */
	protected function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteRow', array('row' => $row));

		$current = $row->getRawData();
		$charge = $this->calcChargeLine($row->get('type'), $row->get('call_charge'));
		$added_values = array(
			'price_customer' => $charge,
			'price_provider' => $charge,
		);
		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);

		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteRow', array('row' => $row));
	}

	/**
	 * Method to calculate the charge from flat rate
	 *
	 * @param string $type the type of the charge (depend on provider)
	 * @param double $charge the amount of charge
	 * @return double the amount to charge
	 *
	 * @todo: refactoring it by mediator or plugin system
	 */
	protected function calcChargeLine($type, $charge) {

		switch ($type):
			case '012':
			case '015':
			case '017':
				$rating_charge = round($charge / 1000, 3);
				break;

			case '013':
			case '018':
				$rating_charge = round($charge / 100, 2);
				break;
			case '014':
			case '019':	
				$rating_charge = round($charge, 3);
				break;
			default:
				$rating_charge = floatval($charge);
				$configuredPriceDivision = Billrun_Factory::config()->getConfigValue("$type.calculator.price_division",0);
				if(!empty($configuredPriceDivision)) {
					$rating_charge = round($charge, $configuredPriceDivision);
				}
		endswitch;
		return $rating_charge;
	}

}

<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing calculator class for ilds records
 *
 * @package  calculator
 * @since    1.0
 */
class calculator_ilds extends calculator {

	/**
	 * the type of the calculator
	 * @var string
	 */
	protected $type = 'ilds';

	/**
	 * constructor
	 * @param array $options
	 */
	public function __construct($options) {
		parent::__construct($options);
	}

	/**
	 * execute the calculation process
	 */
	public function calc() {
		// @TODO trigger before calc
		foreach ($this->data as $item) {
			$this->updateRow($item);
		}
		// @TODO trigger after calc
	}

	/**
	 * execute write down the calculation output
	 */
	public function write() {
		// @TODO trigger before write
		$lines = $this->db->getCollection(self::lines_table);
		foreach ($this->data as $item) {
			$item->save($lines);
		}
		// @TODO trigger after write
	}

	/**
	 * write the calculation into DB
	 */
	protected function updateRow($row) {
		// @TODO trigger before update row
		$current = $row->getRawData();
		$charge = $this->calcChargeLine($row->get('type'), $row->get('call_charge'));
		$added_values = array(
			'price_customer' => $charge,
			'price_provider' => $charge,
		);
		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);
		// @TODO trigger after update row
	}

	protected function calcChargeLine($type, $charge) {
		switch ($type):
			case '012':
			case '014':
			case '015':
				$rating_charge = round($charge / 1000, 3);
				break;

			case '013':
			case '018':
				$rating_charge = round($charge / 100, 2);
				break;
			default:
				$rating_charge = $charge;
		endswitch;
		return $rating_charge;
	}

}
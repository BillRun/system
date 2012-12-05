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
class calculator_ilds extends calculator_basic implements calculator
{

	/**
	 * constructor
	 * @param array $options
	 */
	public function __construct($options)
	{
		parent::__construct($options);
	}

	/**
	 * execute the calculation process
	 */
	public function calc()
	{
		// @TODO trigger before calc
		foreach($this->data as $item) {
			$this->updateRow($item);
		}
		// @TODO trigger after calc
	}

	/**
	 * execute write down the calculation output
	 */
	public function write()
	{
		// @TODO trigger before write
		$lines = $this->db->getCollection(self::lines_table);
		foreach($this->data as $item) {
			$item->save($lines);
		}
		// @TODO trigger after write
	}

	/**
	 * write the calculation into DB
	 */
	protected function updateRow($row)
	{
		// @TODO trigger before update row
		$current = $row->getRawData();
		$charge = $row->get('call_charge') / 100;
		$added_values = array(
			'price_customer' => $charge,
			'price_provider' => $charge,
		);
		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);
		// @TODO trigger after update row
	}

}
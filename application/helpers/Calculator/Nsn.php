<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator class for nsn records
 *
 * @package  calculator
 * @since    0.5
 */
class Calculator_Nsn extends Billrun_Calculator {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = "nsn";

	public function __construct($options = array()) {
		parent::__construct($options);
	}

	/**
	 * make the calculation
	 */
	public function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorUpdateRow', array($row, $this));
		$current = $row->getRawData();
		$usage_type = $this->getLineUsageType($row);
		$volume = $this->getLineVolume($row, $usage_type);

		$added_values = array(
			'usaget' => $usage_type,
			'usagev' => $volume
		);
		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);

		Billrun_Factory::dispatcher()->trigger('afterCalculatorUpdateRow', array($row, $this));
		return $row;
	}


	/**
	 * method to receive the lines the calculator should take care
	 *
	 * @return Mongodloid_Cursor Mongo cursor for iteration
	 */
	protected function getLines() {
		return $this->getQueuedLines(array('type' => static::$type));
	}

	/**
	 * @see Billrun_Calculator::getCalculatorQueueType
	 */
	public function getCalculatorQueueType() {
		return 'rate';
	}

	/**
	 * @see Billrun_Calculator::isLineLegitimate
	 */
	public function isLineLegitimate($line) {
		return $line['type'] == 'nsn';
	}



	/**
	 * @see Billrun_Calculator_Rate::getLineVolume
	 */
	protected function getLineVolume($row, $usage_type) {
		if (in_array($usage_type, array('call', 'incoming_call'))) {
			if (isset($row['duration'])) {
				return $row['duration'];
			}
		}
		if ($usage_type == 'sms') {
			return 1;
		}
		return null;
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineUsageType
	 */
	protected function getLineUsageType($row) {
		switch ($row['record_type']) {
			case '08':
			case '09':
				return 'sms';

			case '02':
			case '12':
				return 'incoming_call';
				
			case '31':
				if(preg_match('/^RCEL/',$row['out_circuit_group_name'])) {
					return 'incoming_call';
				} else {
					return 'call';
				}
				
			case '11':
			case '01':
			case '30':
			default:
				return 'call';
		}
		return 'call';
	}

		
}

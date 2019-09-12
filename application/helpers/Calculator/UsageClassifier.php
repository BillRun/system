<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator rate class
 * The class is basic rate that can evaluate record rate by different factors
 *
 * @package  calculator
 * @since    0.5
 *
 */
class Calculator_UsageClassifier extends Billrun_Calculator {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'usage_classifier';


	public function __construct($options = array()) {
		parent::__construct($options);
	}

	/**
	 * @see Billrun_Calculator_Rate::updateRow
	 */
	public function updateRow($row)
	{
	Billrun_Factory::dispatcher()->trigger('beforeCalculatorUpdateRow', array($row, $this));
		$current = $row->getRawData();


		$usage_type = $this->getLineUsageType($row);
		$volume = $this->getLineVolume($row, $usage_type);
		if(empty($usage_type) || $volume == FALSE) {
			return FALSE;
		}
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
	 * @see Billrun_Calculator_Rate::getLineVolume
	 */
	protected function getLineVolume($row, $usage_type) {
		switch($row['type']) {
			case 'ggsn':
					return $row['fbc_downlink_volume'] + $row['fbc_uplink_volume'];
				break;
			case 'nsn' :
				if (in_array($usage_type, array('call', 'incoming_call'))) {
					if (isset($row['duration'])) {
						return $row['duration'];
					}
				}
				if ($usage_type == 'sms') {
					return 1;
				}
			break;
		}
		return $row['duration'];
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineUsageType
	 */
	protected function getLineUsageType($row) {
		if($row['type'] == 'nsn') {
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
		} else if ($row['type'] == 'ggsn') {
			return 'data';
		}

		return FALSE;
	}

	/**
	 * @see Billrun_Calculator::getLines
	 */
	protected function getLines() {
		return $this->getQueuedLines(array('type' => ['$in'=> ['nsn','ggsn']]));
	}


		/**
	 * @see Billrun_Calculator::getCalculatorQueueType
	 */
	public function getCalculatorQueueType() {
		return 'usage_classifier';
	}

	/**
	 * @see Billrun_Calculator::isLineLegitimate
	 */
	public function isLineLegitimate($line) {
		return true;
	}

}


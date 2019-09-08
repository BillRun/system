<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator class for tap3 records
 *
 * @package  calculator
 * @since    0.5
 */
class Calculator_Tap3 extends Billrun_Calculator {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'tap3';

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
		$volume = null;
		switch ($usage_type) {
			case 'sms' :
			case 'incoming_sms' :
				$volume = 1;
				break;

			case 'call' :
			case 'incoming_call' :
				$volume = $row->get('basicCallInformation.TotalCallEventDuration');
				break;

			case 'data' :
				$volume = $row->get('download_vol') + $row->get('upload_vol');
				break;
		}
		return $volume;
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineUsageType
	 */
	protected function getLineUsageType($row) {

		$usage_type = null;

		$record_type = $row['record_type'];
		if (isset($row['tele_srv_code'])) {
			$tele_service_code = $row['tele_srv_code'];
			if ($tele_service_code == '11') {
				if ($record_type == '9') {
					$usage_type = 'call'; // outgoing call
				} else if ($record_type == 'a') {
					$usage_type = 'incoming_call'; // incoming / callback
				}
			} else if ($tele_service_code == '22') {
				if ($record_type == '9') {
					$usage_type = 'sms';
				}
			} else if ($tele_service_code == '21') {
				if ($record_type == 'a') {
					$usage_type = 'incoming_sms';
				}
			} else if ($tele_service_code == '12') {
				$usage_type = 'call';
			}
		} else if (isset($row['bearer_srv_code'])) {
			if ($record_type == '9') {
				$usage_type = 'call';
			} else if ($record_type == 'a') {
				$usage_type = 'incoming_call';
			}
		} else if ($record_type == 'e') {
			$usage_type = 'data';
		}

		return $usage_type;
	}
}

?>

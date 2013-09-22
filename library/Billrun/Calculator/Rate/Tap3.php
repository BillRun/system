<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing calculator class for tap3 records
 *
 * @package  calculator
 * @since    0.5
 */
class Billrun_Calculator_Rate_Tap3 extends Billrun_Calculator_Rate {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'tap3';

	/**
	 * write the calculation into DB.
	 * @param $row the line CDR to update. 
	 */
	protected function updateRow($row) {
		if ($row['stamp']!='3ea5d84a13fdce844348ed30cf8c71ef') {
			return false;
		}
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteRow', array('row' => $row));

		$current = $row->getRawData();

		$usage_type = $this->getLineUsageType($row);
		$volume = $this->getLineVolume($row, $usage_type);
		$rate = $this->getLineRate($row, $usage_type);

		$added_values = array(
			'usaget' => $usage_type,
			'usagev' => $volume,
			$this->ratingField => $rate ? $rate->createRef() : $rate,
		);
		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);

		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteRow', array('row' => $row));
		return true;
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
				$volume = $row->get('GprsServiceUsed.DataVolumeIncoming') + $row->get('GprsServiceUsed.DataVolumeOutgoing');
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
		if (isset($row['BasicServiceUsedList']['BasicServiceUsed']['BasicService']['BasicServiceCode']['TeleServiceCode'])) {
			$tele_service_code = $row['BasicServiceUsedList']['BasicServiceUsed']['BasicService']['BasicServiceCode']['TeleServiceCode'];
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
			}
		} else {
			if ($record_type == 'e') {
				$usage_type = 'data';
			}
		}

		return $usage_type;
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineRate
	 */
	protected function getLineRate($row, $usage_type) {
		//$header = $this->getLineHeader($row); @TODO should this be removed? 2013/06	
		$line_time = $row['unified_record_time'];
		$serving_network = $row['serving_network'];

		if (!is_null($serving_network)) {
			$rates = Billrun_Factory::db()->ratesCollection();
			if (isset($usage_type)) {
				$filter_array = array(
					'params.serving_networks' => array(
						'$in' => array($serving_network),
					),
					'rates.' . $usage_type => array(
						'$exists' => true,
					),
					'from' => array(
						'$lte' => $line_time,
					),
					'to' => array(
						'$gte' => $line_time,
					),
				);
				$rate = $rates->query($filter_array)->cursor()->current();
				if ($rate->getId()) {
					$rate->collection(Billrun_Factory::db()->ratesCollection());
					return $rate;
				}
			}
		}

		return false;
	}

	/**
	 * Get the header data  of the file that a given TAP3 CDR line belongs to. 
	 * @param type $line the cdr  lline to get the header for.
	 * @return Object representing the file header of the line.
	 */
	protected function getLineHeader($line) {
		return Billrun_Factory::db()->logCollection()->query(array('header.stamp' => $line['header_stamp']))->cursor()->current();
	}

}

?>

<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents a service to be aggregated for a subscriber
 *
 * @package  Subscriber Aggregate
 * @since    5.2
 */
class Billrun_Subscriber_Aggregate_Services extends Billrun_Subscriber_Aggregate_Aggregator {

	//TODO is this  logic  still being used? 20170325
	protected function getCharge(array $values, string $billrunKey) {
		$name = $values['key'];
		$service = new Billrun_DataTypes_Subscriberservice($name);
		if (!$service->isValid()) {
			throw new Exception("Invalid subscriber service! " . print_r($service, 1));
		}

		$price = $service->getPrice();
		$dates = $values['dates'];
		$fraction = $this->getFraction($dates, $billrunKey);
		if ($fraction > 1 || ($fraction < 0)) {
			throw new Exception("Fraction cannot be larger than a whole! " . print_r($fraction));
		}

		return $price * $fraction;
	}

	protected function getFraction($dates, $billrunKey) {
		$fraction = 0;
		foreach ($dates as $span) {
			$activation = $span['start'];
			$deactivation = null;
			if (isset($span['end'])) {
				$deactivation = $span['end'];
			}

			$fraction += $this->calcFractionOfMonth($billrunKey, $activation, $deactivation);
		}
		return $fraction;
	}

	/**
	 * 
	 * @param array $subscribers
	 */
	protected function getRecords($subscribers) {
		$records = array();

		// Get the services from the subscribers.
		foreach ($subscribers as $subscriber) {
			if (!isset($subscriber['services'])) {
				continue;
			}

			// Get the services
			$subscriberServices = $subscriber['services'];
			$this->constructSubscriberServices($subscriberServices, $records);
		}
	}

	protected function constructSubscriberServices($subServices, &$records) {
		foreach ($subServices as $service) {
			$name = $service['name'];

			// Check if doesn't exists.
			if (!isset($records[$name])) {
				// Create it.
				$records[$name] = new Billrun_Subscriber_Aggregate_Base($name);
			}

			$dates = array("start" => $service['activation']);
			if (isset($service['deactivation'])) {
				$dates['end'] = $service['deactivation'];
			}

			// Set the date
			$records[$name]->add($dates);
		}
	}

	/**
	 * 
	 * @param type $billrunKey
	 * @return int
	 * @todo This should be moved to a more fitting location
	 */
	protected function calcFractionOfMonth($billrunKey, $activation, $deactivation) {
		$start = Billrun_Billingcycle::getStartTime($billrunKey);

		// If the billing start date is after the deactivation return zero
		if ($deactivation && ($start > $deactivation)) {
			return 0;
		}

		// If the billing start date is after the activation date, return a whole
		// fraction representing a full month.
		if (!$deactivation && ($start > $activation)) {
			return 1;
		}

		$end = Billrun_Billingcycle::getEndTime($billrunKey);

		// Validate the dates.
		if (!$this->validateCalcFractionOfMonth($billrunKey, $start, $end)) {
			return 0;
		}

		// Take the termination date.
		$termination = $end;
		if ($deactivation && ($end > $deactivation)) {
			$termination = $deactivation;
		}

		// Take the starting
		$starting = $start;
		if ($activation > $start) {
			$starting = $activation;
		}

		// Set the start date to the activation date.
		return $this->calcFraction($starting, $termination);
	}

	/**
	 * Validate the calc operation.
	 * @param type $billrunKey
	 * @param type $start
	 * @param type $end
	 * @return boolean
	 */
	protected function validateCalcFractionOfMonth($billrunKey, $start, $end, $activation, $deactivation) {
		// Validate dates.
		if ($deactivation && ($deactivation < $activation)) {
			Billrun_Factory::log("Invalid dates in subscriber service");
			return false;
		}

		// Validate the dates.
		if ($end < $start) {
			return false;
		}

		// Normalize the activation.
		$activationDay = (int) date('d', $activation);
		$normalizedStamp = $billrunKey . (int) str_pad($activationDay, 2, '0', STR_PAD_LEFT) . "000000";
		$normalizedActivation = strtotime($normalizedStamp);

		if ($end < $normalizedActivation) {
			Billrun_Factory::log("Service activation date is after billing end.");
			return false;
		}

		return true;
	}

	/**
	 * Calc the fraction between two dates out of a month.
	 * @param int $start - Start epoch
	 * @param int $end - End epoch
	 * @return float value.
	 */
	protected function calcFraction($start, $end) {
		$days_in_month = (int) date('t', $start);
		$start_day = date('j', $start);
		$end_day = date('j', $end);
		$start_month = date('F', $start);
		$end_month = date('F', $end);

		if ($start_month == $end_month) {
			$days_in_plan = (int) $end_day - (int) $start_day + 1;
		} else {
			$days_in_previous_month = $days_in_month - (int) $start_day + 1;
			$days_in_current_month = (int) $end_day;
			$days_in_plan = $days_in_previous_month + $days_in_current_month;
		}

		$fraction = $days_in_plan / $days_in_month;
		return $fraction;
	}

}

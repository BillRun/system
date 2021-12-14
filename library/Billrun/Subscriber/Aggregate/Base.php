<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents a base record to be aggregated for a subscriber
 *
 * @package  Subscriber Aggregate
 * @since    5.2
 */
class Billrun_Subscriber_Aggregate_Base {

	/**
	 * Unique key to adress this record.
	 * @var string
	 */
	protected $key;

	/**
	 * Current date span, with date fields.
	 * @var array
	 */
	protected $current = array();

	/**
	 * Array containing date records
	 * @var array
	 */
	protected $dates = array();

	/**
	 * Create a new instance of the base aggregateable class
	 * @param type $key
	 */
	public function __construct($key) {
		$this->key = $key;
	}

	/**
	 * Get an array representation of the data to be aggregated
	 * @return array - Containing key and dates
	 */
	public function getValues() {
		$toReturn = array();
		$toReturn['key'] = $this->key;
		$toReturn['dates'] = $this->dates;
		if ($this->current) {
			$toReturn['dates'][] = $this->current;
		}
		return $toReturn;
	}

	/**
	 * Add a date span
	 * @param array $date - Contains a start date, might not contain an end date.
	 */
	public function add($date) {
		if (!isset($date['start'])) {
			throw new Exception("Invalid date!!!! " . print_r($date, 1));
		}

		if (isset($date['end'])) {
			$this->dates[] = $date;
		} else {
			$this->setCurrent($date);
		}
	}

	protected function setCurrent($date) {
		if (!$this->current) {
			$this->current = $date;
			return;
		}

		if ($date['start'] < $this->current['start']) {
			throw new Exception("Invalid value - DB corrupted?");
		}

		$this->current['end'] = $date['start'];
		$this->dates[] = $this->current;
		$this->current = $date;
	}

}

<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing calculator class for 016 records
 *
 * @package  calculator
 * @since    1.0
 */
class Billrun_Calculator_Rate_016 extends Billrun_Calculator_Rate {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = "016";

	public function __construct($options = array()) {
		parent::__construct($options);
		$this->loadRates();
	}

	/**
	 * method to receive the lines the calculator should take care
	 * 
	 * @return Mongodloid_Cursor Mongo cursor for iteration
	 */
	protected function getLines() {

		$lines = Billrun_Factory::db()->linesCollection();

		$lines_arr = $lines->query()
				->equals('source', 'ilds')
				->equals('type', static::$type)
				->notExists($this->ratingField);

		foreach ($lines_arr as $entity) {
			$this->data[] = $entity;
		}

		return $this->data;
	}

	/**
	 * Execute the calculation process
	 */
	public function calc() {

		Billrun_Factory::dispatcher()->trigger('beforeCalculateData', array('data' => $this->data));

		foreach ($this->data as $item) {
			// update billing line with ratingField & duration
			if (!$this->updateRow($item)) {
				Billrun_Factory::log()->log("stamp:" . $item->get('stamp') . " cannot update rate for billing line", Zend_Log::ERR);
				continue;
			}
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
	public function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteRow', array('row' => $row));
		$usage_type = $this->getLineUsageType($row);
		$volume = $this->getLineVolume($row, $usage_type);

		if ($volume === false) {
			$volume = 0;
		}

		$rate = $this->getLineRate($row, $usage_type);
		if (empty($rate['key'])) {
			return false;
		}

		$current = $row->getRawData();

		$added_values = array(
			'duration' => $volume,
			$this->ratingField => $rate ? $rate->createRef() : $rate,
		);
		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);

		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteRow', array('row' => $row));
		return true;
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineRate
	 */
	protected function getLineRate($row, $usage_type) {

		$unformatted_called_number = $row->get('called_number');
		$pos = strpos($unformatted_called_number, '016');
		$called_number = substr($unformatted_called_number, $pos + 3);

		$line_time = $row->get('urt');
		$matchedRate = false;

		if ($row['prepaid'] == '1') {
			$called_number_prefixes = $this->getPrefixes('#' . $called_number);
		} else {
			$called_number_prefixes = $this->getPrefixes($called_number);
		}
		foreach ($called_number_prefixes as $prefix) {
			if (isset($this->rates[$prefix])) {
				foreach ($this->rates[$prefix] as $rate) {
					if (isset($rate['rates'][$usage_type])) {
						if ($rate['from'] <= $line_time && $rate['to'] >= $line_time) {
							foreach ($rate['params']['out_circuit_group'] as $groups) {
								$matchedRate = $rate;
								break 3;
							}
						}
					}
				}
			}
		}
		if (!$matchedRate) {
			$matchedRate = $this->rates['UNRATED'];
		}

		return $matchedRate;
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineUsageType
	 */
	protected function getLineUsageType($row) {
		return 'call';
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineVolume
	 */
	protected function getLineVolume($row, $usage_type) {
		$call_start_time = $row->get('call_start_time');
		$call_end_time = $row->get('call_end_time');
		if (empty($call_start_time) || empty($call_end_time))
			return FALSE;

		$date_hack = substr(date("Y"), 0, 2);
		$start_time = strtotime($date_hack . $call_start_time);
		$end_time = strtotime($date_hack . $call_end_time);

		$duration = $end_time - $start_time;

		if ($duration < 0 || $duration > 86400) // 86400 = 1 DAY
			return FALSE;

		return $duration;
	}

}

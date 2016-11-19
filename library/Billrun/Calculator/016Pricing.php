<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing calculator for 016 pricing billing lines with customer price.
 *
 * @package  calculator
 * @since    1.0
 */
class Billrun_Calculator_016Pricing extends Billrun_Calculator {

	/**
	 * name of the pricing field
	 *
	 * @var string
	 */
	protected $pricingField = 'aprice';

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = "016pricing";

	/**
	 * an access price that would be added to the final price
	 * @var float
	 */
	protected $two_way_access_price;

	public function __construct($options = array()) {
		parent::__construct($options);
		$this->loadRates();
		$this->two_way_access_price = round(Billrun_Factory::config()->getConfigValue('016_two_way.access_price', 1.00), 2);
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
				->equals('type', '016')
				->exists('arate')
				->notExists('price_customer');

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
				Billrun_Factory::log()->log("stamp:" . $item->get('stamp') . " cannot update billing line", Zend_Log::ERR);
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

		$current = $row->getRawData();

		$records_type = '000';
		$sample_duration_in_sec = '1';

		$rate = $this->getRowRate($row);
		$volume = $row->get('duration');

		if ($volume == '0' || empty($volume)) {
			$records_type = '005';
			$pricingData = '0.0000';
		} else if (!$rate) {
			$records_type = '002';
			$sample_duration_in_sec = '0';
			$pricingData = '0.0000';
		} else if ($row['is_in_glti'] == '1') {
			$pricingData = '0';
		} else {
			$accessPrice = isset($rate['rates']['call']['access']) ? $rate['rates']['call']['access'] : 0;
			$pricingData = ($rate['key'] == 'ILD_PREPAID' ? 0 : $this->two_way_access_price) + $accessPrice + Billrun_Util::getPriceByRates($rate, 'call', $volume);

			if ($pricingData === FALSE) {
				Billrun_Factory::log()->log("fail calc charge line, stamp: " . $row->get('stamp'), Zend_Log::ERR);
				return FALSE;
			}
		}

		$added_values = array(
			'sampleDurationInSec' => $sample_duration_in_sec,
			'charge' => round($pricingData, 4),
			'records_type' => $records_type,
			'price_customer' => round($pricingData, 4)
		);

		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);

		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteRow', array('row' => $row));

		return TRUE;
	}

	/**
	 * gets an array which represents a db ref (includes '$ref' & '$id' keys)
	 * @param type $db_ref
	 */
	protected function getRowRate($row) {
		return $this->getRateByRef($row->get('arate', true));
	}

	protected function getRateByRef($rate_ref) {
		if (isset($rate_ref['$id'])) {
			$id_str = strval($rate_ref['$id']);
			if (isset($this->rates[$id_str])) {
				return $this->rates[$id_str];
			}
		}
		return null;
	}

	/**
	 * Caches the rates in the memory for fast computations
	 */
	protected function loadRates() {

		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$rates = $rates_coll->query()->cursor()->setReadPreference(MongoClient::RP_SECONDARY_PREFERRED);
		foreach ($rates as $rate) {
			$rate->collection($rates_coll);
			$this->rates[strval($rate->getId())] = $rate;
		}
	}

	protected static function getCalculatorQueueType() {
		
	}

	protected function isLineLegitimate($line) {
		
	}

}

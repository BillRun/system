<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing  calculator for  pricing  billing lines with wholesale national roaming price.
 *
 * @package  calculator
 * @since    0.5
 */
class Billrun_Calculator_Wholesale_NationalRoamingPricing extends Billrun_Calculator_Wholesale {

	const MAIN_DB_FIELD = 'price_nr';

	protected $pricingField = self::MAIN_DB_FIELD;
	protected $nrCarriers = array();

	public function __construct($options = array()) {
		parent::__construct($options);
		foreach (Billrun_Factory::db()->carriersCollection()->query(array('key' => 'NR')) as $nrCarir) {
			$nrCarir->collection(Billrun_Factory::db()->carriersCollection());
			$this->nrCarriers[] = $nrCarir->createRef();
		}
	}

	/**
	 * @see Billrun_Calculator::getLines
	 */
	protected function getLines() {
		$lines = $this->getQueuedLines(array()); //array('type' => 'nsn')
		return $lines;
	}

	public function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteRow', array($row, $this));

		//@TODO  change this  be be configurable.
		$pricingData = array();
		$row->collection(Billrun_Factory::db()->linesCollection());
		$zoneKey = $this->isLineIncoming($row) ? 'incoming' : $this->loadDBRef($row->get(Billrun_Calculator_Wholesale_Nsn::MAIN_DB_FIELD, true))['key'];

		if (isset($row['usagev']) && $zoneKey) {
			$carir = $this->loadDBRef($row->get(in_array($row->get('wsc', true), $this->nrCarriers) ? 'wsc' : 'wsc_in', true));
			$rates = $this->getCarrierRateForZoneAndType($carir, $zoneKey, $row['usaget']);
			if (!$rates) {
				Billrun_Factory::log()->log(" Failed finding rate for row : " . print_r($row['stamp'], 1), Zend_Log::DEBUG);
				return false;
			}
			$pricingData = $this->getLinePricingData($row['usagev'], $rates);
			$row->setRawData(array_merge($row->getRawData(), $pricingData));
		} else {
			Billrun_Factory::log()->log(" No usagev or zone : {$row['usagev']} && $zoneKey for line with stamp: " . $row['stamp'], Zend_Log::NOTICE);
			return false;
		}
		
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteRow', array($row, $this));
		return true;
	}

	/**
	 * @see Billrun_Calculator::isLineLegitimate()
	 */
	public function isLineLegitimate($line) {
		return $line['type'] == 'nsn' &&
			$line->get(Billrun_Calculator_Wholesale_Nsn::MAIN_DB_FIELD, true) &&
			( ($line['record_type'] === "12" && in_array($line->get('wsc', true), $this->nrCarriers)) ||
			($line['record_type'] === "11" && in_array($line->get('wsc_in', true), $this->nrCarriers)) );
	}

}

<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator for  pricing  billing lines with wholesale price.
 *
 * @package  calculator
 * @since    0.5
 */
class Billrun_Calculator_Wholesale_WholesalePricing extends Billrun_Calculator_Wholesale {

	const MAIN_DB_FIELD = 'pprice';

	protected $pricingField = self::MAIN_DB_FIELD;

	/**
	 * @see Billrun_Calculator_Base_Rate
	 * @var type 
	 */
	protected $linesQuery = array('type' => 'nsn',);
	protected $count = 0;

	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['lines_query'])) {
			$this->linesQuery = $options['lines_query'];
		}
	}

	/**
	 * @see Billrun_Calculator::getLines
	 */
	protected function getLines() {
		$lines = $this->getQueuedLines(array());
		return $lines;
	}

	/**
	 * make the calculation
	 */
	public function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorUpdateRow', array($row, $this));
		$pricingData = array();
		$row->collection(Billrun_Factory::db()->linesCollection());
		$zoneKey = ($this->isLineIncoming($row) ? 'incoming' : $this->loadDBRef($row->get(Billrun_Calculator_Wholesale_Nsn::MAIN_DB_FIELD, true))['key']);

		if (isset($row['usagev']) && $zoneKey) {
			$rates = $this->getCarrierRateForZoneAndType(
				$this->loadDBRef($row->get($this->isLineIncoming($row) ? 'wsc_in' : 'wsc', true)), $zoneKey, $row['usaget'], ($this->isPeak($row) ? 'peak' : 'off_peak')
			);
			if ($rates) {
				$pricingData = $this->getLinePricingData($row['usagev'], $rates);

				//todo add peak/off peak to the data.
				$row->setRawData(array_merge($row->getRawData(), $pricingData));
			} else {
				Billrun_Factory::log()->log(" Failed finding rate for row : " . print_r($row['stamp'], 1), Zend_Log::DEBUG);
			}
		} else {
			Billrun_Factory::log()->log($this->count++ . " no usagev or zone : {$row['usagev']} && $zoneKey for line with stamp: " . $row['stamp'], Zend_Log::NOTICE);
			return false;
		}

		Billrun_Factory::dispatcher()->trigger('afterCalculatorUpdateRow', array($row, $this));
		return $row;
	}

	/**
	 * Check if the line direction is incoming to golan or outgoing from golan.
	 * @param $row the line to check.
	 * @return true is the line  is incoming to golan.
	 */
	protected function isLineIncoming($row) {
		$carir = $this->loadDBRef($row->get('wsc', true));
		return $carir['key'] == 'GOLAN' || $carir['key'] == 'NR';
	}

	/**
	 * @see Billrun_Calculator::isLineLegitimate()
	 */
	public function isLineLegitimate($line) {
		return $line['type'] == 'nsn' &&
			$line->get('pzone', true) &&
			($line->get(Billrun_Calculator_Carrier::MAIN_DB_FIELD, true) !== null && $line->get(Billrun_Calculator_Carrier::MAIN_DB_FIELD . "_in", true) != null) &&
			$line->get(Billrun_Calculator_Wholesale_Nsn::MAIN_DB_FIELD, true) != false && in_array($line['record_type'], $this->wholesaleRecords);
	}

}

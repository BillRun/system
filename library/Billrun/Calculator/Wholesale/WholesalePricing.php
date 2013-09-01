<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing calculator for  pricing  billing lines with wholesale price.
 *
 * @package  calculator
 * @since    0.5
 */
class Billrun_Calculator_Wholesale_WholesalePricing extends Billrun_Calculator_Wholesale {
	
	const MAIN_DB_FIELD = 'price_provider';
	
	protected $pricingField = self::MAIN_DB_FIELD;
	
	/**
	 * @see Billrun_Calculator_Base_Rate
	 * @var type 
	 */
	protected $linesQuery =array('type'=> 'nsn',);
	
	protected $count  =0 ;
	
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
		$lines =  $this->getQueuedLines($this->linesQuery);
		return $lines;
	}

	/**
	 * @see Billrun_Calculator::updateRow
	 */
	protected function updateRow($row) {
	
		$pricingData = array();
		$row->collection(Billrun_Factory::db()->linesCollection());
		$zoneKey = ($this->isLineIncoming($row) ?  'incoming' : $row[Billrun_Calculator_Wholesale_Nsn::MAIN_DB_FIELD]['key']);
		
		if (isset($row['usagev']) && $zoneKey) {
			$rates =  $this->getCarrierRateForZoneAndType(
									 $row[($this->isLineIncoming($row)) ?'carir_in' : 'carir'], 
									$zoneKey, 
									$row['usaget'], 
									($this->isPeak($row) ? 'peak' : 'off_peak')
							);
			if($rates) {
				$pricingData = $this->getLinePricingData($row['usagev'], $rates);
				
				//todo add peak/off peak to the data.
				$row->setRawData(array_merge($row->getRawData(), $pricingData));
			} else {
				Billrun_Factory::log()->log( " Failed finding rate for row : ". print_r($row['stamp'],1),Zend_Log::DEBUG);
			}
		} else {
			Billrun_Factory::log()->log($this->count++. " no usagev or zone : {$row['usagev']} && $zoneKey : ". print_r($row,1),Zend_Log::DEBUG);
			return false;
		}
		
		return true;
	}	
	
	/**
	 * Check if the line direction is incoming to golan or outgoing from golan.
	 * @param $row the  line to check.
	 * @return true is the line  is incoming to golan.
	 */
	protected function isLineIncoming($row) {
		return $row['carir']['key'] == 'GOLAN'  ||  $row['carir']['key'] == 'NR';
	}

	/**
	 * @see Billrun_Calculator::isLineLegitimate()
	 */
	protected function isLineLegitimate($line) {		
		return ($line[Billrun_Calculator_Carrier::MAIN_DB_FIELD] !== null || $line[Billrun_Calculator_Carrier::MAIN_DB_FIELD ."_in"] !== null) &&
				$line[Billrun_Calculator_Wholesale_Nsn::MAIN_DB_FIELD] !== false &&
				in_array($line['record_type'], $this->wholesaleRecords);
	}

}


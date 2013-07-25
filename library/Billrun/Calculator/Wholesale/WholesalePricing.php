<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing calculator for  pricing  billing lines with customer price.
 *
 * @package  calculator
 * @since    0.5
 */
class Billrun_Calculator_Wholesale_WholesalePricing extends Billrun_Calculator_Wholesale {
	
	const DEF_CALC_DB_FIELD = 'price_provider';
	
	protected $pricingField = self::DEF_CALC_DB_FIELD;
	
	/**
	 * @see Billrun_Calculator_Base_Rate
	 * @var type 
	 */
	protected $linesQuery =array(
								'type'=> 'nsn',
								 'record_type' => array('$in' =>  array('11','12','08','09'),), //TODO move wholesale type to configuration
						);
	
	
	
	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['lines_query'])) {
			$this->linesQuery = $options['lines_query'];
		}
	}
	
	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();

		return $lines->query($this->linesQuery)	
						->exists(Billrun_Calculator_Carrier::DEF_CALC_DB_FIELD)->notEq(Billrun_Calculator_Carrier::DEF_CALC_DB_FIELD,null)
						->exists(Billrun_Calculator_Wholesale_Call::DEF_CALC_DB_FIELD)
						->notExists($this->pricingField)->cursor()->limit($this->limit);
	}

	protected function updateRow($row) {
				
		$pricingData = array();
		$row->collection(Billrun_Factory::db()	->linesCollection());
		$zoneKey = ($this->isLineIncoming($row) ?  'incoming' : $row[Billrun_Calculator_Wholesale_Call::DEF_CALC_DB_FIELD]['key']);
		
	if (isset($row['usagev']) && $zoneKey) {
			$rates =  $this->getCarrierRateForZoneAndType(
									$row['carir'], 
									$zoneKey, 
									$row['usaget'], 
									($this->isPeak($row) ? 'peak' : 'off_peak')
							);
			if($rates) {
				$pricingData = $this->getLinePricingData($row['usagev'], $rates);
				
				//todo add peak/off peak to the data.
				$row->setRawData(array_merge($row->getRawData(), $pricingData));
			}
		}
		
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteRow', array('row' => $row));
	}	
	
}


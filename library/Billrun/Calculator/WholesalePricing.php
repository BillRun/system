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
class Billrun_Calculator_WholesalePricing extends Billrun_Calculator_Wholesale {
	const DEF_CALC_DB_FIELD = 'price_provider';
	
	protected $pricingField = self::DEF_CALC_DB_FIELD;
	
	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();

		return $lines->query(array(
								'type'=> 'nsn',
								 'record_type' => array('$in' =>  array('11','12','08','09'),), //TODO movewholesale type to configuration
						))				
						->exists(Billrun_Calculator_Carrier::DEF_CALC_DB_FIELD)->notEq(Billrun_Calculator_Carrier::DEF_CALC_DB_FIELD,null)
						->exists('customer_rate')->notExists($this->pricingField)->cursor()->limit($this->limit);
	}

	protected function updateRow($row) {
		
		$zoneKey = $this->isLineIncoming($row) ?  'incoming' : $row['customer_rate']['key'];//$this->getLineZone($row, $row['usaget']);		
		$pricingData = array();
		$row->collection(Billrun_Factory::db()->linesCollection());
		
		if (isset($row['usagev']) && $zoneKey) {
			$pricingData = $this->getLinePricingData($row['usagev'], $row['usaget'], $row['carir'], $zoneKey,isset($row['peak']) ? $row['peak'] : null ); //$this->priceLine($volume, $usage_type, $rate, $subscriber);
			$row->setRawData(array_merge($row->getRawData(), $pricingData));
		}	
	}
	
}


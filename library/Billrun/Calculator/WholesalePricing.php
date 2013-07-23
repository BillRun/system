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
	const DEF_CALC_DB_FIELD = 'price_wholesale';
	
	protected $pricingField = self::DEF_CALC_DB_FIELD;
	
	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();

		return $lines->query(array(
								'type'=> 'nsn',
								'$or' => array(
									array( 'record_type' => '11', 'out_circuit_group_name' => array('$ne' => '')),
									array('record_type' => '12', 'in_circut_group_name' => array('$ne' => '')),
								)
						))				
						->exists('carir')->notEq('carir',null)->exists('usaget')->notExists($this->pricingField)->cursor()->limit($this->limit);
	}

	protected function updateRow($row) {
		$zoneKey = $this->getLineZone($row, $row['usaget']);		
		$pricingData = array();
		$row->collection(Billrun_Factory::db()->linesCollection());
		
		if (isset($row['usagev']) && $zoneKey) {
			$pricingData = $this->getLinePricingData($row['usagev'], $row['usaget'], $row['carir'], $zoneKey); //$this->priceLine($volume, $usage_type, $rate, $subscriber);
			$row->setRawData(array_merge($row->getRawData(), $pricingData));
		}	
	}
	
}


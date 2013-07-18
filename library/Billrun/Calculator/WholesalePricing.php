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

	protected $pricingField = 'price_wholesale';
	
	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();

		return $lines->query()
				->in('type', array( 'nsn' ))
				->exists('customer_rate')->notEq('customer_rate', FALSE)->notExists($this->pricingField)->cursor()->limit($this->limit);
	}

	protected function updateRow($row) {
		$zoneKey = $this->getLineZone($row, $row['usaget']);
		$carrier = $this->getLineCarrier($row);		
		$pricingData = array();

		if (isset($row['usagev'])) {
			$pricingData = $this->getLinePricingData($row['usagev'], $row['usaget'], $carrier, $zoneKey); //$this->priceLine($volume, $usage_type, $rate, $subscriber);
			$row->setRawData(array_merge($row->getRawData(), $pricingData));
		} else {
			//@TODO error?
		}		
	}
	
}


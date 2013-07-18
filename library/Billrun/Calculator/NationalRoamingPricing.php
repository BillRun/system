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
class Billrun_Calculator_NationalRoamingPricing extends Billrun_Calculator_Wholesale {

	protected $pricingField = 'price_wholesale_extra';
	
	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();

		return $lines->query(array(
						'out_circuit_group' => array(
								'$gt' => "599",
								'$lt' => "599",
							),
						 'in_circut_group' => array(
 							'$gt' => "599",
							'$lt' => "588",
						 ),
				))->in('type', array('nsn'))
				->notExists($this->pricingField)->cursor()->limit($this->limit);
	}

	protected function updateRow($row) {
		$carrier = $this->getLineCarrier($row);
		
		//@TODO  change this  be be configurable.
		$pricingData = array();

		if (isset($row['usagev'])) {
			$pricingData = $this->getLinePricingData($row['usagev'], $row['usaget'], $carrier, 'none'); //$this->priceLine($volume, $usage_type, $rate, $subscriber);
			$row->setRawData(array_merge($row->getRawData(), $pricingData));
		} else {
			//@TODO error?
		}		
	}


}


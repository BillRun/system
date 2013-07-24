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
	const DEF_CALC_DB_FIELD = 'price_nr';
	
	protected $pricingField = self::DEF_CALC_DB_FIELD;
	
	protected $nrCarrier = null;
	
	public function __construct($options = array()) {
		parent::__construct($options);
		$this->nrCarrier = Billrun_Factory::db()->carriersCollection()->query(array('key'=>'NR'))->cursor()->current();
	}
	
	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();

		return $lines->query(array(
								'type'=> 'nsn',
								'$or' => array(
									array( 'record_type' => '12', 'out_circuit_group_name' => array('$regex'=>'^RCEL')),
									array('record_type' => '11', 'in_circut_group_name' => array('$regex'=>'^RCEL')),
								)
							))
				->exists('customer_rate')->notExists($this->pricingField)->cursor()->limit($this->limit);
	}

	protected function updateRow($row) {
		$zoneKey = $this->isLineIncoming($row) ?  'incoming' : $row['customer_rate']['key'];
		
		//@TODO  change this  be be configurable.
		$pricingData = array();

		if (isset($row['usagev'])) {
			$pricingData = $this->getLinePricingData($row['usagev'], $row['usaget'], $this->nrCarrier, $zoneKey);
			$row->setRawData(array_merge($row->getRawData(), $pricingData));
		}	
	}
}


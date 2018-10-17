<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents the service data to be aggregated.
 */
class Billrun_Cycle_Data_Service extends Billrun_Cycle_Data_Plan {
	
	protected $quantity = 1;
	protected $planIncluded = FALSE;
	protected $serviceID = FALSE;
	
	public function __construct(array $options) {
		if(!isset($options['name'], $options['cycle'])) {
			throw new InvalidArgumentException("Received empty service!");
		}
		$this->name = $options['name'];
		$this->plan = $options['plan'];
		$this->cycle = $options['cycle'];
		$this->quantity = Billrun_Util::getFieldVal($options['quantity'],1);
		$this->planIncluded = Billrun_Util::getFieldVal($options['included'], FALSE);
		$this->serviceID = Billrun_Util::getFieldVal($options['service_id'], FALSE);
		$this->constructOptions($options);
		$this->foreignFields = $this->getForeignFields(array('service' => $options), $this->stumpLine);
	}
	
	protected function getCharges($options) {
		if( $this->planIncluded && !Billrun_Factory::config()->getConfigValue('customer.aggregator.charge_included_service',TRUE)) {
			return [ 'charge' => 0 ];
		}
			
		return parent::getCharges($options);
	}
	
	/**
	 * Translate the plan values to service values.
	 * @return type
	 */
	protected function getFlatLine() {
		$flatLine = parent::getFlatLine();	
		$flatLine['service'] = $this->name;
		$flatLine['name'] = $this->name;
		$flatLine['usagev'] = $this->quantity;
		if($this->planIncluded) {
			$flatLine['included_in_plan'] = $this->planIncluded;
		}
		if($this->serviceID) {
			$flatLine['service_id'] = $this->serviceID;
		}
		$flatLine['type'] = 'service';
		return $flatLine;
	}
	
	protected function generateLineStamp($line) {
		return md5($line['usagev'].$line['charge_op']. $line['aid'] . $line['sid'] . $this->name . $this->cycle->start() . $this->cycle->key().$this->serviceID);
	}

}

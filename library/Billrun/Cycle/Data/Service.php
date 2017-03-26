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
	
	public function __construct(array $options) {
		if(!isset($options['name'], $options['cycle'])) {
			throw new InvalidArgumentException("Received empty service!");
		}
		$this->plan = $options['name'];
		$this->cycle = $options['cycle'];
		$this->quantity = Billrun_Util::getFieldVal($options['quantity'],1);
		$this->constructOptions($options);
	}
	
	/**
	 * Translate the plan values to service values.
	 * @return type
	 */
	protected function getFlatLine() {
		$flatLine = parent::getFlatLine();	
		$planValue = $flatLine['plan'];
		unset($flatLine['plan']);
		$flatLine['service'] = $planValue;
		$flatLine['name'] = $planValue;
		$flatLine['usagev'] = $this->quantity;
		$flatLine['type'] = 'service';
		return $flatLine;
	}
	
	protected function generateLineStamp($line) {
		return md5($line['usagev'].$line['charge_op']. $line['aid'] . $line['sid'] . $this->plan . $this->cycle->start() . $this->cycle->key());
	}

}

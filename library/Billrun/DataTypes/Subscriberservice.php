<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents a service to be used by a subscriber.
 */
class Billrun_DataTypes_Subscriberservice {
	protected $name = null;
	protected $from = null;
	protected $to = null;
	
	public function __construct(array $options) {
		if(!isset($options['from'], $options['to'], $options['name'])) {
			return;
		}
		
		// Get the date strings.
		$from = strtotime($options['from']);
		$to = strtotime($options['to']);
		
		// Validate
		if($from === -1 || $to === -1) {
			return;
		}
		
		$this->to = $to;
		$this->from = $from;
		$this->name = $options['name'];
	}
	
	/**
	 * Check if the service is valid.
	 * @return true if valid.
	 */
	public function isValid() {
		if(empty($this->name) || !is_string($this->name) || empty($this->from) || empty($this->to)) {
			return false;
		}
		
		// Validate the dates.
		if(($this->from > $this->to)) {
			return false;
		}
		
		// Check in the mongo.
		$ratesColl = Billrun_Factory::db()->ratesCollection();
		$serviceQuery = Billrun_Util::getDateBoundQuery($this->from, true);
		$serviceQuery['name'] = $this->name;
		$serviceQuery['type'] = 'service';
		$service = $ratesColl->query($serviceQuery)->cursor()->current();
		
		return !$service->isEmpty();
	}
}

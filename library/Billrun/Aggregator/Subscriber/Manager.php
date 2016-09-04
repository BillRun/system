<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Subscriber flat aggregator.
 *
 * @package  Aggregator
 * @since    5.1
 */
class Billrun_Aggregator_Subscriber_Manager {
	
	/**
	 * List of subscriber aggregators.
	 * @var array
	 */
	protected $aggregators;
	
	/**
	 * Construct the subscriber aggregator manager with aggregator types.
	 * @param array|string $types - Array of strings, or a $seperator seperated string
	 * of subscriber types.
	 * @param string $seperator - Seperator value for string input, coma by default.
	 */
	public function __construct($types, $seperator=",") {
		$aggregatorTypes = $this->getSubscriberAggregatorTypes($types, $seperator);
		
		// Go through the types.
		foreach ($aggregatorTypes as $type) {
			$normalizedType = ucfirst(strtolower($type));
			$aggregatorName = str_replace('_Manager', '_' . $normalizedType, __CLASS__);
			
			// Check if exists.
			if(!class_exists($aggregatorName, true)) {
				Billrun_Factory::log("Received invalid subscriber aggregator name " . print_r($type,1));
				continue;
			}
			
			// Create the aggregator.
			$this->aggregators[] = new $aggregatorName();
		}
	}
	
	/**
	 * Perform the aggregation logic
	 * @param $subscriber - Current subscriber to aggregate.
	 * @param $billrunKey - Current billrun key
	 * @return array of aggregate lines.
	 */
	public function aggregate($subscriber, $billrunKey) {
		$aggregated = array();
		
		foreach ($this->aggregators as $aggregator) {
			/* @var $aggregator Billrun_Aggregator_Subscriber_Base */
			$aggregated = array_merge($aggregated, $aggregator->save($subscriber, $billrunKey));
		}
	}
	
	/**
	 * Get the array of subscriber aggreagtor types.
	 * @param array|string $types - Input types
	 * @param string $seperator - Seperator
	 * @return array of aggregator types.
	 * @throws Exception
	 */
	protected function getSubscriberAggregatorTypes($types, $seperator) {
		$error = false;
		if(is_array($types)) {
			$inputTypes = $types;
		} else if(is_string($types)) {
			$inputTypes = explode($seperator, $types);
			$error = ($inputTypes===false);
		} else {
			$error = true;
		}
		
		if($error) {
			throw new Exception("Invalid subscriber aggreagtor types " . print_r($types,1));
		}
		
		return $inputTypes;
	}
}
 
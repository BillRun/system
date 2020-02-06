<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing interface aggregator class
 *
 * @package  calculator
 * @since    0.5
 */
abstract class Billrun_Aggregator extends Billrun_Base {

	protected $excludes = array();

	/**
	 *
	 * @var mixed The data container, should extend Traversable
	 */
	protected $data = null;

	protected $isValid = true;
	
	public function __construct($options = array()) {
		parent::__construct($options);

		$configPath = Billrun_Factory::config()->getConfigValue($this->getType() . '.billrun.config_path');
		if ($configPath) {
			$config = new Yaf_Config_Ini($configPath);
			if (isset($config->billrun->exclude)) {
				$this->excludes = $config->billrun->exclude->toArray();
			}
		}
	}

	public function isValid() {
		return $this->isValid;
	}	

	/**
	 * load aggregation data and prefom loading actions
	 * @return type
	 */
	public function load() {
		$this->beforeLoad();
		$this->data = $this->loadData();
		$this->afterLoad($this->data);
		return $this->data;
	}
	
		/**
	 * Actions to do before loading
	 */
	protected function beforeLoad() {
		
	}
	/**
	 * Actions to do after loading
	 */
	protected function afterLoad($data) {
		
	}
	
		/**
	 * load the data to aggregate
	 * Loads an array of aggregateable records.
	 * @return Billrun_Aggregator_Aggregateable
	 */
	abstract protected function loadData();
	
	
	public function getData() {
		return $this->data;
	}
	
	/**
	 * execute aggregate
	 */
	public function aggregate() {
		$data = empty($this->data) ?  $this->load() : $this->data;
		if(!is_array($data)) {
			// TODO: Create an aggregator exception.
			throw new Exception("Aggregator internal error.");
		}
		Billrun_Factory::dispatcher()->trigger('beforeAggregate', array($data, &$this));
		$this->beforeAggregate($data);
		
		$aggregated = array();
		
		// Go through the aggregateable
		foreach ($data as $aggregateable) {
			$result = $this->aggregatedEntity($aggregateable->aggregate(), $aggregateable);
			$aggregated = array_merge($aggregated, $result);
		}
		
		//$this->save($aggregated);
		Billrun_Factory::log("Done aggregating!");
	//	Billrun_Factory::dispatcher()->trigger('afterAggregate', array($data, &$this));
		return $this->afterAggregate($aggregated);
	}
	
	protected abstract function beforeAggregate($data);
	/**
	 * Actions to be taken/alter each aggregated entity.
	 * @return the altered aggregated entity
	 */
	protected function aggregatedEntity($aggregatedResults,$aggregatedEntity) {
		return $aggregatedResults;
	}
	
	/**
	 * The results of this function are returned from the aggregate function
	 * @param array $results - Array of aggregate results
	 */
	protected abstract function afterAggregate($results);

	/**
	 * load the subscriber billrun raw (aggregated)
	 * if not found, create entity with default values
	 * @param type $subscriber
	 *
	 * @return mixed
	 */
	protected function loadSubscriber($phone_number, $time) {
		$object = new stdClass();
		$object->phone_number = $phone_number;
		$object->time = $time;
		return $object;
	}

}

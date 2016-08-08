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

	/**
	 * execute aggregate
	 */
	abstract public function aggregate();

	/**
	 * load the data to aggregate
	 */
	abstract public function load();

	/**
	 * update the billing line with stamp to avoid another aggregation
	 *
	 * @param int $sid the subscriber id to update
	 * @param Mongodloid_Entity $line the billing line to update
	 *
	 * @return boolean true on success else false
	 */
	abstract protected function updateBillingLine($sid, $item);

	/**
	 * method to update the billrun by the billing line (row)
	 * @param Mongodloid_Entity $billrun the billrun line
	 * @param Mongodloid_Entity $line the billing line
	 *
	 * @return boolean true on success else false
	 */
	abstract protected function updateBillrun($billrun, $row);

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

	abstract protected function save($data);
}

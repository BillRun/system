<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi model for balance update by prepaid include
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Action_Update_Balance_Chargingplan extends Models_Action_Update_Balance_Abstract {

	/**
	 * the update method type
	 * @var string
	 */
	protected $updateType = 'Chargingplan';

	/**
	 * the data container of the entry that has the update properties
	 * can be prepaid include, charging plan, card secret, etc
	 * @var array
	 */
	protected $data = array(); // data container of the charging plan record

	/**
	 * the charging plan name
	 * @var string
	 */
	protected $name;

	public function __construct(array $params = array()) {
		if (isset($params['charging_plan'])) {
			$this->name = $params['charging_plan'];
		} else if (isset($params['charging_plan_name'])) {
			$this->name = $params['charging_plan_name'];
		} else {
			throw new Billrun_Exceptions_Api(0, array(), 'Charging plan name is not set');
		}
		parent::__construct($params);

		$this->init();
	}

	public function getData() {
		return $this->data;
	}

	protected function init() {
		$query = array(
			'name' => $this->name,
		);
		$chargingPlan = Billrun_Factory::db()->prepaidgroupsCollection()->query($query)->cursor()->current();

		if ($chargingPlan->isEmpty()) {
			throw new Billrun_Exceptions_Api(0, array(), 'Charging plan not found');
		}

		$chargingGroup = $chargingPlan->getRawData();
		foreach ($chargingGroup['include'] as $chargingEntry) {
			$ppIncludeParams = array(
				'sid' => $this->subscriber['sid'],
				'operation' => isset($chargingEntry['operation']) ? $chargingEntry['operation'] : $chargingGroup['operation'],
				'pp_includes_external_id' => (int) $chargingEntry['pp_includes_external_id'],
				'expiration_date' => strtotime('+' . $chargingEntry['period']['duration'] . ' ' . $chargingEntry['period']['unit']),
				'value' => isset($chargingEntry['usagev']) ? $chargingEntry['usagev'] :
				(isset($chargingEntry['cost']) ? $chargingEntry['cost'] :
				(isset($chargingEntry['total_cost']) ? $chargingEntry['total_cost'] : $chargingEntry['value'])),
			);
			$this->data[] = new Models_Action_Update_Balance_Prepaidinclude($ppIncludeParams);
		}
	}

	/**
	 * @todo
	 */
	public function update() {
		foreach ($this->data as $prepaidInclude) {
			$prepaidInclude->update();
		}
		return true;
	}

	/**
	 * create row to track the balance update
	 * @todo
	 */
	protected function createBillingLines() {
		foreach ($this->data as $prepaidInclude) {
			$prepaidInclude->createBillingLines();
		}
	}

	protected function trackChanges() {
		foreach ($this->data as $prepaidInclude) {
			$prepaidInclude->trackChanges();
		}
	}

}

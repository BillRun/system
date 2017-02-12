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
class Models_Balance_Update_Prepaidinclude extends Models_Balance_Update {

	protected $operation = 'inc';
	protected $data = array(); // data container of the prepaid includes record
	protected $subscriber = array();
	protected $chargeValue;
	protected $query;

	public function __construct(array $params = array()) {
		parent::__construct($params);
		if (!isset($params['sid'])) {
			throw new Billrun_Exceptions_Api(0, array(), 'Subscriber id is not define in input under prepaid include');
		}

		$field = $this->getLoadField($params);

		$this->load($field, $params[$field]);

		$this->loadSubscriber((int) $params['sid']);

		if (isset($params['operation']) && in_array($params['operation'], array('inc', 'set', 'new'))) {
			$this->operation = $params['operation'];
		}

		if (!isset($params['value'])) {
			throw new Billrun_Exceptions_Api(0, array(), 'Prepaid include value not defined in input');
		}

		$this->chargeValue = (int) $params['value'];
		$this->init();
	}

	protected function init() {
		$this->query = array(
			'sid' => $this->subscriber['sid'],
			'pp_includes_external_id' => $this->data['external_id'],
		);
		$this->preload();
	}

	protected function getLoadField($params) {
		if (isset($params['pp_includes_external_id'])) {
			return 'pp_includes_external_id';
		} else if (isset($params['pp_includes_name'])) {
			return 'pp_includes_name';
		} else {
			throw new Billrun_Exceptions_Api(0, array(), 'Prepaid include not defined in input');
		}
	}

	protected function load($field, $value) {
		$ppQuery = array($field => $value);
		$ppinclude = Billrun_Factory::db()->prepaidincludesCollection()->query($ppQuery)->cursor()->current();
		if ($ppinclude->isEmpty()) {
			throw new Billrun_Exceptions_Api(0, array(), 'Prepaid include not found');
		}
		$this->data = $ppinclude->getRawData();
	}

	protected function loadSubscriber($sid) {
		$subQuery = array(
			'$or' => array(
				'type' => array(
					'$exists' => false,
				), // backward compatibility (type not exists)
				'type' => 'subscriber'
			),
			'sid' => $sid,
		);
		$sub = Billrun_Factory::db()->subscribersCollection()->query($subQuery)->cursor()->current();
		if ($sub->isEmpty()) {
			throw new Billrun_Exceptions_Api(0, array(), 'Subscriber not found on prepaid include update');
		}
		$this->subscriber = $sub->getRawData();
	}

	public function update() {
		switch ($this->data['charging_by']) :
			case 'cost':
			case 'usagev':
				$key = 'balance.totals.' . $this->data['charging_by_usaget'] . '.' . $this->data['charging_by'];
				break;
			case 'total_cost':
			default:
				$key = 'balance.cost';
		endswitch;
		$update = array(
			$key => array(
				'$gte' => $this->chargeValue,
			),
		);
		$this->after = Billrun_Factory::db()->balancesCollection()->findAndModify($this->query, $update, null, array('new' => true));
	}

	protected function preload() {
		$this->before = Billrun_Factory::db()->balancesCollection()->query($this->query)->cursor()->current();
	}

	public function createLines() {
		
	}

}

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
class Models_Action_Update_Balance_Prepaidinclude extends Models_Action_Update_Balance_Abstract {

	/**
	 * the update method type
	 * @var string
	 */
	protected $updateType = 'Prepaidinclude';

	/**
	 * operation of update (inc, set, new)
	 * @var string
	 */
	protected $operation = 'inc';

	/**
	 * the data container of the entry that has the update properties
	 * can be prepaid include, charging plan, card secret, etc
	 * @var array
	 */
	protected $data = array(); // data container of the prepaid includes record

	/**
	 * the subscriber entry
	 * @var array
	 */
	protected $subscriber = array();

	/**
	 * the charging value to update
	 * @var type 
	 */
	protected $chargeValue;

	/**
	 * query to form on update
	 * @var array 
	 */
	protected $query;

	/**
	 * the balance entry before
	 * @var array
	 */
	protected $before;

	/**
	 * the balance entry after
	 * @var array
	 */
	protected $after;

	public function __construct(array $params = array()) {
		parent::__construct($params);
		if (!isset($params['sid'])) {
			throw new Billrun_Exceptions_Api(0, array(), 'Subscriber id is not define in input under prepaid include');
		}

		$query = $this->getLoadQuery($params);

		$this->load($query);

		$this->loadSubscriber((int) $params['sid']);

		if (isset($params['operation']) && in_array($params['operation'], array('inc', 'set', 'new'))) { // TODO: move array values to config
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

	protected function getLoadQuery($params) {
		if (isset($params['pp_includes_external_id'])) {
			return array('external_id' => $params['pp_includes_external_id']);
		} else if (isset($params['pp_includes_name'])) {
			return array('name' => $params['pp_includes_external_name']);
		} else if (isset($params['id'])) {
			return array('_id' => new MongoId($params['id']));
		} else if (isset($params['_id'])) {
			return array('_id' => new MongoId($params['_id']));
		} else {
			throw new Billrun_Exceptions_Api(0, array(), 'Prepaid include not defined in input');
		}
	}

	/**
	 * 
	 * @param type $ppQuery
	 * @throws Billrun_Exceptions_Api
	 */
	protected function load($ppQuery) {
		if (isset($ppQuery['id']) || isset($ppQuery['_id'])) {
			$balance = Billrun_Factory::db()->balancesCollection()->query($ppQuery)->cursor()->current();
			if ($balance->isEmpty()) {
				throw new Billrun_Exceptions_Api(0, array(), 'Balance not found');
			}
			$this->data = $balance->getRawData();
		} else {
			$ppinclude = Billrun_Factory::db()->prepaidincludesCollection()->query($ppQuery)->cursor()->current();
			if ($ppinclude->isEmpty()) {
				throw new Billrun_Exceptions_Api(0, array(), 'Prepaid include not found');
			}
			$this->data = $ppinclude->getRawData();
		}
	}

	/**
	 * method to load subscriber details
	 * @param type $sid
	 * @throws Billrun_Exceptions_Api
	 * @todo add connection type (limit to prepaid)
	 * @return array subscriber details
	 */
	protected function loadSubscriber($sid) {
		$subQuery = array(
			'$or' => array(
				array(
					'type' => array(
						'$exists' => false,
					)), // backward compatibility (type not exists)
				array('type' => 'subscriber'),
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
			'$inc' => array(
				$key => $this->chargeValue,
			),
			'$setOnInsert' => array(
				'from' => new MongoDate(),
				'aid' => $this->subscriber['aid'],
				'charging_type' => 'prepaid',
				'charging_by' => $this->data['charging_by'],
				'charging_by_usaget' => $this->data['charging_by_usaget'],
				'priority' => $this->data['priority'],
				'pp_includes_name' => isset($this->data['name']) ? $this->data['name'] : $this->data['pp_includes_name'],
			),
		);
		if (isset($this->query['to']) && Zend_Date::isDate($this->query['to'])) {
			$update['$set'] = array(
				'to' => new MongoDate(strtotime($this->query['to'])),
			);
		} else if (isset($this->data['unlimited']) && $this->data['unlimited']) {
			$update['$set'] = array(
				'to' => new MongoDate(Billrun_Utils_Time::UNLIMITED_DATE),
				'unlimited' => true,
			);
		} else { // fallback to 30 days charge (@TODO move to config)
			$update['$set'] = array(
				'to' => new MongoDate(strtotime('tomorrow', strtotime('+1 month')) - 1),
			);
		}
		if (isset($this->data['shared']) && $this->data['shared']) {
			$this->query['sid'] = 0;
			$update['shared'] = true;
		}
		$findAndModify = array(
			'new' => true,
			'upsert' => true,
		);
		$this->after = Billrun_Factory::db()->balancesCollection()->findAndModify($this->query, $update, null, $findAndModify);
	}

	protected function preload() {
		$this->before = Billrun_Factory::db()->balancesCollection()->query($this->query)->cursor()->current();
	}

	/**
	 * create row to track the balance update
	 */
	public function createTrackingLines() {
		$row = array(
			'source' => 'billapi',
			'type' => 'balance',
			'usaget' => 'balance',
			'charging_type' => $this->updateType,
			'urt' => new MongoDate(),
			'source_ref' => Billrun_Factory::db()->prepaidincludesCollection()->createRefByEntity($this->data),
			'aid' => $this->subscriber['aid'],
			'sid' => $this->subscriber['sid'],
			'pp_includes_name' => $this->data['name'],
			'pp_includes_external_id' => $this->data['external_id'],
			'charging_usaget' => $this->data['charging_by_usaget'],
			'balance_ref' => Billrun_Factory::db()->balancesCollection()->createRefByEntity($this->after),
			'balance_before' => $this->getBalanceBefore(),
			'balance_after' => $this->getBalanceAfter(),
		);
		if (isset($this->data['service_provider'])) { // backward compatibility
			$row['service_provider'] = $this->data['service_provider'];
		}
		$row['stamp'] = Billrun_Util::generateArrayStamp($row);
		Billrun_Factory::db()->linesCollection()->insert($row);
		return $row;
	}

	protected function getBalanceBefore($afterValue = null) {
		if (is_null($afterValue)) {
			$afterValue = $this->getBalanceAfter();
		}
		return $afterValue - $this->chargeValue;
	}

	protected function getBalanceAfter() {
		if (isset($this->after['balance']['cost'])) {
			return $this->after['balance']['cost'];
		}
		return $this->after['balance']['totals'][$this->data['charging_by_usaget']][$this->data['charging_by']];
	}

}

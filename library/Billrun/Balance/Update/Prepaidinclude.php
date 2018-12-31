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
class Billrun_Balance_Update_Prepaidinclude extends Billrun_Balance_Update_Abstract {

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
	 * the charging value to update
	 * @var type 
	 */
	protected $chargingValue;

	/**
	 * the charging limit for the prepaid include
	 * @var type 
	 */
	protected $chargingLimit;

	/**
	 * query to form on update
	 * @var array 
	 */
	protected $query;

	/**
	 * the balance entry before
	 * @var array
	 */
	protected $before = null;

	/**
	 * the balance entry after
	 * @var array
	 */
	protected $after = null;

	/**
	 * the balance entry after
	 * @var array
	 */
	protected $normalizeValue = 0;
	
	/**
	 * expiration date
	 * @var MongoDate
	 */
	protected $to = null;

	public function __construct(array $params = array()) {
		$query = $this->getLoadQuery($params);
		$this->load($query);
		if (isset($this->data['shared']) && $this->data['shared']) {
			$this->sharedBalance = true;
		}

		parent::__construct($params);

		if (isset($params['operation']) && in_array($params['operation'], array('inc', 'set', 'new'))) { // TODO: move array values to config
			$this->operation = $params['operation'];
		}

		if (!isset($params['value'])) {
			throw new Billrun_Exceptions_Api(0, array(), 'Prepaid include value not defined in input');
		}

		$this->chargingValue = (float) $params['value'];
		$this->init();

		// this should be done after init (load before state)
		if (isset($params['expiration_date'])) {
			$this->setTo($params['expiration_date']);
		} else {
			$this->setTo();
		}
		
		$this->chargingLimit = $this->getChargingLimit();;
	}

	public function getBefore() {
		return $this->before;
	}

	public function getAfter() {
		return $this->after;
	}

	protected function setTo($expirationDate = null) {
		if (isset($this->data['unlimited']) && $this->data['unlimited']) {
			$this->to = new MongoDate(strtotime(Billrun_Utils_Time::UNLIMITED_DATE));
		} else if ($expirationDate instanceof MongoDate) {
			$this->to = $expirationDate;
		} else if (is_numeric($expirationDate)) {
			$this->to = new MongoDate($expirationDate);
		} else if (is_string($expirationDate)) {
			$this->to = new MongoDate(strtotime($expirationDate));
		} else { // fallback to 30 days charge (@TODO move to config)
			$this->to = new MongoDate(strtotime('tomorrow +1 month') - 1);
		}
	}
	
	/**
	 * method to get the limit of the charging
	 * 
	 * @return number
	 */
	protected function getChargingLimit() {
		$query = Billrun_Utils_Mongo::getDateBoundQuery();
		$query['plan'] = Billrun_Util::getIn($this->subscriber, ['plan'], '');
		$plan = Billrun_Factory::db()->plansCollection()->query($query)->cursor()->current();

		if (isset($plan['pp_threshold'][$this->data['external_id']])) {
			return $plan['pp_threshold'][$this->data['external_id']];
		}
		return (-1) * PHP_INT_MAX;
	}
	
	protected function init() {
		$this->query = array(
			'pp_includes_external_id' => $this->data['external_id'],
		);
		if ($this->sharedBalance) {
			$this->query['aid'] = $this->subscriber['aid'];
		} else {
			$this->query['sid'] = $this->subscriber['sid'];
		}
		$this->preload();
	}

	protected function getLoadQuery($params) {
		if (isset($params['pp_includes_external_id'])) {
			return array('external_id' => $params['pp_includes_external_id']);
		} else if (isset($params['pp_includes_name'])) {
			return array('name' => $params['pp_includes_name']);
		} else if (isset($params['id'])) {
			return array('_id' => new MongoId($params['id']));
		} else if (isset($params['_id'])) {
			return array('_id' => new MongoId($params['_id']));
		} else {
			throw new Billrun_Exceptions_Api(0, array(), 'Prepaid include not defined in input');
		}
	}

	public function preValidate() {
		if (parent::preValidate() === false) {
			return false;
		}

		$balanceValue = $this->getBalanceValue($this->before);
		if (!$this->data['unlimited'] && $this->chargingLimit > ($balanceValue + $this->chargingValue)) {
			return false;
		}
		
		if (($balanceValue + $this->chargingValue) > 0) {
			return false;
		}
		
		return true;
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

	public function update() {
		$update = array(
			'$setOnInsert' => array(
				'from' => new MongoDate(),
				'aid' => $this->subscriber['aid'],
//				'charging_type' => 'prepaid',
				'connection_type' => 'prepaid',
				'charging_by' => $this->data['charging_by'],
				'charging_by_usaget' => $this->data['charging_by_usaget'],
				'priority' => $this->data['priority'],
				'pp_includes_name' => isset($this->data['name']) ? $this->data['name'] : $this->data['pp_includes_name'],
			),
		);
		
		if (isset($this->subscriber['service_provider'])) {
			$update['$setOnInsert']['service_provider'] = $this->subscriber['service_provider'];
		}

		$field =  $this->getChargingField();

		switch ($this->operation) :
			case 'new':
				$this->query['rand'] = rand(0, 1000000); // this will make the FAM to always insert
				// do not break here, need to set value
			case 'set':
				$update['$set'] = array(
					$field => $this->chargingValue,
				);
				break;
			case 'inc':
			default:
				$update['$inc'] = array(
					$field => $this->chargingValue,
				);

				break;
		endswitch;

		if (!empty($this->to)) {
			if (!isset($update['$set'])) {
				$update['$set'] = array();
			}
			$update['$set']['to'] = $this->to;
			if (isset($this->data['unlimited']) && $this->data['unlimited']) {
				$update['$setOnInsert']['unlimited'] = true;
			}
		}

		if (isset($this->data['shared']) && $this->data['shared']) {
			$this->query['sid'] = 0;
			$update['$setOnInsert']['shared'] = true;
		}
		$findAndModify = array(
			'new' => true,
			'upsert' => true,
		);
		$this->after = Billrun_Factory::db()->balancesCollection()->findAndModify($this->query, $update, null, $findAndModify);
		$this->handleMaxBalanceAfterUpdate();
	}
	
	/**
	 * method to handle maximum charging limit of balance
	 * @return type
	 */
	protected function handleMaxBalanceAfterUpdate() {
		try {
			if (!isset($this->after['_id'])) {
				return;
			}

			$balanceValue = $this->getBalanceValue($this->after);
			// value is negative
			if ($this->chargingLimit < $balanceValue) {
				return;
			}

			$query = array('_id' => $this->after->getId()->getMongoID());
			$update = array(
				'$set' => array(
					$this->getChargingField() => $this->chargingLimit,
				)
			);

			$options = array(
				'upsert' => false,
				'new' => true,
			);

			$this->after = Billrun_Factory::db()->balancesCollection()->findAndModify($query, $update, null, $options);
			$this->normalizeValue = $this->getBalanceValue($this->after) - $balanceValue;
		} catch (Exception $ex) {
			Billrun_Factory::log("Cannot handle max balance after update. " . $ex->getCode() . ": " . $ex->getMessage());
		}
	}
	
    /**
	 * get the balance key in the balance document
     * @return string the balance reference; if nested put dot for object hierarchy 
     */
	protected function getChargingField() {
		switch ($this->data['charging_by']) :
			case 'cost':
			case 'usagev':
				$key = 'balance.totals.' . $this->data['charging_by_usaget'] . '.' . $this->data['charging_by'];
				break;
			case 'total_cost':
			default:
				$key = 'balance.cost';
		endswitch;
		
		return $key;
	}
	
	/**
	 * get the balance value for the this charge
	 * 
	 * @param array $balance the balance object that the charging running on
	 * @return double the balance value
	 */
	public function getBalanceValue($balance) {
		switch ($this->data['charging_by']) :
			case 'cost':
			case 'usagev':
				return isset($balance['balance']['totals'][$this->data['charging_by_usaget']][$this->data['charging_by']]) ?
					$balance['balance']['totals'][$this->data['charging_by_usaget']][$this->data['charging_by']] : 0;
				break;
			case 'total_cost':
			default:
				return isset($balance['balance']['cost']) ? $balance['balance']['cost'] : 0;
		endswitch;
	}

	/**
	 * initialize method
	 */
	protected function preload() {
		$this->before = Billrun_Factory::db()->balancesCollection()->query($this->query)->cursor()->current();
	}

	/**
	 * create row to track the balance update
	 */
	protected function createBillingLines() {
		Billrun_Factory::dispatcher()->trigger('beforeBalanceUpdateCreateBillingLine', array($this));
		$row = array(
			'source' => 'billapi',
			'type' => 'balance',
			'usaget' => 'balance',
			'charging_type' => $this->updateType,
			'urt' => new MongoDate(),
			'source_ref' => Billrun_Factory::db()->prepaidincludesCollection()->createRefByEntity($this->data),
			'aid' => $this->subscriber['aid'],
			'sid' => isset($this->subscriber['sid']) ? $this->subscriber['sid'] : 0,
			'pp_includes_name' => $this->data['name'],
			'pp_includes_external_id' => $this->data['external_id'],
			'charging_usaget' => $this->data['charging_by_usaget'],
			'balance_ref' => Billrun_Factory::db()->balancesCollection()->createRefByEntity($this->after),
			'balance_before' => $this->getBalanceBefore(),
			'balance_after' => $this->getBalanceAfter(),
			'balance_normalized' => $this->normalizeValue,
		);
	
		$chargingValue = $row['balance_after'] - $row['balance_before'];
		if ($this->data['charging_by'] == 'usagev') {
			$row['usagev'] = $chargingValue;
		} else {
			$row['aprice'] = $chargingValue;
		}

		if (isset($this->subscriber['service_provider'])) { // backward compatibility
			$row['service_provider'] = $this->data['service_provider'];
		}
		if (!empty($this->additional)) {
			$row['additional'] = $this->additional;
		}
		$row['stamp'] = Billrun_Util::generateArrayStamp($row);
		Billrun_Factory::db()->linesCollection()->insert($row);
		Billrun_Factory::dispatcher()->trigger('afterBalanceUpdateCreateBillingLine', array($row, $this));
		return $row;
	}

	protected function getBalanceBefore($afterValue = null) {
		if (is_null($afterValue)) {
			$afterValue = $this->getBalanceAfter();
		}
		return $afterValue - $this->chargingValue;
	}

	protected function getBalanceAfter() {
		if (isset($this->after['balance']['cost'])) {
			return $this->after['balance']['cost'];
		}
		return $this->after['balance']['totals'][$this->data['charging_by_usaget']][$this->data['charging_by']];
	}

	/**
	 * method to track change in audit trail
	 * 
	 * @return true on success log change else false
	 */
	protected function trackChanges() {
		return Billrun_AuditTrail_Util::trackChanges('update', $this->subscriber['aid'] . '_' . (isset($this->subscriber['sid']) ? $this->subscriber['sid'] : 0), 
			'balances', $this->before->getRawData(), $this->after->getRawData());
	}

}

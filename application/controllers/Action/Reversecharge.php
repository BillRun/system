<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Realtime event action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       4.0
 */
class ReversechargeAction extends RealtimeeventAction {

	protected $event = null;
	protected $row = null;
	protected $balance = null;

	/**
	 * method to execute reverse charge event
	 */
	public function execute() {
		$this->allowed();
		$this->event = $this->getRequestData('event');
		$this->usaget = $this->getRequestData('usaget');
		Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/conf/reversecharge/conf.ini');
		if (empty($this->event['called_number']) && isset($this->event['called_number'])) {
			unset($this->event['called_number']);
		}
		$this->reverseCharge();
		$this->respond($this->row);
	}

	protected function getRequestData($key) {
		return $this->_request->getParam($key);
	}

	protected function reverseCharge() {
		if (!$this->createNewLine()) {
			$this->setErrorData();
			return false;
		}
		$this->updateBalance();
	}

	protected function setErrorData() {
		$this->row['reason'] = 'Reverse charge error';
		$this->row['usaget'] = $this->usaget; // For the responder
		$this->row['record_type'] = $this->usaget; // For the responder
		$this->row['usagev'] = 0;
	}

	protected function createNewLine() {
		if (!$this->getLineData()) {
			return false;
		}
		Billrun_Factory::db()->linesCollection()->insert($this->row);
		return true;
	}

	protected function updateBalance() {
		if (in_array($this->balance['charging_by_usaget'], array('cost', 'total_cost'))) {
			$currentPrice = $this->balance['balance']['cost'];
			$this->balance->set('balance.cost', $currentPrice + $this->row['aprice']);
		} else if ($this->balance['charging_by'] === 'cost') {
			$currentPrice = $this->balance['balance']['totals'][$this->balance['charging_by_usaget']][$this->balance['charging_by']];
			$this->balance->set('balance.totals.' . $this->balance['charging_by_usaget'] . '.' . $this->balance['charging_by'], $currentPrice + $this->row['aprice']);
		} else {
			$currentUsage = $this->balance['balance']['totals'][$this->balance['charging_by_usaget']][$this->balance['charging_by']];
			$this->balance->set('balance.totals.' . $this->balance['charging_by_usaget'] . '.' . $this->balance['charging_by'], $currentUsage + $this->row['usagev']);
		}
		return $this->balance->save();
	}

	protected function getBalance() {
		$balanceRef = $this->row['balance_ref'];
		$balances_coll = Billrun_Factory::db()->balancesCollection();
		if (!$balanceRef || !$balance = $balances_coll->getRef($balanceRef)) {
			Billrun_Factory::log("Reverse charge - cannot find balance", Zend_Log::WARN);
			return false;
		}
		$balance->collection($balances_coll);
		return $balance;
	}

	protected function getLineData() {
		if (!$originLine = $this->getOriginLine()) {
			return false;
		}
		$this->row = $this->event + $originLine->getRawData();
		if (!$this->balance = $this->getBalance()) {
			return false;
		}

		$fieldsToRemove = $this->getFieldsToRemove();
		foreach ($fieldsToRemove as $fieldToRemove) {
			unset($this->row[$fieldToRemove]);
		}

		$fieldsToUpdate = $this->getFieldsToUpdate();
		foreach ($fieldsToUpdate as $fieldToUpdate => $updateFunction) {
			if (isset($this->row[$fieldToUpdate]) && method_exists($this, $updateFunction)) {
				$this->row[$fieldToUpdate] = $this->{$updateFunction}($this->row[$fieldToUpdate]);
			}
		}

		return true;
	}

	protected function getFieldsToUpdate() {
		return Billrun_Factory::config()->getConfigValue('reversecharge.fieldsToUpdate', array());
	}

	protected function getOppositeValue($val) {
		if (!is_numeric($val)) {
			return 0;
		}

		return $val * (-1);
	}

	protected function getBalanceBefore() {
		return $this->getBalanceValue($this->balance);
	}

	protected function getBalanceAfter() {
		$balanceBefore = $this->getBalanceBefore();
		if ($this->balance->get('charging_by') === 'usagev') {
			return $balanceBefore + $this->row['usagev'];
		}
		return $balanceBefore + $this->row['aprice'];
	}

	protected function getBalanceValue($balance) {
		$charging_by_usaget = $balance->get('charging_by_usaget');
		if ($charging_by_usaget == 'total_cost' || $charging_by_usaget == 'cost') {
			return $balance->get('balance')['cost'];
		}
		$charging_by = $balance->get('charging_by');
		return $balance->get('balance')['totals'][$charging_by_usaget][$charging_by];
	}

	protected function getFieldsToRemove() {
		return Billrun_Factory::config()->getConfigValue('reversecharge.fieldsToRemove', array());
	}

	protected function getOriginLine() {
		$query = $this->getReverseChargedQuery();
		if (!$query) {
			Billrun_Factory::log('Cannot find reverse charge query. Probably wrong type. Details: ' . print_R($this->event, 1), Zend_Log::WARN);
			return false;
		}
		$linesCollection = Billrun_Factory::db()->linesCollection();
		$prevCharge = $linesCollection->query($query)->cursor();
		if ($prevCharge->count() === 0) {
			Billrun_Factory::log('Cannot find previous line to reverse charge from. Details: ' . print_R($this->event, 1), Zend_Log::WARN);
			return false;
		}

		return $prevCharge->current();
	}

	protected function getReverseChargedQuery() {
		$queryValues = Billrun_Factory::config()->getConfigValue('reversecharge.reverseChargeQuery.' . $this->event['type'], array());
		if (empty($queryValues)) {
			return false;
		}

		$query = array();
		foreach ($queryValues as $key => $value) {
			$val = null;
			if (in_array($key, array('or', 'and'))) {
				$key = '$' . $key;
			}
			if (!is_array($value)) {
				$val = $this->event[$value];
			} else if (isset($value['classMethod']) && method_exists($this, $value['classMethod'])) {
				$val = $this->{$value['classMethod']}();
			}
			if (!is_null($val)) {
				$query[$key] = $val;
			}
		}

		return $query;
	}

	protected function getReverseChargeQuery() {
		return array(
			array('reverse_charge' => array('$exists' => 0)),
			array('reverse_charge' => false),
		);
	}

}

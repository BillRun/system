<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract subscriber class
 *
 * @package  Billing
 * @since    5.3
 */
class Billrun_Balance_Postpaid extends Billrun_Balance {

	protected $connection_type = 'postpaid';

	protected function init() {
		
	}

	protected function load() {
		$ret = parent::load()->getRawData();
		if (empty($ret)) { // on postpaid we create the balance if not exists
			$ret = $this->getDefaultBalance($this->row);
		}
		return $ret;
	}

	/**
	 * Gets default balance for subscriber (when no balance was found).
	 * create new empty balance.
	 * 
	 * @param type $options subscriber db line
	 * @return array The default balance
	 */
	protected function getDefaultBalance($options) {
		$urtDate = date('Y-m-d h:i:s', $options['urt']->sec);
		$from = Billrun_Billingcycle::getBillrunStartTimeByDate($urtDate);
		$to = Billrun_Billingcycle::getBillrunEndTimeByDate($urtDate);
		$plan = Billrun_Factory::plan(array('name' => $options['plan'], 'time' => $options['urt']->sec, 'disableCache' => true));
		return $this->createBasicBalance($options['aid'], $options['sid'], $from, $to, $plan);
	}

	/**
	 * Create a new balance  for a subscriber  in a given billrun
	 * @param type $account_id the account ID  of the subscriber.
	 * @param type $subscriber_id the subscriber ID.
	 * @param type $from billrun start date
	 * @param type $to billrun end date
	 * @param Billrun_Plan $plan the subscriber plan.
	 * @return boolean true  if the creation was sucessful false otherwise.
	 */
	protected function createBasicBalance($aid, $sid, $from, $to, $plan) {
		$query = array(
			'sid' => $sid,
		);
		$update = array(
			'$setOnInsert' => $this->getEmptySubscriberEntry($from, $to, $aid, $sid, $plan),
		);
		$options = array(
			'upsert' => true,
			'new' => true,
		);
		Billrun_Factory::log()->log("Create empty balance, from: " . date("Y-m-d", $from) . " to: " . date("Y-m-d", $to) . ", if not exists for subscriber " . $sid, Zend_Log::DEBUG);
		$output = $this->collection()->findAndModify($query, $update, array(), $options, false);

		if (!is_array($output)) {
			Billrun_Factory::log('Error creating balance  , from: ' . date("Y-m-d", $from) . " to: " . date("Y-m-d", $to) . ', for subscriber ' . $sid . '. Output was: ' . print_r($output->getRawData(), true), Zend_Log::ALERT);
			return false;
		}
		Billrun_Factory::log('Added balance from: ' . date("Y-m-d", $from) . " to: " . date("Y-m-d", $to) . ', to subscriber ' . $sid, Zend_Log::INFO);
		return $output;
	}

	/**
	 * Get a new balance array to be placed in the DB.
	 * @param int $from
	 * @param int $to
	 * @param int $aid
	 * @param int $sid
	 * @param Billrun_Plan $current_plan
	 * @return array
	 */
	protected function getEmptySubscriberEntry($from, $to, $aid, $sid, $plan) {
		$planRef = $plan->createRef();
		$connectionType = $plan->get('connection_type');
		return array(
			'from' => new MongoDate($from),
			'to' => new MongoDate($to),
			'aid' => $aid,
			'sid' => $sid,
			'current_plan' => $planRef,
			'connection_type' => $connectionType,
			'balance' => array('cost' => 0),
			'tx' => new stdclass,
		);
	}

	/**
	 * method to build update query of the balance
	 * 
	 * @param array $pricingData pricing data array
	 * @param Mongodloid_Entity $row the input line
	 * @param int $volume The usage volume (seconds of call, count of SMS, bytes  of data)
	 * 
	 * @return array update query array (mongo style)
	 */
	public function buildBalanceUpdateQuery(&$pricingData, $row, $volume) {
		list($query, $update) = parent::buildBalanceUpdateQuery($pricingData, $row, $volume);
		$balance_totals_key = $this->getBalanceTotalsKey($pricingData);
		$currentUsage = $this->getCurrentUsage($balance_totals_key);
		$update['$inc']['balance.totals.' . $balance_totals_key . '.usagev'] = $volume;
		$update['$inc']['balance.totals.' . $balance_totals_key . '.cost'] = $pricingData[$this->pricingField];
		$update['$inc']['balance.totals.' . $balance_totals_key . '.count'] = 1;
		$update['$inc']['balance.cost'] = $pricingData[$this->pricingField];
		// update balance group (if exists); supported only on postpaid
		$this->buildBalanceGroupsUpdateQuery($update, $pricingData, $balance_totals_key);
		$pricingData['usagesb'] = floatval($currentUsage);
		return array($query, $update);
	}

	/**
	 * build (on) balance update query groups of usages
	 * 
	 * @param array $update update query
	 * @param array $pricingData pricing data
	 * @param string $balance_totals_key the balance key (usage type based)
	 * 
	 * @return void
	 */
	protected function buildBalanceGroupsUpdateQuery(&$update, &$pricingData, $balance_totals_key) {
		if (!isset($pricingData['arategroups'])) {
			return;
		}
		foreach ($pricingData['arategroups'] as &$arategroup) {
			$group = $arategroup['name'];
			$update['$inc']['balance.groups.' . $group . '.' . $balance_totals_key . '.usagev'] = $arategroup['usagev'];
			$update['$inc']['balance.groups.' . $group . '.' . $balance_totals_key . '.count'] = 1;
			$update['$set']['balance.groups.' . $group . '.' . $balance_totals_key . '.left'] = $arategroup['left'];
			$update['$set']['balance.groups.' . $group . '.' . $balance_totals_key . '.total'] = $arategroup['total'];
//				$update['$inc']['balance.groups.' . $group . '.' . $usage_type . '.cost'] = $pricingData[$this->pricingField];
			if (isset($this->get('balance')['groups'][$group][$balance_totals_key]['usagev'])) {
				$arategroup['usagesb'] = floatval($this->get('balance')['groups'][$group][$balance_totals_key]['usagev']);
			} else {
				$arategroup['usagesb'] = 0;
			}
		}
	}

	/**
	 * method to get balance totals key
	 * 
	 * @param array $row
	 * @param array $pricingData rate handle
	 * 
	 * @return string
	 */
	protected function getBalanceTotalsKey($pricingData) {
		if (isset($pricingData['in_plan']) || isset($pricingData['over_plan'])) {
			return $this->row['usaget'];
		}
		return 'out_plan_' . $this->row['usaget'];
	}

}

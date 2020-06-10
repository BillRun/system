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
		$ret = parent::load();
		if (empty($ret)) { // on postpaid we create the balance if not exists
			$ret = $this->getDefaultBalance();
		}
		return $ret;
	}
	
	/**
	 * Gets a query to get the correct balance of the subscriber.
	 * 
	 * @param type $subscriberId
	 * @param type $timeNow - The time now.
	 * @param type $chargingType
	 * @param type $usageType
	 * @return array
	 */
	protected function getBalanceLoadQuery(array $query = array()) {
		$query['aid'] = $this->row['aid'];
		$query['sid'] = $this->row['sid'];
		$query['from'] = array('$lte' => $this->row['urt']);
		$query['to'] = array('$gte' => $this->row['urt']);
		$query['priority'] = $this->getServiceIndex();
		
		if ($this->isExtendedBalance()) {
			$query['service_name'] = $this->row['service_name'];
		} else {
			$query['service_name'] = array(
				'$exists' => false,
			);
		}
		Billrun_Factory::dispatcher()->trigger('getBalanceLoadQuery', array(&$query, $this->row, $this));

		return $query;
	}

	/**
	 * Gets default balance for subscriber (when no balance was found).
	 * create new empty balance.
	 * 
	 * @param type $options subscriber db line
	 * @return array The default balance
	 */
	protected function getDefaultBalance() {
		$urt = $this->row['urt']->sec;
		if ($this->isExtendedBalance()) {
			$service_name = $this->row['service_name'];
			$service_id = $this->row['service_id'];
			$from = $start_period = $this->row['service_start_date'];
			$period = $this->row['balance_period'];
			$to = strtotime((string) $this->row['balance_period'], $from);
		} else if ($this->isAddonBalance()) {
			$service_id = $this->row['service_id'];
			$service_name = $this->row['service_name'];
		} else {
			$service_id = 0;
			$service_name = null;
		}
		if (empty($from) || empty($to)) {
			$urtDate = date('Y-m-d h:i:s', $urt);
			$from = Billrun_Billingcycle::getBillrunStartTimeByDate($urtDate);
			$start_period = "default";
			$to = Billrun_Billingcycle::getBillrunEndTimeByDate($urtDate);
			$period = "default";
		}
		$plan = Billrun_Factory::plan(array('name' => $this->row['plan'], 'time' => $urt, 'disableCache' => true));
		return $this->createBasicBalance($this->row['aid'], $this->row['sid'], $from, $to, $plan, $urt, $start_period, $period, $service_name, $service_id);
	}
	
	/**
	 * method to return service index if this balance based on service
	 * @return int service index if service based else return 0
	 */
	protected function getServiceIndex() {
		if (!isset($this->row['service_id'])) {
			return 0;
		}
		return $this->row['service_id'];
	}

	/**
	 * method to check if balance is aligned to extended period which is not aligned to the cycle
	 * @return boolean true if this is extended balance, else false
	 */
	protected function isExtendedBalance() {
		return isset($this->row['balance_period']) && $this->row['balance_period'] != "default" && isset($this->row['service_name']);
	}

	/**
	 * method to check if balance is an add-on balance
	 * @return boolean true if this is an add-on balance, else false
	 */
	protected function isAddonBalance() {
		return !empty($this->row['add_on']);
	}

	/**
	 * Create a new balance  for a subscriber  in a given billrun
	 * @param type $account_id the account ID  of the subscriber.
	 * @param type $subscriber_id the subscriber ID.
	 * @param type $from billrun start date
	 * @param type $to billrun end date
	 * @param Billrun_Plan $plan the subscriber plan.
	 * @param type $urt line time
	 * @return boolean true  if the creation was sucessful false otherwise.
	 */
	protected function createBasicBalance($aid, $sid, $from, $to, $plan, $urt, $start_period = "default", $period = "default", $service_name = null, $priority = 0) {
		$converted_start_period = is_numeric($start_period) ? new MongoDate($start_period) : $start_period;
		$query = array(
			'aid' => $aid,
			'sid' => $sid,
			'from' => array(
				'$lte' => new MongoDate($urt),
			),
			'to' => array(
				'$gte' => new MongoDate($urt),
			),
//			'start_period' => $start_period,
			'start_period' => $converted_start_period,
			'period' => $period,
//			'priority' => $priority,
		);
		if ($sid != 0) {
			$query['priority'] = $priority;
		}
		if (!is_null($service_name)) {
			$query['service_name'] = $service_name;
		}
		$update = array(
			'$setOnInsert' => $this->getEmptySubscriberEntry($from, $to, $aid, $sid, $plan, $converted_start_period, $period, $service_name, $priority),
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
	protected function getEmptySubscriberEntry($from, $to, $aid, $sid, $plan, $start_period = "default", $period = "default", $service_name = null, $priority = 0) {
		$planRef = $plan->createRef();
		$connectionType = $plan->get('connection_type');
		$planDescription = $plan->get('description');
		$ret = array(
			'from' => new MongoDate($from),
			'to' => new MongoDate($to),
			'aid' => $aid,
			'sid' => $sid,
			'current_plan' => $planRef,
			'connection_type' => $connectionType,
			'start_period' => $start_period,
			'period' => $period,
			'plan_description' => $planDescription,
//			'priority' => $priority,
			'balance' => array('cost' => 0),
			'tx' => new stdclass,
		);
		if ($sid != 0) {
			$ret['priority'] = $priority;
		}
		if (!is_null($service_name)) {
			$ret['service_name'] = $service_name;
		}
		return $ret;
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
		if ($this->get('sid') != 0 && !$this->isExtendedBalance() && !$this->isAddonBalance()) {
			$update['$inc']['balance.totals.' . $balance_totals_key . '.usagev'] = $volume;
			$update['$inc']['balance.totals.' . $balance_totals_key . '.cost'] = $pricingData[$this->pricingField];
			$update['$inc']['balance.totals.' . $balance_totals_key . '.count'] = 1;
			$update['$inc']['balance.cost'] = $pricingData[$this->pricingField];
			if (isset($pricingData['out_group'])) {
				$update['$inc']['balance.totals.' . $row['usaget'] . '.out_group' . '.usagev'] = $pricingData['out_group'];
			}
			if (isset($pricingData['over_group'])) {
				$update['$inc']['balance.totals.' . $row['usaget'] . '.over_group' . '.usagev'] = $pricingData['over_group'];
			}
		}
		// update balance group (if exists); supported only on postpaid
		$this->buildBalanceGroupsUpdateQuery($update, $pricingData);
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
	protected function buildBalanceGroupsUpdateQuery(&$update, &$pricingData) {
		if (!isset($pricingData['arategroups'])) {
			return;
		}
		foreach ($pricingData['arategroups'] as &$arategroup) {
			$group = $arategroup['name'];
			if (isset($arategroup['cost'])) {
				// $subscriberSpent = $subscriberBalance['balance']['groups'][$groupSelected]['cost'];
				$update['$inc']['balance.groups.' . $group . '.cost'] = $arategroup['cost'];
				$update['$inc']['balance.groups.' . $group . '.count'] = 1;
				$update['$set']['balance.groups.' . $group . '.left'] = $arategroup['left'];
				$update['$set']['balance.groups.' . $group . '.total'] = $arategroup['total'];
				if (isset($this->get('balance')['groups'][$group]['cost'])) {
					$arategroup['usagesb'] = floatval($this->get('balance')['groups'][$group]['cost']);
				} else {
					$arategroup['usagesb'] = 0;
				}
			} else {
				$update['$inc']['balance.groups.' . $group . '.usagev'] = $arategroup['usagev'];
				$update['$inc']['balance.groups.' . $group . '.count'] = 1;
				$update['$set']['balance.groups.' . $group . '.left'] = $arategroup['left'];
				$update['$set']['balance.groups.' . $group . '.total'] = $arategroup['total'];
//				$update['$inc']['balance.groups.' . $group . '.' . $usage_type . '.cost'] = $pricingData[$this->pricingField];
				if (isset($this->get('balance')['groups'][$group]['usagev'])) {
					$arategroup['usagesb'] = floatval($this->get('balance')['groups'][$group]['usagev']);
				} else {
					$arategroup['usagesb'] = 0;
				}
			}
			// $subscriberSpent = $subscriberBalance['balance']['groups'][$groupSelected]['cost'];
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
	public function getBalanceTotalsKey($pricingData) {
		if (isset($pricingData['in_plan']) || isset($pricingData['over_plan']) ||
			isset($pricingData['in_group']) || isset($pricingData['over_group'])) {
			return $this->row['usaget'];
		}
		return 'out_plan_' . $this->row['usaget'];
	}
	
	/**
	 * method to get the instance of the class (singleton)
	 * 
	 * @param type $params
	 * 
	 * @return Billrun_Balance
	 */
	public static function getInstance($params = null) {
		if (empty($params)) {
			$params = Yaf_Application::app()->getConfig();
		}
	
		return new Billrun_Balance_Postpaid($params);
	}

}

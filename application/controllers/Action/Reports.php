<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2017 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Reports action class
 *
 * @package  Action
 * @since 5.5
 * 
 */
class ReportsAction extends ApiAction {
	
	use Billrun_Traits_Api_UserPermissions;
	
	static $DESCRIPTION_LIMIT = 8;
	
	protected $request = null;
	protected $status = true;
	protected $desc = 'success';
	protected $response = array();
	
	public function execute() {
		$this->allowed();
		$this->request = $this->getRequest()->getRequest(); // supports GET / POST requests;
		$action = $this->request['action'];
		if (!method_exists($this, $action)) {
			return $this->setError('Reports controller - cannot find action: ' . $action);
		}
		
		$this->{$action}();
		return $this->response();
	}
	
	protected function getRevenue($fromCycle, $toCycle) {
		$this->response = array();
		
		$match = array(
			'billrun_key' => array(
				'$gte' => $fromCycle,
				'$lte' => $toCycle,
			),
			'type' => 'inv',
		);
		
		$group = array(
			'_id' => '$billrun_key',
			'due' => array('$sum' => '$due'),
		);
		
		$project = array(
			'billrun_key' => '$_id',
			'due' => '$due',
		);
		
		$sort = array(
			'billrun_key' => 1,
		);
		
		$bills = Billrun_Factory::db()->billsCollection()->aggregate(
			array('$match' => $match),
			array('$group' => $group),
			array('$project' => $project),
			array('$sort' => $sort)
		);
		
		$data = array();
		
		foreach ($bills as $bill) {
			$data[$bill['billrun_key']] = $bill['due'];
		}
		
		for ($cycle = $fromCycle; $cycle <= $toCycle; $cycle = Billrun_Billingcycle::getFollowingBillrunKey($cycle)) {
			$this->response[] = array(
				'billrun_key' => $cycle,
				'due' => isset($data[$cycle]) ? $data[$cycle] : 0,
			);
		}
	}


	public function totalRevenue() {
		list($fromCycle, $toCycle) = $this->getCyclesRange();
		$this->getRevenue($fromCycle, $toCycle);
	}
	
	protected function getDebt($fromCycle, $toCycle) {
		$this->response = array();
		for ($cycle = $fromCycle; $cycle <= $toCycle; $cycle = Billrun_Billingcycle::getFollowingBillrunKey($cycle)) {
			$startTime = Billrun_Billingcycle::getStartTime($cycle);
			
			$match = array(
				'urt' => array(
					'$lt' => new MongoDate($startTime),
				),
			);

			$group = array(
				'_id' => null,
				'due' => array('$sum' => '$due'),
			);

			$res = Billrun_Factory::db()->billsCollection()->aggregate(
				array('$match' => $match),
				array('$group' => $group)
			)->current();
			
			$this->response[] = array(
				'billrun_key' => $cycle,
				'due' => isset($res['due']) ? $res['due'] : 0,
			);
		}
	}
	
	public function outstandingDebt() {
		list($fromCycle, $toCycle) = $this->getCyclesRange();
		$this->getDebt($fromCycle, $toCycle);
	}
	
	public function totalNumOfCustomers() {
		list($fromCycle, $toCycle) = $this->getCyclesRange();
		$this->response = array();
		for ($cycle = $fromCycle; $cycle <= $toCycle; $cycle = Billrun_Billingcycle::getFollowingBillrunKey($cycle)) {
			$startTime = Billrun_Billingcycle::getStartTime($cycle);
			$endTime = Billrun_Billingcycle::getEndTime($cycle);
			$query = Billrun_Utils_Mongo::getOverlappingWithRange('from', 'to', $startTime, $endTime);
			$this->response[] = array(
				'billrun_key' => $cycle,
				'customers_num' => count(Billrun_Factory::db()->subscribersCollection()->distinct('sid', $query)),
			);
		}
	}
	
	public function customerStateDistribution() {
		$date = '-1 month';
		$startTime = Billrun_Billingcycle::getBillrunStartTimeByDate($date);
		$endTime = Billrun_Billingcycle::getBillrunEndTimeByDate($date);
		
		$churnQuery = array(
			'deactivation_date' => array(
				'$gte' => new MongoDate($startTime),
				'$lte' => new MongoDate($endTime),
			),
		);
		$churnSubscribers = Billrun_Factory::db()->subscribersCollection()->distinct('sid', $churnQuery);
		
		$newQuery = array(
			'creation_time' => array(
				'$gte' => new MongoDate($startTime),
				'$lte' => new MongoDate($endTime),
			),
			'sid' => array('$nin' => $churnSubscribers),
		);
		$newSubscribers = Billrun_Factory::db()->subscribersCollection()->distinct('sid', $newQuery);
		
		$existingQuery = Billrun_Utils_Mongo::getDateBoundQuery();
		$existingQuery['sid'] = array('$nin' => array_merge($churnSubscribers, $newSubscribers));
		$existingSubscribers = Billrun_Factory::db()->subscribersCollection()->distinct('sid', $existingQuery);
		
		$this->response = array(
			array('state' => 'existing', 'customers_num' => count($existingSubscribers)),
			array('state' => 'new', 'customers_num' => count($newSubscribers)),
			array('state' => 'churn', 'customers_num' => count($churnSubscribers)),
		);
	}
	
	public function revenueOverTime() {
		$toCycle = Billrun_Billingcycle::getLastConfirmedBillingCycle();
		$currentYear = date('Y', Billrun_Billingcycle::getStartTime($toCycle));
		$fromCycle = ($currentYear - 1) . '01';
		$this->getRevenue($fromCycle, $toCycle);
	}
	
	public function planByCustomers() {
		$current = $this->planByCustomersQuery();
		$lastMonth = $this->planByCustomersQuery(strtotime("-1 month"));
		foreach ($current as $plan => $amount) {
			$this->response[] = array(
				'plan' => $plan,
				'amount' => $amount,
				'prev_amount' => isset($lastMonth[$plan]) ? $lastMonth[$plan] : 0,
			);
		}
	}
	
	public function planByCustomersQuery($time = null) {
		$plans = array();

		$match = Billrun_Utils_Mongo::getDateBoundQuery($time);
		$match['type'] = 'subscriber';

		$group = array(
			'_id' => '$plan', 
			'count' => array('$sum' => 1)
		);
		
		$project = array(
			'_id' => 0,
			'plan' => '$_id',
			'amount' => '$count'
		);
		
		$sort = array(
			'amount' => -1
		);
		
		$revenues = Billrun_Factory::db()->subscribersCollection()->aggregate(
			array('$match' => $match),
			array('$group' => $group),
			array('$project' => $project),
			array('$sort' => $sort)
		);
		
		foreach ($revenues as $revenue) {
			$plans[$revenue['plan']] = $revenue['amount'];
		}
		return $plans;
	}
	
	public function revenueByPlan() {
		$this->response = array();
		$billrunKey = Billrun_Billingcycle::getLastConfirmedBillingCycle();
		$prevBillrunKey = Billrun_Billingcycle::getPreviousBillrunKey($billrunKey);
		
		$match = array(
			'billrun_key' => array('$in' => array($billrunKey, $prevBillrunKey)),
//			'type' => 'inv',
			'billed' => 1,
		);
		
		$unwind = '$subs';
		
		$group = array(
			'_id' => array(
				'plan' => '$subs.plan',
				'billrun_key' => '$billrun_key',
			),
			'amount' => array('$sum' => '$subs.totals.after_vat'),
		);
		
		$project = array(
			'plan' => '$_id.plan',
			'billrun_key' => '$_id.billrun_key',
			'amount' => '$amount',
		);
		
		$sort = array(
			'billrun_key' => -1,
			'amount' => -1,
		);
		
		$revenues = Billrun_Factory::db()->billrunCollection()->aggregate(
			array('$match' => $match),
			array('$unwind' => $unwind),
			array('$group' => $group),
			array('$project' => $project),
			array('$sort' => $sort)
		);
		
		$sortedRevenues = array();
		
		$othersAmount = 0;
		$othersPrevAmount = 0;
		
		foreach($revenues as $revenue) {
			if ($revenue['billrun_key'] === $billrunKey) {
				if (count($sortedRevenues) < self::$DESCRIPTION_LIMIT) {
					$sortedRevenues[$revenue['plan']] = array('amount' => $revenue['amount']);
				} else {
					$othersAmount += $revenue['amount'];
				}
			} else {
				if (isset($sortedRevenues[$revenue['plan']])) {
					$sortedRevenues[$revenue['plan']]['prev'] = $revenue['amount'];
				} else {
					$othersPrevAmount += $revenue['amount'];
				}
			}
		}
		
		$sortedRevenues['others'] = array(
			'amount' => $othersAmount,
			'prev' => $othersPrevAmount,
		);
		
		foreach ($sortedRevenues as $plan => $revenue) {
			$this->response[] = array(
				'plan' => $plan,
				'amount' => $revenue['amount'],
				'prev_amount' => isset($revenue['prev']) ? $revenue['prev'] : 0,
			);
		}
	}
	
	public function agingDebt() {
		$from = strtotime('12 months ago');
		$fromCycle = Billrun_Billingcycle::getBillrunKeyByTimestamp($from);
		$toCycle = Billrun_Billingcycle::getPreviousBillrunKey(Billrun_Billingcycle::getLastConfirmedBillingCycle());
		$this->response = array();
		for ($cycle = $fromCycle; $cycle <= $toCycle; $cycle = Billrun_Billingcycle::getFollowingBillrunKey($cycle)) {
			$startTime = Billrun_Billingcycle::getStartTime($cycle);
			$endTime = Billrun_Billingcycle::getEndTime($cycle);
			
			$match = array(
				'invoice_date' => array(
					'$lte' => new MongoDate($endTime),
					'$gte' => new MongoDate($startTime),
				),
			);

			$group = array(
				'_id' => null,
				'left_to_pay' => array('$sum' => '$left_to_pay'),
			);

			$res = Billrun_Factory::db()->billsCollection()->aggregate(
				array('$match' => $match),
				array('$group' => $group)
			)->current();
			
			$this->response[] = array(
				'billrun_key' => $cycle,
				'left_to_pay' => isset($res['left_to_pay']) ? $res['left_to_pay'] : 0,
			);
		}
	}
	
	public function debtOverTime() {
		$toCycle = Billrun_Billingcycle::getLastConfirmedBillingCycle();
		$currentYear = date('Y', Billrun_Billingcycle::getStartTime($toCycle));
		$fromCycle = ($currentYear - 1) . '01';
		$this->getDebt($fromCycle, $toCycle);
	}
	
	protected function getCyclesRange() {
		$from = strtotime('12 months ago');
		return array(Billrun_Billingcycle::getOldestBillrunKey($from), Billrun_Billingcycle::getLastConfirmedBillingCycle());
	}
	
	protected function response() {
		$this->getController()->setOutput(array(
			array(
				'status' => $this->status,
				'desc' => $this->desc,
				'details' => $this->response,
			)
		));
		return true;
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
	}

}
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
	
	protected $request = null;
	protected $status = true;
	protected $desc = 'success';
	protected $response = array();
	
	public function execute() {
		$this->request = $this->getRequest()->getRequest(); // supports GET / POST requests;
		$action = $this->request['action'];
		if (!method_exists($this, $action)) {
			return $this->setError('Reports controller - cannot find action: ' . $action);
		}
		
		$this->{$action}();
		return $this->response();
	}
	
	public function totalRevenue() {
		list($fromCycle, $toCycle) = $this->getCyclesRange();
		
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
		
		$bills = Billrun_Factory::db()->billsCollection()->aggregate(
			array('$match' => $match),
			array('$group' => $group),
			array('$project' => $project)
		);
		
		foreach ($bills as $bill) {
			$this->response[$bill['billrun_key']] = $bill['due'];
		}
	}
	
	public function outstandingDebt() {
		list($fromCycle, $toCycle) = $this->getCyclesRange();
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
			
			$this->response[$cycle] = isset($res['due']) ? $res['due'] : 0;
		}
	}
	
	public function totalNumOfCustomers() {
		list($fromCycle, $toCycle) = $this->getCyclesRange();
		for ($cycle = $fromCycle; $cycle <= $toCycle; $cycle = Billrun_Billingcycle::getFollowingBillrunKey($cycle)) {
			$startTime = Billrun_Billingcycle::getStartTime($cycle);
			$endTime = Billrun_Billingcycle::getEndTime($cycle);
			$query = Billrun_Utils_Mongo::getOverlappingWithRange('from', 'to', $startTime, $endTime);
			$this->response[$cycle] = Billrun_Factory::db()->subscribersCollection()->distinct('sid', $query);
		}
	}
	
	public function customerStateDistribution() {
		
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
				'details' => array($this->response),
			)
		));
		return true;
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
	}

}
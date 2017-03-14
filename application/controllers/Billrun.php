<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2017 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class manages billing cycle process.
 *
 * @package     Controllers
 * @subpackage  Action
 *
 */
class BillrunController extends ApiController {

	protected $billingCycleCol = null;

	/**
	 * 
	 * @var int
	 */
	protected $size;

	public function init() {
		$this->billingCycleCol = Billrun_Factory::db()->billing_cycleCollection();
		$this->size = Billrun_Factory::config()->getConfigValue('customer.aggregator.size', 100);
		parent::init();
	}

	/**
	 * Runs billing cycle by billrun key.
	 * 
	 */
	public function completeCycleAction() {
		$request = $this->getRequest();
		$billrunKey = $request->get('stamp');
		if (empty($billrunKey) || !Billrun_Util::isBillrunKey($billrunKey)) {
			throw new Exception('Need to pass correct billrun key');
		}
		$rerun = $request->get('rerun');
		$currentBillrunKey = Billrun_Billingcycle::getBillrunKeyByTimestamp();
		if ($billrunKey >= $currentBillrunKey) {
			throw new Exception("Can't run billing cycle on active or future cycles");
		}
		if (Billrun_Billingcycle::isCycleRunning($this->billingCycleCol, $billrunKey, $this->size)) {
			throw new Exception("Already Running");
		}
		if (Billrun_Billingcycle::isBillingCycleRerun($this->billingCycleCol, $billrunKey, $this->size) && $this->getCycleStatus($billrunKey) == 'finished') {
			if (is_null($rerun) || !$rerun) {
				throw new Exception("For rerun pass rerun value as true");
			}
			Billrun_Billingcycle::removeBeforeRerun($this->billingCycleCol, $billrunKey);
		}

		self::processCycle($billrunKey);
	}

	protected function render($tpl, array $parameters = array()) {
		return parent::render('index', $parameters);
	}

	/**
	 * Returning info regarding billing cycle.
	 * 
	 */
	public function cyclesAction() {
		$request = $this->getRequest();
		$params['from'] = $request->get('from');
		$params['to'] = $request->get('to');
		$params['billrun_key'] = $request->get('stamp');
		$billrunKeys = $this->getCyclesKeys($params);
		foreach ($billrunKeys as $billrunKey) {
			$setting['billrun_key'] = $billrunKey;
			$setting['start_date'] = date('Y-m-d H:i:s', Billrun_Billingcycle::getStartTime($billrunKey));
			$setting['end_date'] = date('Y-m-d H:i:s', Billrun_Billingcycle::getEndTime($billrunKey));
			$setting['cycle_status'] = $this->getCycleStatus($billrunKey);
			$settings[] = $setting;
		}

		$output = array(
			'status' => !empty($settings) ? 1 : 0,
			'desc' => !empty($settings) ? 'success' : 'error',
			'details' => empty($settings) ? array() : $settings,
		);
		$this->setOutput(array($output));
	}

	/**
	 * Returns billing cycle statistics by billrun key.
	 * 
	 */
	public function cycleAction() {
		$request = $this->getRequest();
		$billrunKey = $request->get('stamp');
		if (empty($billrunKey) || !Billrun_Util::isBillrunKey($billrunKey)) {
			throw new Exception('Need to pass stamp of the wanted cycle info');
		}
		$setting['start_date'] = date('Y-m-d H:i:s', Billrun_Billingcycle::getStartTime($billrunKey));
		$setting['end_date'] = date('Y-m-d H:i:s', Billrun_Billingcycle::getEndTime($billrunKey));
		$setting['cycle_status'] = $this->getCycleStatus($billrunKey);
		$setting['completion_percentage'] = Billrun_Billingcycle::getCycleCompletionPercentage($this->billingCycleCol, $billrunKey, $this->size);

		$output = array(
			'status' => !empty($setting) ? 1 : 0,
			'desc' => !empty($setting) ? 'success' : 'error',
			'details' => empty($setting) ? array() : array($setting),
		);
		$this->setOutput(array($output));
	}

	protected function processCycle($billrunKey) {
		$cmd = 'php ' . APPLICATION_PATH . '/public/index.php ' . Billrun_Util::getCmdEnvParams() . ' --cycle --type customer --stamp ' . $billrunKey;
		Billrun_Util::forkProcessCli($cmd);
	}

	protected function getCyclesKeys($params) {
		if (!empty($params['from']) && !empty($params['to'])) {
			return $this->getCyclesInRange($params['from'], $params['to']);
		}
		if (!empty($params['billrun_key'])) {
			return array($params['billrun_key']);
		}
		return $this->getPreviousCycles();
	}

	public function getCyclesInRange($from, $to) {
		$startTime = Billrun_Billingcycle::getBillrunStartTimeByDate($from);
		$endTime = Billrun_Billingcycle::getBillrunEndTimeByDate($to);
		$currentBillrunKey = Billrun_Billingcycle::getBillrunKeyByTimestamp($startTime);
		$lastBillrunKey = Billrun_Billingcycle::getBillrunKeyByTimestamp($endTime - 1);

		while ($currentBillrunKey <= $lastBillrunKey) {
			$billrunKeys[] = $currentBillrunKey;
			$currentBillrunKey = Billrun_Billingcycle::getFollowingBillrunKey($currentBillrunKey);
		}

		return $billrunKeys;
	}

	public function getPreviousCycles() {
		$billrunKeys = array();
		$currentStamp = Billrun_Billingcycle::getBillrunKeyByTimestamp();
		array_push($billrunKeys, $currentStamp);
		$rangeOfCycles = Billrun_Factory::config()->getConfigValue('cyclemanagement.previous_cycles');
		while ($rangeOfCycles) {
			array_push($billrunKeys, Billrun_Billingcycle::getBillrunKeyByTimestamp(strtotime("$rangeOfCycles months ago")));
			$rangeOfCycles--;
		}
		return $billrunKeys;
	}

	public function getCycleStatus($billrunKey) {
		$currentBillrunKey = Billrun_Billingcycle::getBillrunKeyByTimestamp();
		if ($billrunKey == $currentBillrunKey) {
			return 'current';
		}
		if ($billrunKey > $currentBillrunKey) {
			return 'future';
		}
		if ($billrunKey < $currentBillrunKey && !Billrun_Billingcycle::hasCycleEnded($this->billingCycleCol, $billrunKey, $this->size)) {
			return 'to_run';
		} else if (!Billrun_Billingcycle::isCycleConfirmed($billrunKey)) {
			return 'finished';
		}
		if (Billrun_Billingcycle::isCycleRunning($this->billingCycleCol, $billrunKey, $this->size)) {
			return 'running';
		}
		if (Billrun_Billingcycle::hasCycleEnded($this->billingCycleCol, $billrunKey, $this->size) && Billrun_Billingcycle::isCycleConfirmed($billrunKey)) {
			return 'confirmed';
		}

		return '';
	}

}

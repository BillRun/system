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
		$this->size = (int) Billrun_Factory::config()->getConfigValue('customer.aggregator.size', 100);
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
		if ($this->getCycleStatus($billrunKey) == 'finished') {
			if (is_null($rerun) || !$rerun) {
				throw new Exception("For rerun pass rerun value as true");
			}
			Billrun_Billingcycle::removeBeforeRerun($this->billingCycleCol, $billrunKey);
		}

		$success = self::processCycle($billrunKey);
		$output = array (
			'status' => $success ? 1 : 0,
			'desc' => $success ? 'success' : 'error',
			'details' => array(),
		);
		$this->setOutput(array($output));
	}
	
	
	/**
	 * Generating bills by invoice id's.
	 * 
	 */
	public function confirmCycleAction() {
		$request = $this->getRequest();
		$invoices = $request->get('invoices');
		$invoicesId = json_decode($invoices);
		if (!empty($invoices) && is_null($invoicesId)) {
			throw new Exception('Invoices parameter must be array of integers');
		}
		$billrunKey = $request->get('stamp');
		if (empty($billrunKey) || !Billrun_Util::isBillrunKey($billrunKey)) {
			return $this->setError("stamp is in incorrect format or missing ", $request);
		}
		if (Billrun_Billingcycle::hasCycleEnded($this->billingCycleCol, $billrunKey, $this->size) && (!Billrun_Billingcycle::isCycleConfirmed($billrunKey) || !empty($invoices))){
			if (is_null($invoices)) {
				$success = self::processConfirmCycle($billrunKey);
			} else {
				$success = self::processConfirmCycle($billrunKey, $invoicesId);
			}
		}
		$output = array (
			'status' => $success ? 1 : 0,
			'desc' => $success ? 'success' : 'error',
			'details' => array(),
		);
		$this->setOutput(array($output));
	}
	
	public function chargeStatusAction() {
		$setting['status'] = $this->isChargeAllowed();
		$setting['owed_amount'] = $this->getOwedAmount();

		$output = array(
			'status' => !empty($setting) ? 1 : 0,
			'desc' => !empty($setting) ? 'success' : 'error',
			'details' => empty($setting) ? array() : $setting,
		);
		$this->setOutput(array($output));
	}

	protected function render($tpl, array $parameters = array()) {
		return parent::render('index', $parameters);
	}
	
	/**
	 * Charge accounts.
	 * 
	 */
	public function chargeAccountAction() {
		$request = $this->getRequest();
		$aids = $request->get('aids');
		$aidsArray = json_decode($aids);
		if (!empty($aids) && is_null($aidsArray)) {
			throw new Exception('aids parameter must be array of integers');
		}
		if (is_null($aids)) {
			$success = self::processCharge();
		} else {
			$success = self::processCharge($aidsArray);
		}
		$output = array (
			'status' => $success ? 1 : 0,
			'desc' => $success ? 'success' : 'error',
			'details' => array(),
		);
		$this->setOutput(array($output));
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
			$setting['start_date'] = date(Billrun_Base::base_datetimeformat, Billrun_Billingcycle::getStartTime($billrunKey));
			$setting['end_date'] = date(Billrun_Base::base_datetimeformat, Billrun_Billingcycle::getEndTime($billrunKey));
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
		$setting['start_date'] = date(Billrun_Base::base_datetimeformat, Billrun_Billingcycle::getStartTime($billrunKey));
		$setting['end_date'] = date(Billrun_Base::base_datetimeformat, Billrun_Billingcycle::getEndTime($billrunKey));
		$setting['cycle_status'] = $this->getCycleStatus($billrunKey);
		$setting['completion_percentage'] = Billrun_Billingcycle::getCycleCompletionPercentage($this->billingCycleCol, $billrunKey, $this->size);
		$setting['generated_invoices'] = Billrun_Billingcycle::getNumberOfGeneratedInvoices($billrunKey);
		$setting['generated_bills'] = Billrun_Billingcycle::getNumberOfGeneratedBills($billrunKey);
		if (Billrun_Billingcycle::hasCycleEnded($this->billingCycleCol, $billrunKey, $this->size)) {
			$setting['confirmation_percentage'] = Billrun_Billingcycle::getCycleConfirmationPercentage($billrunKey);
		}
		$output = array(
			'status' => !empty($setting) ? 1 : 0,
			'desc' => !empty($setting) ? 'success' : 'error',
			'details' => empty($setting) ? array() : array($setting),
		);
		$this->setOutput(array($output));
	}

	protected function processCycle($billrunKey) {
		if (empty($billrunKey) || !Billrun_Util::isBillrunKey($billrunKey)) {
			throw new Exception('Need to pass correct billrun key');
		}
		$cmd = 'php ' . APPLICATION_PATH . '/public/index.php ' . Billrun_Util::getCmdEnvParams() . ' --cycle --type customer --stamp ' . $billrunKey;
		return Billrun_Util::forkProcessCli($cmd);
	}

	protected function getCyclesKeys($params) {
		if (!empty($params['from']) && !empty($params['to'])) {
			return $this->getCyclesInRange($params['from'], $params['to']);
		}
		if (!empty($params['billrun_key'])) {
			return array($params['billrun_key']);
		}
		$to = date('Y/m/d', time());
		$from = date('Y/m/d', strtotime('12 months ago'));		
		return $this->getCyclesInRange($from, $to);
	}

	public function getCyclesInRange($from, $to) {
		$limit = 0;
		$startTime = Billrun_Billingcycle::getBillrunStartTimeByDate($from);
		$endTime = Billrun_Billingcycle::getBillrunEndTimeByDate($to);
		$currentBillrunKey = Billrun_Billingcycle::getBillrunKeyByTimestamp($endTime - 1);
		$lastBillrunKey = Billrun_Billingcycle::getBillrunKeyByTimestamp($startTime);

		while ($currentBillrunKey >= $lastBillrunKey && $limit < 100) {
			$billrunKeys[] = $currentBillrunKey;
			$currentBillrunKey = Billrun_Billingcycle::getPreviousBillrunKey($currentBillrunKey);
			$limit++;
		}

		return $billrunKeys;
	}

	public function getCycleStatus($billrunKey) {
		$currentBillrunKey = Billrun_Billingcycle::getBillrunKeyByTimestamp();
		$cycleConfirmed = Billrun_Billingcycle::isCycleConfirmed($billrunKey);
		$cycleEnded = Billrun_Billingcycle::hasCycleEnded($this->billingCycleCol, $billrunKey, $this->size);
		$cycleRunning = Billrun_Billingcycle::isCycleRunning($this->billingCycleCol, $billrunKey, $this->size);
		
		if ($billrunKey == $currentBillrunKey) {
			return 'current';
		}
		if ($billrunKey > $currentBillrunKey) {
			return 'future';
		}
		if ($billrunKey < $currentBillrunKey && !$cycleEnded && !$cycleRunning) {
			return 'to_run';
		} else if (!$cycleConfirmed) {
			return 'finished';
		}
		if ($cycleRunning) {
			return 'running';
		}
		if ($cycleEnded && $cycleConfirmed) {
			return 'confirmed';
		}

		return '';
	}
	
	protected function processConfirmCycle($billrunKey, $invoicesId = array()) {
		if (empty($billrunKey) || !Billrun_Util::isBillrunKey($billrunKey)) {
			throw new Exception('Need to pass correct billrun key');
		}
		if (!empty($invoicesId)) {
			$invoicesArray = Billrun_util::verify_array($invoicesId, 'int');
			$invoicesId = json_encode($invoicesArray);			
		}
		$cmd = 'php ' . APPLICATION_PATH . '/public/index.php ' . Billrun_Util::getCmdEnvParams() . ' --generate --type billrunToBill --stamp ' . $billrunKey . ' invoices=' . $invoicesId;
		return Billrun_Util::forkProcessCli($cmd);
	}
	
	protected function processCharge($aids = array()) {
		if (!empty($aids)) {
			$aidsArray = Billrun_util::verify_array($aids, 'int');
			$aids = json_encode($aidsArray);			
		}
		$cmd = 'php ' . APPLICATION_PATH . '/public/index.php ' . Billrun_Util::getCmdEnvParams() . ' --charge ' . 'aids=' . $aids;
		return Billrun_Util::forkProcessCli($cmd);
	}
	
	protected function getOwedAmount() {
		$billsColl = Billrun_Factory::db()->billsCollection();
		$match = array(
			'$match' => array(
				'aid' => array('$exists' => true),
			),
		);
		
		$group = array(
			'$group' => array(
				'_id' => null,
				'amount' => array(
					'$sum' => '$due',
				)
			)
		);

		$result = $billsColl->aggregate($match, $group)->current();
		return $result['amount'];
	}
	
	protected function isChargeAllowed() {
		$operationsColl = Billrun_Factory::db()->operationsCollection();
		$query = array(
			'action' => array('$in' => array('confirm_cycle', 'charge_account')),
			'end_time' => array('$exists' => false),
		);
		
		$chargeAllowed = $operationsColl->query($query)->cursor()->current();
		if ($chargeAllowed->isEmpty()) {
			return true;
		}
		return false;
	}
}

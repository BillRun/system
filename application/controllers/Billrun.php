<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2017 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

require_once APPLICATION_PATH . '/application/controllers/Action/Charge.php';

/**
 * This class manages billing cycle process.
 *
 * @package     Controllers
 * @subpackage  Action
 *
 */
class BillrunController extends ApiController {
	
	use Billrun_Traits_Api_UserPermissions;
		
	/**
	 * 
	 * @var int
	 */
	protected $size;
	
	protected $permissionReadAction = array('cycles', 'chargestatus', 'cycle');

	public function init() {
		$this->size = (int) Billrun_Factory::config()->getConfigValue('customer.aggregator.size', 100);
		if (in_array($this->getRequest()->action, $this->permissionReadAction)) {
			$this->permissionLevel = Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
		}
		$this->allowed();
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
		$generatedPdf = $request->get('generate_pdf');
		$currentBillrunKey = Billrun_Billingcycle::getBillrunKeyByTimestamp();
		if ($billrunKey >= $currentBillrunKey) {
			throw new Exception("Can't run billing cycle on active or future cycles");
		}
		if (Billrun_Billingcycle::isCycleRunning($billrunKey, $this->size)) {
			throw new Exception("Already Running");
		}
		$cycleStatus = Billrun_Billingcycle::getCycleStatus($billrunKey);
		if ($cycleStatus == 'finished' || $cycleStatus == 'to_rerun') {
			if (is_null($rerun) || !$rerun) {
				throw new Exception("For rerun pass rerun value as true");
			}
			Billrun_Factory::log("Rerunning cycle " . $billrunKey, Zend_Log::DEBUG);
			Billrun_Billingcycle::removeBeforeRerun($billrunKey);
		}

		$success = self::processCycle($billrunKey, $generatedPdf);
		Billrun_Factory::log("Finished running cycle " . $billrunKey, Zend_Log::DEBUG);
		$output = array (
			'status' => $success ? 1 : 0,
			'desc' => $success ? 'success' : 'error',
			'details' => array(),
		);
		$this->setOutput(array($output));
	}
	
	/**
	 * Runs billing cycle by billrun key on specific account id's.
	 * 
	 */
	public function specificCycleAction() {
		$request = $this->getRequest();
		$billrunKey = $request->get('stamp');
		if (empty($billrunKey) || !Billrun_Util::isBillrunKey($billrunKey)) {
			throw new Exception('Need to pass correct billrun key');
		}
		$accountArray = json_decode($request->get('aids'));
		if (empty($accountArray)) {
			throw new Exception('Need to supply at least one account id');
		}
		$aids = array_diff(Billrun_Util::verify_array($accountArray, 'int'), array(0));
		if (empty($aids)) {
			throw new Exception("Illgal account id's");
		}
		$status = Billrun_Billingcycle::getCycleStatus($billrunKey);
		if (!in_array($status, array('to_run', 'finished', 'to_rerun'))) {
			throw new Exception("Can't Run");
		}
		$customerAggregatorOptions = array(
			'force_accounts' => $aids,
		);
		$options = array(
			'type' =>  'customer',
			'stamp' =>  $billrunKey,
			'size' =>  $this->size,
			'aggregator' => $customerAggregatorOptions
		);
			
		$aggregator = Billrun_Aggregator::getInstance($options);
		if(!$aggregator) {
			throw new Exception("Can't Run");
		}
		$aggregator->load();
		$aggregator->aggregate();
		$output = array (
			'status' => 1,
			'desc' => 'success',
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
		if (!empty($invoices)) {
			$invoicesId = explode(',', $invoices);
		}
		$billrunKey = $request->get('stamp');
		if (empty($billrunKey) || !Billrun_Util::isBillrunKey($billrunKey)) {
			return $this->setError("stamp is in incorrect format or missing ", $request);
		}
		if (Billrun_Billingcycle::hasCycleEnded($billrunKey, $this->size) && (empty(Billrun_Billingcycle::getConfirmedCycles(array($billrunKey))) || !empty($invoices))){
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
	
	/**
	 * Checks if can charge and display the total amount owed.
	 * 
	 */
	public function chargeStatusAction() {
		$setting['status'] = $this->isChargeAllowed();

		$output = array(
			'status' => !empty($setting) ? 1 : 0,
			'desc' => !empty($setting) ? 'success' : 'error',
			'details' => empty($setting) ? array() : array($setting),
		);
		$this->setOutput(array($output));
	}

	protected function render($tpl, array $parameters = null) {
		return parent::render('index', $parameters);
	}
	
	/**
	 * Charge accounts.
	 * 
	 */
	public function chargeAccountAction() {
		$request = $this->getRequest();
		$params = array();
		$mode = $request->get('mode');
		$params['date'] = $request->get('date');
		$params['aids'] = $request->get('aids');
		$params['invoices'] = $request->get('invoices');
		$params['billrun_key'] = $request->get('billrun_key');
		$params['pay_mode']= $request->get('pay_mode');
		$params['mode'] = $request->get('charge_mode');
		$params['min_invoice_date'] = $request->get('min_invoice_date');
		$params['exclude_accounts'] = $request->get('exclude_accounts');
		if (!$this->validateParams($params)) {
			throw new Exception("One or more of the parameters of the 'charge' command is not valid");
		}
		if ((!is_null($mode) && ($mode != 'pending')) || (is_null($mode))) {
			$mode = '';
		}
		
		if ($this->canSyncCharge($request)) {
			return $this->chargeSync($mode, $params, $request);
		}
		
		return $this->chargeAsync($mode, $params);
	}
	
	/**
	 * make an asynchronous charge request 
	 * 
	 * @param string $mode
	 * @param array $params
	 */
	protected function chargeAsync($mode, $params) {
		$success = self::processCharge($mode, $params);
		$output = array (
			'status' => $success ? 1 : 0,
			'desc' => $success ? 'success' : 'error',
			'details' => array(),
		);
		$this->setOutput(array($output));
	}
	
	/**
	 * make a synchronous charge request 
	 * 
	 * @param string $mode
	 * @param array $params
	 * @param Yaf_Request_Abstract $request
	 */
	protected function chargeSync($mode, $params, $request) {
		$aid = intval($params['aids']);
		$paymentData = json_decode($request->get('payment_data'), JSON_OBJECT_AS_ARRAY);
		if (!empty($paymentData)) {
			$params['payment_data'] = [
				$aid => $paymentData,
			];
		}
		$amount = $request->get('amount');
		if ($amount) { // allow charge without existing bill
			$params['bills'] = [
				[
					'aid' => $aid,
					'left_to_pay' => $amount > 0 ? $amount : 0,
					'left' => $amount < 0 ? abs($amount) : 0,
					'payment_method' => $request->get('payment_method', 'automatic'),
					'billrun_key' => $request->get('billrun_key', Billrun_Billingcycle::getBillrunKeyByTimestamp()),
				],
			];
		}
		$chargeAction = new ChargeAction();
		$response = $chargeAction->charge($params);
		$this->setSuccess($response);
	}
	
	/**
	 * checks if the charge can be done synchronously (1 payment request)
	 * 
	 * @param Yaf_Request_Abstract $request
	 * @return boolean
	 */
	protected function canSyncCharge($request) {
		$aids = Billrun_Util::verify_array($request->get('aids', []), 'int');
		return (count($aids) == 1 && $request->get('pay_mode', '') == 'one_payment');
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
		$params['newestFirst'] = $request->get('newestFirst');
		$params['timeStatus'] = $request->get('timeStatus');
		$billrunKeys = $this->getCyclesKeys($params);
		foreach ($billrunKeys as $billrunKey) {
			$setting['billrun_key'] = $billrunKey;
			$setting['start_date'] = date(Billrun_Base::base_datetimeformat, Billrun_Billingcycle::getStartTime($billrunKey));
			$setting['end_date'] = date(Billrun_Base::base_datetimeformat, Billrun_Billingcycle::getEndTime($billrunKey));	
			if (empty($params['timeStatus'])) {
				$setting['cycle_status'] = Billrun_Billingcycle::getCycleStatus($billrunKey);
			} else {
				$setting['cycle_time_status'] = Billrun_Billingcycle::getCycleTimeStatus($billrunKey);
			}	
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
		$setting['cycle_status'] = Billrun_Billingcycle::getCycleStatus($billrunKey);
		$setting['completion_percentage'] = Billrun_Billingcycle::getCycleCompletionPercentage($billrunKey, $this->size);
		$setting['generated_invoices'] = Billrun_Billingcycle::getNumberOfGeneratedInvoices($billrunKey);
		$setting['generated_bills'] = Billrun_Billingcycle::getNumberOfGeneratedBills($billrunKey);
		if (Billrun_Billingcycle::hasCycleEnded($billrunKey, $this->size)) {
			$setting['confirmation_percentage'] = Billrun_Billingcycle::getCycleConfirmationPercentage($billrunKey);
		}
		$setting['generate_pdf'] = Billrun_Factory::config()->getConfigValue('billrun.generate_pdf');
		$output = array(
			'status' => !empty($setting) ? 1 : 0,
			'desc' => !empty($setting) ? 'success' : 'error',
			'details' => empty($setting) ? array() : array($setting),
		);
		$this->setOutput(array($output));
	}

	protected function processCycle($billrunKey, $generatedPdf = true) {
		if (empty($billrunKey) || !Billrun_Util::isBillrunKey($billrunKey)) {
			throw new Exception('Need to pass correct billrun key');
		}
		//Prevent command line injection
		if ((!in_array($generatedPdf,['true', 'false'])) || !is_numeric($billrunKey)) {
			throw new Exception("One or more of the parameters of the 'cycle' command is not valid");
		}
		$cmd = 'php ' . APPLICATION_PATH . '/public/index.php ' . Billrun_Util::getCmdEnvParams() . ' --cycle --type customer --stamp ' . $billrunKey . ' generate_pdf=' . $generatedPdf;
		return Billrun_Util::forkProcessCli($cmd);
	}

	protected function getCyclesKeys($params) {
		$newestFirst = !isset($params['newestFirst']) ? TRUE : boolval($params['newestFirst']);
		if (!empty($params['from']) && !empty($params['to'])) {
			return $this->getCyclesInRange($params['from'], $params['to'], $newestFirst);
		}
		if (!empty($params['billrun_key'])) {
			return array($params['billrun_key']);
		}
		$to = date('Y/m/d', time());
		$from = date('Y/m/d', strtotime('24 months ago'));		
		return $this->getCyclesInRange($from, $to, $newestFirst);
	}

	public function getCyclesInRange($from, $to, $newestFirst = TRUE) {
		$limit = 0;
		$startTime = Billrun_Billingcycle::getBillrunStartTimeByDate($from);
		$endTime = Billrun_Billingcycle::getBillrunEndTimeByDate($to);
		$currentBillrunKey = Billrun_Billingcycle::getBillrunKeyByTimestamp($endTime - 1);
		$lastBillrunKey = Billrun_Billingcycle::getOldestBillrunKey($startTime);

		while ($currentBillrunKey >= $lastBillrunKey && $limit < 100) {
			$billrunKeys[] = $currentBillrunKey;
			$currentBillrunKey = Billrun_Billingcycle::getPreviousBillrunKey($currentBillrunKey);
			$limit++;
		}
		if (!$newestFirst) {
			$billrunKeys = array_reverse($billrunKeys);
		}
		return $billrunKeys;
	}

	protected function processConfirmCycle($billrunKey, $invoicesId = array()) {
		if (empty($billrunKey) || !Billrun_Util::isBillrunKey($billrunKey)) {
			throw new Exception('Need to pass correct billrun key');
		}
		if (!empty($invoicesId)) {
			$invoicesArray = array_diff(Billrun_util::verify_array($invoicesId, 'int'), array(0));
			if (empty($invoicesArray) || count($invoicesArray) !== count(array_filter($invoicesArray,'is_numeric'))) {
				throw new Exception("Illgal invoices");
			}
			$invoicesId = implode(',', $invoicesArray);			
		}
		if (!empty($invoicesId)) {
			$cmd = 'php ' . APPLICATION_PATH . '/public/index.php ' . Billrun_Util::getCmdEnvParams() . ' --generate --type billrunToBill --stamp ' . $billrunKey . ' invoices=' . $invoicesId;
		} else {
			$cmd = 'php ' . APPLICATION_PATH . '/public/index.php ' . Billrun_Util::getCmdEnvParams() . ' --generate --type billrunToBill --stamp ' . $billrunKey;
		}
		return Billrun_Util::forkProcessCli($cmd);
	}
	
	protected function processCharge($mode, $params = array()) {
		$paramsString = $this->buildCommandByParams($params);
		$cmd = 'php ' . APPLICATION_PATH . '/public/index.php ' . Billrun_Util::getCmdEnvParams() . ' --charge ' . $paramsString . ' ' . $mode;
		return Billrun_Util::forkProcessCli($cmd);
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
	
	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_ADMIN;
	}
	
	/**
	 * Resetting billing cycle by billrun key.
	 * 
	 */
	public function resetCycleAction() {
		$request = $this->getRequest();
		$billrunKey = $request->get('stamp');
		if (empty($billrunKey) || !Billrun_Util::isBillrunKey($billrunKey)) {
			throw new Exception('Need to pass correct billrun key');
		}
		$success = false;
		if (Billrun_Billingcycle::getCycleStatus($billrunKey) == 'finished') {
			Billrun_Factory::log("Starting reset cycle for " . $billrunKey, Zend_Log::DEBUG);
			Billrun_Billingcycle::removeBeforeRerun($billrunKey);
			Billrun_Factory::log("Finished reset cycle for " . $billrunKey, Zend_Log::DEBUG);
			$success = true;
		}

		$output = array (
			'status' => $success ? 1 : 0,
			'desc' => $success ? 'success' : 'error',
			'details' => array(),
		);
		$this->setOutput(array($output));
	}
	
	protected function buildCommandByParams($params) {
		$paramsString = '';
		foreach ($params as $key => $value) {
			if (is_null($value)) {
				continue;
			}
			
			$paramsString .= $key . '=' . $value . ' ';
		}
		
		return $paramsString;
	}
	
	public function validateParams($params) {
		foreach ($params as $name => $value) {
			switch ($name) {
				case 'date':
				case 'min_invoice_date':
					if (!is_null($value) && !strtotime(trim($value, '"'))) {
						return false;
					}
					break;
				case 'invoices':
				case 'aids':
				case 'exclude_accounts':
					$values_array = !is_null($value) ? Billrun_Util::verify_array($value, 'int') : [];
					if (!is_null($value) && empty($values_array)) {
						return false;
					}
					break;
				case 'billrun_key':
					if (!is_null($value) && !Billrun_Util::isBillrunKey(trim($value, '"'))) {
						return false;
					}
					break;
				case 'pay_mode':
				case 'mode':
					$array = $name === 'pay_mode' ? ['one_payment', 'multiple_payments'] : ['refund', 'charge‎'];	
					if (!is_null($value) && !in_array(trim($value, '"'), $array)) {
						return false;
					}
					break;
				default:
					return false;
			}
		}
		return true;
	}

}

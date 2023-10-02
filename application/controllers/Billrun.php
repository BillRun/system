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

	protected $config_model;

	public function init() {
		$this->size = (int) Billrun_Factory::config()->getConfigValue('customer.aggregator.size', 100);
		if (in_array($this->getRequest()->action, $this->permissionReadAction)) {
			$this->permissionLevel = Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
		}
		$this->initializeModel();
		$this->allowed();
		parent::init();
	}

	/**
	 * This method is for initializing controller's model.
	 */
	protected function initializeModel() {
		$this->config_model = new ConfigModel();
	}

	/**
	 * Runs billing cycle by billrun key.
	 * 
	 */
	public function completeCycleAction() {
		$request = $this->getRequest();
		$billrunKey = $request->get('stamp');
		$invoicingDay = !empty($request->get('invoicing_day')) ? ltrim($request->get('invoicing_day'), "0") : null;
		if (empty($billrunKey) || !Billrun_Util::isBillrunKey($billrunKey)) {
			throw new Exception('Need to pass correct billrun key');
		}
		if (empty($invoicingDay) && Billrun_Factory::config()->isMultiDayCycle()) {
			throw new Exception('Need to pass invoicing day when on multi day cycle mode.');
		}
		$rerun = $request->get('rerun');
		$generatedPdf = $request->get('generate_pdf');
		$currentBillrunKey = Billrun_Billingcycle::getBillrunKeyByTimestamp(null, $invoicingDay);
		if ($billrunKey >= $currentBillrunKey) {
			throw new Exception("Can't run billing cycle on active or future cycles");
		}
		if (Billrun_Billingcycle::isCycleRunning($billrunKey, $this->size, $invoicingDay)) {
			throw new Exception("Already Running");
		}
		$cycleStatus = Billrun_Billingcycle::getCycleStatus($billrunKey, null, $invoicingDay);
		if ($cycleStatus == 'finished' || $cycleStatus == 'to_rerun') {
			if (is_null($rerun) || !$rerun) {
				throw new Exception("For rerun pass rerun value as true");
			}
			Billrun_Factory::log("Rerunning cycle " . $billrunKey, Zend_Log::DEBUG);
			Billrun_Billingcycle::removeBeforeRerun($billrunKey, $invoicingDay);
		}

		$success = self::processCycle($billrunKey, $generatedPdf, $invoicingDay);
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
		$invoicingDay = !empty($request->get('invoicing_day')) ? ltrim($request->get('invoicing_day'), "0") : null;
		if (empty($billrunKey) || !Billrun_Util::isBillrunKey($billrunKey)) {
			return $this->setError("stamp is in incorrect format or missing ", $request);
		}
		if (Billrun_Factory::config()->isMultiDayCycle() && (empty($invoicingDay) || (!empty($invoicingDay) && !is_numeric($invoicingDay)))) {
			return $this->setError('Need to pass numeric invoicing day when on multi day cycle mode.', $request);
		}
		if(!Billrun_Billingcycle::hasCycleEnded($billrunKey, $this->size, $invoicingDay)){
			return $this->setError("Can't confirm invoices while the billing cycle run is ongoing", $request);
		}
		if (empty(Billrun_Billingcycle::getConfirmedCycles(array($billrunKey), $invoicingDay)) || !empty($invoices)) {
			if (is_null($invoices)) {
				$success = self::processConfirmCycle($billrunKey, [], [$invoicingDay]);
			} else {
				$success = self::processConfirmCycle($billrunKey, $invoicesId, $invoicingDay);
			}
		} else {
			return $this->setError("Cycle was confirmed already, or no invoices were found to confirm", $request);
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

	protected function render(string $tpl, array $parameters = null): string {
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
		return count($aids) == 1;
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
		$invoicing_day = $request->get('invoicing_day');
		if ($this->config_model->isMultiDayCycleMode()) {
			if (empty($invoicing_day)) {
				$params['invoicing_day'] = Billrun_Factory::config()->getConfigChargingDay();
				Billrun_Factory::log('No invoicing day was passed, the default one was taken', Zend_Log::DEBUG);
			} else {
				$params['invoicing_day'] = ltrim($invoicing_day, "0");
			}
		}
		$billrunKeys = $this->getCyclesKeys($params);
		foreach ($billrunKeys as $billrunKey) {
			$setting['billrun_key'] = $billrunKey;
			$setting['start_date'] = date(Billrun_Base::base_datetimeformat, Billrun_Billingcycle::getStartTime($billrunKey, $invoicing_day));
			$setting['end_date'] = date(Billrun_Base::base_datetimeformat, Billrun_Billingcycle::getEndTime($billrunKey, $invoicing_day));	
			if (empty($params['timeStatus'])) {
				$setting['cycle_status'] = Billrun_Billingcycle::getCycleStatus($billrunKey, $invoicing_day);
			} else {
				$setting['cycle_time_status'] = Billrun_Billingcycle::getCycleTimeStatus($billrunKey, $invoicing_day);
			}	
			if (!empty($invoicing_day)) {
				$setting['invoicing_day'] = $invoicing_day;
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
		$config = Billrun_Factory::config();
		$request = $this->getRequest();
		$billrunKey = $request->get('stamp');
		$invoicingDay = !empty($request->get('invoicing_day')) ? ltrim($request->get('invoicing_day'), "0") : null;
		if (empty($billrunKey) || !Billrun_Util::isBillrunKey($billrunKey)) {
			throw new Exception('Need to pass stamp of the wanted cycle info');
		}
		if (empty($invoicingDay) && $config->isMultiDayCycle()) {
			throw new Exception('Need to pass invoicing day when on multi day cycle mode.');
		}
		$setting['billrun_key'] = $billrunKey;
		$setting['start_date'] = date(Billrun_Base::base_datetimeformat, Billrun_Billingcycle::getStartTime($billrunKey, $invoicingDay));
		$setting['end_date'] = date(Billrun_Base::base_datetimeformat, Billrun_Billingcycle::getEndTime($billrunKey, $invoicingDay));
		$setting['cycle_status'] = Billrun_Billingcycle::getCycleStatus($billrunKey, null, $invoicingDay);
		$setting['completion_percentage'] = Billrun_Billingcycle::getCycleCompletionPercentage($billrunKey, $this->size, $invoicingDay);
		$setting['generated_invoices'] = Billrun_Billingcycle::getNumberOfGeneratedInvoices($billrunKey, $invoicingDay);
		$setting['generated_bills'] = Billrun_Billingcycle::getNumberOfGeneratedBills($billrunKey, $invoicingDay);
		if (Billrun_Billingcycle::hasCycleEnded($billrunKey, $this->size, $invoicingDay)) {
			$setting['confirmation_percentage'] = Billrun_Billingcycle::getCycleConfirmationPercentage($billrunKey, $invoicingDay);
		}
		$setting['generate_pdf'] = Billrun_Factory::config()->getConfigValue('billrun.generate_pdf');
		if (!empty($invoicingDay)) {
			$setting['invoicing_day'] = $invoicingDay;
		}
		$output = array(
			'status' => !empty($setting) ? 1 : 0,
			'desc' => !empty($setting) ? 'success' : 'error',
			'details' => empty($setting) ? array() : array($setting),
		);
		$this->setOutput(array($output));
	}

	protected function processCycle($billrunKey, $generatedPdf = true, $invoicing_day = null) {
		if (empty($billrunKey) || !Billrun_Util::isBillrunKey($billrunKey)) {
			throw new Exception('Need to pass correct billrun key');
		}
		//Prevent command line injection
		if ((!in_array($generatedPdf,['true', 'false'])) || !is_numeric($billrunKey)) {
			throw new Exception("One or more of the parameters of the 'cycle' command is not valid");
		}
		$cmd = 'php ' . APPLICATION_PATH . '/public/index.php ' . Billrun_Util::getCmdEnvParams() . ' --cycle --type customer --stamp ' . $billrunKey . ' generate_pdf=' . $generatedPdf . (!is_null($invoicing_day) ? ' invoicing_days=' . $invoicing_day : "");
		return Billrun_Util::forkProcessCli($cmd);
	}

	protected function getCyclesKeys($params) {
		$invoicing_day = !empty($params['invoicing_day']) ? $params['invoicing_day'] : null;
		$newestFirst = !isset($params['newestFirst']) ? TRUE : boolval($params['newestFirst']);
		if (!empty($params['from']) && !empty($params['to'])) {
			return $this->getCyclesInRange($params['from'], $params['to'], $newestFirst, $invoicing_day);
		}
		if (!empty($params['billrun_key'])) {
			return array($params['billrun_key']);
		}
		$to = date('Y/m/d', time());
		$from = date('Y/m/d', strtotime('24 months ago'));		
		return $this->getCyclesInRange($from, $to, $newestFirst, $invoicing_day);
	}

	public function getCyclesInRange($from, $to, $newestFirst = TRUE, $invoicing_day = null) {
		$limit = 0;
		$startTime = Billrun_Billingcycle::getBillrunStartTimeByDate($from, null, $invoicing_day);
		$endTime = Billrun_Billingcycle::getBillrunEndTimeByDate($to, null, $invoicing_day);
		$currentBillrunKey = Billrun_Billingcycle::getBillrunKeyByTimestamp($endTime - 1, $invoicing_day);
		$lastBillrunKey = Billrun_Billingcycle::getOldestBillrunKey($startTime, $invoicing_day);

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

	protected function processConfirmCycle($billrunKey, $invoicesId = array(), $invoicing_day = null) {
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
			$cmd = 'php ' . APPLICATION_PATH . '/public/index.php ' . Billrun_Util::getCmdEnvParams() . ' --generate --type billrunToBill --stamp ' . escapeshellarg($billrunKey) . ' invoices=' . escapeshellarg($invoicesId);
		} else {
			$cmd = 'php ' . APPLICATION_PATH . '/public/index.php ' . Billrun_Util::getCmdEnvParams() . ' --generate --type billrunToBill --stamp ' . escapeshellarg($billrunKey);
		}
		if (!empty($invoicing_day)) {
			$cmd .= ' invoicing_days=' . escapeshellarg($invoicing_day);
		}
		return Billrun_Util::forkProcessCli(escapeshellcmd($cmd));
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
		$invoicingDay = !empty($request->get('invoicing_day')) ? ltrim($request->get('invoicing_day'), "0") : null;
		if (empty($billrunKey) || !Billrun_Util::isBillrunKey($billrunKey)) {
			throw new Exception('Need to pass correct billrun key');
		}
		if (empty($invoicingDay) && Billrun_Factory::config()->isMultiDayCycle()) {
			throw new Exception('Need to pass invoicing day when on multi day cycle mode.');
		}
		$success = false;
		if (Billrun_Billingcycle::getCycleStatus($billrunKey, null, $invoicingDay) == 'finished') {
			Billrun_Factory::log("Starting reset cycle for " . $billrunKey, Zend_Log::DEBUG);
			Billrun_Billingcycle::removeBeforeRerun($billrunKey, $invoicingDay);
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

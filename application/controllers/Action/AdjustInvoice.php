<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Credit.php';

/**
 * Adjust invoice action class
 * Adds manual credit lines to an existing unconfirmed invoice:
 * validates and calculates the lines in memory, removes the account's
 * unconfirmed invoice of the requested billrun key, stores the lines with that
 * billrun key and schedules an immediate regeneration of the invoice.
 *
 * @package  Action
 */
class AdjustInvoiceAction extends CreditAction {

	/**
	 * @var int
	 */
	protected $aid;

	/**
	 * @var string
	 */
	protected $billrunKey;

	/**
	 * @var Mongodloid_Entity
	 */
	protected $account;

	public function execute() {
		$this->allowed();
		Billrun_Factory::log("Execute adjust invoice", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest();
		try {
			$this->validateRequest($request);
			$this->buildEvents($this->getCreditLines($request));
			$processor = $this->processInMemory();
			Billrun_Aggregator_Customer::removeBeforeAggregate($this->billrunKey, [$this->aid], true);
			if ($processor->storeWhenInMemory() === false) {
				throw new Exception('Failed to store credit lines. Invoice of billrun ' . $this->billrunKey . ' for account ' . $this->aid . ' was removed and must be regenerated');
			}
			$this->scheduleInvoiceRegeneration();
		} catch (Exception $ex) {
			return $this->setError($ex->getMessage(), $request);
		}

		$this->getController()->setOutput([[
			'status' => 1,
			'desc' => 'success',
			'input' => $request,
			'stamps' => array_column($this->events, 'stamp'),
		]]);
		return true;
	}

	protected function validateRequest($request) {
		if (!Billrun_Jobsmanager::getInstance()->isWorkerEnabled()) {
			throw new Exception('Workers are disabled');
		}
		if (empty($request['aid']) || !is_numeric($request['aid'])) {
			throw new Exception('Illegal aid');
		}
		if (empty($request['billrun_key']) || !Billrun_Util::isBillrunKey($request['billrun_key'])) {
			throw new Exception('Illegal billrun key');
		}
		$this->aid = (int) $request['aid'];
		$this->billrunKey = $request['billrun_key'];

		$this->account = Billrun_Factory::account()->loadAccountForQuery(['aid' => $this->aid]);
		if (empty($this->account) || empty($this->account['aid'])) {
			throw new Exception('Account ' . $this->aid . ' not found');
		}
		if (Billrun_Bill_Invoice::isInvoiceConfirmed($this->aid, $this->billrunKey)) {
			throw new Exception('Invoice of billrun ' . $this->billrunKey . ' is already confirmed for account ' . $this->aid);
		}
	}

	/**
	 * @param array $request
	 * @return array credit lines, each in the api/credit request format
	 */
	protected function getCreditLines($request) {
		$creditLines = $request['credit_lines'] ?? [];
		if (is_string($creditLines)) {
			$creditLines = json_decode($creditLines, JSON_OBJECT_AS_ARRAY);
		}
		if (empty($creditLines) || !is_array($creditLines)) {
			throw new Exception('Please supply at least one credit line');
		}
		return $creditLines;
	}

	/**
	 * Builds the credit events, forced to the requested billrun key
	 *
	 * @param array $creditLines
	 */
	protected function buildEvents($creditLines) {
		$cycleEndTime = Billrun_Billingcycle::getEndTimeByCustomer($this->billrunKey, $this->account);
		foreach ($creditLines as $creditLine) {
			$this->request = $creditLine;
			if ($this->hasInstallments()) {
				throw new Exception('Installments are not supported when adjusting an invoice');
			}
			$event = $this->setEventData();
			if ((int) $event['aid'] !== $this->aid) {
				throw new Exception('Credit line aid does not match the account aid');
			}
			if ($event['credit_time'] > $cycleEndTime) {
				throw new Exception('Credit time ' . date('Y-m-d H:i:s', $event['credit_time']) . ' is after the end of billrun ' . $this->billrunKey);
			}
			$this->events[] = $event;
		}
	}

	protected function setEventData() {
		$event = parent::setEventData();
		unset($event['stamp']);
		$event['billrun'] = $this->billrunKey;
		$event['stamp'] = Billrun_Util::generateArrayStamp($event);
		return $event;
	}

	/**
	 * Runs the calculators on the credit events without storing them (dry-run)
	 *
	 * @return Billrun_Processor_Credit
	 */
	protected function processInMemory() {
		$processor = Billrun_Processor::getInstance([
			'type' => 'Credit',
			'parser' => 'none',
			'in_memory' => true,
		]);
		foreach ($this->events as $event) {
			$processor->addDataRow($event);
		}
		if ($processor->process() === false) {
			throw new Exception('Processor error');
		}
		$uncalculatedStamps = array_keys($processor->getQueueData());
		if (!empty($uncalculatedStamps)) {
			throw new Exception('Failed to calculate credit line/s: ' . implode(',', $uncalculatedStamps));
		}
		return $processor;
	}

	protected function scheduleInvoiceRegeneration() {
		$jobSettings = [
			'aid' => $this->aid,
			'billrun_key' => $this->billrunKey,
		];
		if (Billrun_Factory::config()->isMultiDayCycle() && !empty($this->account['invoicing_day'])) {
			$jobSettings['invoicing_day'] = $this->account['invoicing_day'];
		}
		$jobsManager = Billrun_Jobsmanager::getInstance();
		if ($jobsManager->push('Cycle_Account', $jobSettings) === false) {
			throw new Exception('Failed to schedule invoice regeneration: ' . $jobsManager->getLastError() . '. Credit lines were stored but invoice of billrun ' . $this->billrunKey . ' for account ' . $this->aid . ' was removed and must be regenerated');
		}
		Billrun_Factory::log("Scheduled invoice regeneration of billrun " . $this->billrunKey . " for account " . $this->aid, Zend_Log::INFO);
	}

}

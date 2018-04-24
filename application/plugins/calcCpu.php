<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Calculator cpu plugin make the calculative operations in the cpu (before line inserted to the DB)
 *
 * @package  Application
 * @subpackage Plugins
 * @since    5.0
 */
class calcCpuPlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'calcCpu';

	/**
	 *
	 * @var int active child processes counter
	 */
	protected $childProcesses = 0;

	/**
	 * calculators queue container
	 * @var type 
	 */
	protected $queueCalculators = null;

	public function beforeProcessorStore($processor, $realtime = false) {
		Billrun_Factory::log('Plugin ' . $this->name . ' triggered', Zend_Log::INFO);
		$options = array(
			'autoload' => 0,
			'realtime' => $realtime,
		);

		$remove_duplicates = Billrun_Factory::config()->getConfigValue('calcCpu.remove_duplicates', true);
		if ($remove_duplicates) {
			$this->removeDuplicates($processor);
		}
		
		$data = &$processor->getData();
		if ($processor->skipQueueCalculators()) {
			return;
		}
		$this->queueCalculators = new Billrun_Helpers_QueueCalculators($options);
		if (!$this->queueCalculators->run($processor, $data)) {
			return false;
		}
		Billrun_Factory::log('Plugin calc cpu end', Zend_Log::INFO);
	}

	public function afterProcessorStore($processor) {
		Billrun_Factory::log('Plugin ' . $this->name . ' triggered after processor store', Zend_Log::INFO);
		if ($this->queueCalculators) {
			$this->queueCalculators->release();
		}	
	}

	protected function removeDuplicates(Billrun_Processor $processor) {
		Billrun_Factory::log('Plugin ' . $this->name . ' remove duplicates', Zend_Log::INFO);
		$lines_coll = Billrun_Factory::db()->linesCollection();
		$data = &$processor->getData();
		$stamps = array();
		foreach ($data['data'] as $key => $line) {
			$stamps[$line['stamp']] = $key;
		}
		if ($stamps) {
			$query = array(
				'stamp' => array(
					'$in' => array_keys($stamps),
				),
			);
			$existing_lines = $lines_coll->query($query)->cursor()->setReadPreference(Billrun_Factory::config()->getConfigValue('read_only_db_pref'));
			foreach ($existing_lines as $line) {
				$stamp = $line['stamp'];
				Billrun_Factory::log('Plugin ' . $this->name . ' skips duplicate line ' . $stamp, Zend_Log::ALERT);
				$processor->unsetRow($stamp);
				$processor->unsetQueueRow($stamp);
			}
		}
	}

	/**
	 * extend the customer aggregator to generate the invoice right after the aggregator finished. EXPERIMENTAL feature.
	 * 
	 * @param Billrun_Cycle_Account     $account_billrun the billrun data of the account
	 * 
	 * @return void
	 */
	public function afterAggregateAccount(Billrun_Cycle_Account $accountBillrun, $aggregatedResults, $aggragator) {
		if(Billrun_Factory::config()->getConfigValue('calcCpu.dont_auto_generate_invoice', FALSE)) { return; }
		
		$forkXmlGeneration = Billrun_Factory::config()->getConfigValue('calcCpu.forkXmlGeneration', 0);
		if ($forkXmlGeneration && function_exists("pcntl_fork")) {
			$forkXmlLimit = Billrun_Factory::config()->getConfigValue('calcCpu.forkXmlLimit', 100);
			if ($this->childProcesses > $forkXmlLimit) {
				Billrun_Factory::log('Plugin calc cpu afterAggregateAccount : Releasing Zombies...', Zend_Log::INFO);
				$this->releaseZombies($forkXmlLimit);
			}
			if ($this->childProcesses <= $forkXmlLimit) {
				if (-1 !== ($pid = pcntl_fork())) {
					if ($pid == 0) {
						Billrun_Util::resetForkProcess();
						Billrun_Factory::log('Plugin calc cpu afterAggregateAccount run it in async mode', Zend_Log::INFO);
						$this->invoiceExport($accountBillrun->getInvoice(), $aggragator);
						exit(0); // exit from child process after finish creating xml; continue on parent
					}
					$this->childProcesses++;
					Billrun_Factory::log('Plugin calc cpu afterAggregateAccount forked the xml generation. Continue to next account', Zend_Log::INFO);
					return;
				}
			}
		}
		Billrun_Factory::log('Plugin calc cpu afterAggregateAccount run it in sync mode', Zend_Log::INFO);
		$this->invoiceExport($accountBillrun->getInvoice(), $aggragator);
	}

	protected function invoiceExport($accountInvoice, $aggragator) {
		if (!$aggragator->getGeneratePdf()) {
			return;
		}
		$options = array(
			'type' => 'invoice_export',
			'stamp' => $accountInvoice->getBillrunKey(),
			'accounts' => array($accountInvoice->getAid()),
			'is_fake' => $aggragator->isFakeCycle(),
			'is_onetime' => $aggragator->isOneTime(),
		);
		
		if($aggragator->isFakeCycle()) {
			$options['temp_pdf'] = true;
		}
		
		$generator = Billrun_Generator::getInstance($options);
		$generator->setData(array(new Mongodloid_Entity($accountInvoice->getRawData())));
		$generator->generate($accountInvoice->getInvoicedLines());
	}

	protected function releaseZombies($waitNum) {
		if (function_exists('pcntl_wait')) {
			while ($waitNum-- && pcntl_wait($status, WNOHANG) > 0) {
				$this->childProcesses--;
			}
		}
	}
	
	/**
	 * 
	 * @param type $data
	 * @param type $options
	 * @todo do this with one query
	 * @todo if use is required, fix session_id to work with new conventions
	 */
	protected function reuseExistingFields(&$data, $options) {
		$sessionIdFields = Billrun_Factory::config()->getConfigValue('session_id_field', array());
		foreach ($data['data'] as &$line) {
			$line['granted_return_code'] = Billrun_Factory::config()->getConfigValue('realtime.granted_code.ok');
			if (!isset($sessionIdFields[$line['type']]) || (
				isset($line['record_type']) && in_array($line['record_type'], Billrun_Factory::config()->getConfigValue('calcCpu.reuse.ignoreRecordTypes', array()))
			)) {
				continue;
			}
			$customerCalc = $this->getCalculator('customer', $options, $line);
			$rateCalc = $this->getCalculator('rate', $options, $line);
			if (!$rateCalc) {
				continue;
			}
			$possibleNewFields = array_merge($customerCalc->getCustomerPossiblyUpdatedFields(), array($rateCalc->getRatingField()), Billrun_Factory::config()->getConfigValue('calcCpu.reuse.addedFields', array()));
			$query = array_intersect_key($line, array_flip($sessionIdFields[$line['type']]));
			if ($query) {
				$flipedArr = array_flip($possibleNewFields);
				$fieldsToIgnore = Billrun_Factory::config()->getConfigValue('calcCpu.reuse.ignoreFields', array());
				foreach ($fieldsToIgnore as $fieldToIgnore) {
					unset($flipedArr[$fieldToIgnore]);
				}
				$formerLine = Billrun_Factory::db()->linesCollection()->query($query)->cursor()->sort(array('urt' => -1))->limit(1)->current();
				if (!$formerLine->isEmpty()) {
					$addArr = array_intersect_key($formerLine->getRawData(), $flipedArr);
					$line = array_merge($addArr, $line);
				}
			}
		}
	}

}

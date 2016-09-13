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
	 * @var array rows that inserted a transaction to balances
	 */
	protected $tx_saved_rows = array();

	/**
	 *
	 * @var int active child processes counter
	 */
	protected $childProcesses = 0;

	/**
	 * calculators queue container
	 * @var type 
	 */
	protected $queue_calculators = array();

	/**
	 *
	 * @var Billrun_Calculator_Unify
	 */
	protected $unifyCalc;

	public function beforeProcessorStore($processor) {
		Billrun_Factory::log('Plugin ' . $this->name . ' triggered', Zend_Log::INFO);
		$options = array(
			'autoload' => false,
		);

		$remove_duplicates = Billrun_Factory::config()->getConfigValue('calcCpu.remove_duplicates', true);
		if ($remove_duplicates) {
			$this->removeDuplicates($processor);
		}
		$data = &$processor->getData();
		$this->queue_calculators = Billrun_Factory::config()->getConfigValue("queue.calculators");
		$calc_name_in_queue = array_merge(array(false), $this->queue_calculators);
		$last_calc = array_pop($calc_name_in_queue);
		$index = 0;
		foreach ($this->queue_calculators as $calc_name) {
			Billrun_Factory::log('Plugin calc cpu ' . $calc_name, Zend_Log::INFO);
			$calc_options = $this->getCalcOptions($calc_name);
			if ($this->isUnify($calc_name)) {
				$this->unifyCalc($processor, $data);
				continue;
			}
			$queue_data = $processor->getQueueData();
			$calc = Billrun_Calculator::getInstance(array_merge($options, $calc_options));
			$calc->prepareData($data['data']);
			foreach ($data['data'] as $key => &$line) {
				if (isset($queue_data[$line['stamp']]) && $queue_data[$line['stamp']]['calc_name'] == $calc_name_in_queue[$index]) {
					$entity = new Mongodloid_Entity($line);
					if ($calc->isLineLegitimate($entity)) {
						if ($calc->updateRow($entity) !== FALSE) {
							if ($this->isLastCalc($calc_name, $last_calc)) {
								$processor->unsetQueueRow($entity['stamp']);
							} else {
								$processor->setQueueRowStep($entity['stamp'], $calc_name);
								$processor->addAdvancedPropertiesToQueueRow($line);
							}
						}
						$this->calcPricingCase($entity, $calc_name);
					} else {
						if ($this->isLastCalc($calc_name, $last_calc)) {
							$processor->unsetQueueRow($entity['stamp']);
						} else {
							$processor->setQueueRowStep($entity['stamp'], $calc_name);
						}
					}
					$line = $entity->getRawData();
				}
			}
			$index++;
		}
		Billrun_Factory::log('Plugin calc cpu end', Zend_Log::INFO);
	}

	public function unifyCalc(Billrun_Processor $processor, &$data) {
		if (in_array('unify', $this->queue_calculators)) {
			$queue_data = $processor->getQueueData();
			Billrun_Factory::log('Plugin calc Cpu unifying ' . count($queue_data) . ' lines', Zend_Log::INFO);
			foreach ($data['data'] as $key => &$line) {
				$this->unifyCalc = Billrun_Calculator_Unify::getInstance(array('type' => 'unify', 'autoload' => false, 'line' => $line));
				$this->unifyCalc->prepareData($data['data']);
				if (isset($queue_data[$line['stamp']]) && $queue_data[$line['stamp']]['calc_name'] == 'pricing') {
					$entity = new Mongodloid_Entity($line);
					if ($this->unifyCalc->isLineLegitimate($entity)) {
						$this->unifyCalc->updateRow($entity);
					} else {
						//Billrun_Factory::log("Line $key isnt legitimate : ".print_r($line,1), Zend_Log::INFO);
						// if this is last calculator, remove from queue
						if ($this->queue_calculators[count($this->queue_calculators) - 1] == 'unify') {
							$processor->unsetQueueRow($entity['stamp']);
						} else {
							$processor->setQueueRowStep($entity['stamp'], 'unify');
						}
					}

					$line = $entity->getRawData();
				}
			}

			$this->unifyCalc->updateUnifiedLines();

			//remove lines that where succesfully unified / needed archive only.
			foreach (array_keys($this->unifyCalc->getArchiveLines()) as $stamp) {
				$processor->unsetQueueRow($stamp);
				$processor->unsetRow($stamp);
			}
			$this->unifyCalc->saveLinesToArchive();
			//Billrun_Factory::log(count($data['data']), Zend_Log::INFO);
		}
	}

	public function afterProcessorStore($processor) {
		Billrun_Factory::log('Plugin ' . $this->name . ' triggered after processor store', Zend_Log::INFO);
		foreach ($this->tx_saved_rows as $row) {
			Billrun_Balances_Util::removeTx($row);
		}
		if (isset($this->unifyCalc)) {
			$this->unifyCalc->releaseAllLines();
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

	public function calcPricingCase($entity, $calc_name) {
		if ($calc_name == 'pricing') {
			if (!empty($entity['tx_saved'])) {
				$this->tx_saved_rows[] = $entity;
				unset($entity['tx_saved']);
			}
		}
	}

	protected function isUnify($calc_name) {
		if ($calc_name == 'unify') {
			return true;
		}
		return false;
	}

	protected function isLastCalc($calc_name, $last_calc) {
		if ($calc_name == $last_calc) {
			return true;
		}
		return false;
	}

	protected function getCalcOptions($calc_name) {
		switch ($calc_name) {
			case 'rate':
				$calc_options = array('type' => 'rate_Usage');
				break;

			case 'customer':
				$customerAPISettings = Billrun_Factory::config()->getConfigValue('customer.calculator', array());
				$calc_options = array('type' => 'customer', 'customer' => $customerAPISettings);
				break;

			case 'pricing':
				$calc_options = array('type' => 'customerPricing');
				break;

			case 'unify':
				$calc_options = array('type' => 'unify');
				break;

			default :
				Billrun_Factory::log('calculator ' . $calc_name . ' is unknown', Zend_Log::ALERT);
				break;
		}

		return $calc_options;
	}

	/**
	 * extend the customer aggregator to generate the invoice right after the aggregator finished. EXPERIMENTAL feature.
	 * 
	 * @param int                 $accid account id
	 * @param account             $account account subscribers details
	 * @param Billrun_Billrun     $account_billrun the billrun data of the account
	 * @param array               $lines the lines that was aggregated
	 * @param Billrun_Aggregator  $aggregator the aggregator class that fired the event
	 * 
	 * @return void
	 */
	public function afterAggregateAccount($accid, $account, Billrun_Billrun $account_billrun, $lines, Billrun_Aggregator $aggregator) {
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
						$this->makeXml($account_billrun, $lines);
						exit(0); // exit from child process after finish creating xml; continue on parent
					}
					$this->childProcesses++;
					Billrun_Factory::log('Plugin calc cpu afterAggregateAccount forked the xml generation. Continue to next account', Zend_Log::INFO);
					return;
				}
			}
		}
		Billrun_Factory::log('Plugin calc cpu afterAggregateAccount run it in sync mode', Zend_Log::INFO);
		$this->makeXml($account_billrun, $lines);
	}

	protected function makeXml($account_billrun, $lines) {
		$options = array(
			'type' => 'xml',
			'stamp' => $account_billrun->getBillrunKey(),
		);

		$generator = Billrun_Generator::getInstance($options);
		$generator->createXmlInvoice($account_billrun->getRawData(), $lines);
	}

	protected function releaseZombies($waitNum) {
		if (function_exists('pcntl_wait')) {
			while ($waitNum-- && pcntl_wait($status, WNOHANG) > 0) {
				$this->childProcesses--;
			}
		}
	}

}

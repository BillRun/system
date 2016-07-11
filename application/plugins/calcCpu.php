<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Calculator cpu plugin make the calculative operations in the cpu (before line inserted to the DB)
 *
 * @package  Application
 * @subpackage Plugins
 * @since    0.9
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

	protected function rateCalc($processor, &$data, $options) {
		Billrun_Factory::log('Plugin calc cpu rate', Zend_Log::INFO);
		$queue_data = $processor->getQueueData();
		foreach ($data['data'] as &$line) {
			if (isset($queue_data[$line['stamp']]) && $queue_data[$line['stamp']]['calc_name'] == 'customer') {
				$rateCalc = $this->getCalculator('rate', $options, $line);
				if (!$rateCalc) {
					continue;
				}
				$entity = new Mongodloid_Entity($line);

				if (!$this->shouldUsagevBeZero($entity) && (!isset($entity['usagev']) || $entity['usagev'] === 0)) {
					$processor->unsetQueueRow($entity['stamp']);
				} else if ($rateCalc->isLineLegitimate($entity)) {
					if ($rateCalc->updateRow($entity) !== FALSE) {
						$processor->setQueueRowStep($entity['stamp'], 'rate');
					}
				} else {
					$processor->setQueueRowStep($entity['stamp'], 'rate');
				}
				$line = $entity->getRawData();
				$processor->addAdvancedPropertiesToQueueRow($line);
			}
		}
	}

	protected function shouldUsagevBeZero($entity) {
		return $entity['type'] === 'gy' &&
			$entity['request_type'] == intval(Billrun_Factory::config()->getConfigValue('realtimeevent.data.requestType.FINAL_REQUEST'));
	}

	protected function customerCalc(Billrun_Processor $processor, &$data, $options) {
		Billrun_Factory::log('Plugin calc cpu customer.', Zend_Log::INFO);
		$customerCalc = $this->getCalculator('customer', $options);
		$queue_data = $processor->getQueueData();
		if ($customerCalc->isBulk()) {
			$customerCalc->loadSubscribers($data['data']);
		}
		$remove_garbage = Billrun_Factory::config()->getConfigValue('calcCpu.remove_garbage', false);
		$garbage_counter = 0;
		foreach ($data['data'] as $key => &$line) {
			if ($remove_garbage) {
				if (($line['type'] == 'ggsn' && isset($line['usagev']) && $line['usagev'] === 0) || (in_array($line['type'], array('smsc', 'mmsc', 'smpp')) && isset($line['arate']) && $line['arate'] === false) || ($line['type'] == 'nsn' && isset($line['usaget']) && $line['usaget'] === 'sms')
				) {
					$garbage_counter++;
					$processor->unsetQueueRow($line['stamp']);
					unset($queue_data[$line['stamp']]);
					$processor->unsetRow($line['stamp']);
				}
			}
			if (isset($queue_data[$line['stamp']]) && $queue_data[$line['stamp']]['calc_name'] == false) {
				$entity = new Mongodloid_Entity($line);
				/* if (!isset($entity['usagev']) || $entity['usagev'] === 0) {
				  $processor->unsetQueueRow($entity['stamp']);
				  } else */ if ($customerCalc->isLineLegitimate($entity)) {
					if ($customerCalc->updateRow($entity) !== FALSE) {
						$processor->setQueueRowStep($entity['stamp'], 'customer');
						$processor->addAdvancedPropertiesToQueueRow($entity);
					}
				} else {
					$processor->setQueueRowStep($entity['stamp'], 'customer');
				}
				$line = $entity->getRawData();
			}
		}

		$data['header']['linesStats']['garbage'] = $garbage_counter;
	}

	protected function customerPricingCalc($processor, &$data, $options) {
		Billrun_Factory::log('Plugin calc cpu customer pricing', Zend_Log::INFO);
		$customerPricingCalc = Billrun_Calculator::getInstance(array_merge(array('type' => 'customerPricing',), $options));
		$queue_data = $processor->getQueueData();

		foreach ($data['data'] as &$line) {
			if (isset($queue_data[$line['stamp']]) && $queue_data[$line['stamp']]['calc_name'] == 'rate') {
				$entity = new Mongodloid_Entity($line);
				if ($customerPricingCalc->isLineLegitimate($entity)) {
					if ($customerPricingCalc->updateRow($entity) !== FALSE) {
						// if this is last calculator, remove from queue
						if ($this->queue_calculators[count($this->queue_calculators) - 1] == 'pricing') {
							$processor->unsetQueueRow($entity['stamp']);
						} else {
							$processor->setQueueRowStep($entity['stamp'], 'pricing');
						}
					}
					if (!empty($entity['tx_saved'])) {
						$this->tx_saved_rows[] = $entity;
						unset($entity['tx_saved']);
					}
				} else {
					// if this is last calculator, remove from queue
					if ($this->queue_calculators[count($this->queue_calculators) - 1] == 'pricing') {
						$processor->unsetQueueRow($entity['stamp']);
					} else {
						$processor->setQueueRowStep($entity['stamp'], 'pricing');
					}
				}
				$line = $entity->getRawData();
			}
		}
	}

	public function unifyCalc(Billrun_Processor $processor, &$data) {
		if (in_array('unify', $this->queue_calculators)) {
			$queue_data = $processor->getQueueData();
			Billrun_Factory::log('Plugin calc Cpu unifying ' . count($queue_data) . ' lines', Zend_Log::INFO);
			foreach ($data['data'] as $key => &$line) {
				$this->unifyCalc = $this->getCalculator('unify', array(), $line);
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

	public function beforeProcessorStore($processor, $realtime = false) {
		Billrun_Factory::log('Plugin calc cpu triggered before processor store', Zend_Log::INFO);
		$options = array(
			'autoload' => FALSE,
			'realtime' => $realtime,
		);

		$this->queue_calculators = Billrun_Factory::config()->getConfigValue("queue.calculators");
		$remove_duplicates = Billrun_Factory::config()->getConfigValue('calcCpu.remove_duplicates', true);
		if ($remove_duplicates) {
			$this->removeDuplicates($processor);
		}
		$data = &$processor->getData();
		if ($realtime) {
			$this->reuseExistingFields($data, $options);
		}
		$before = microtime(true);
		$this->customerCalc($processor, $data, $options);
		$after = microtime(true);
		Billrun_Factory::log('Customer calculator time: ' . ($after - $before) * 1000 . " ms", Zend_Log::DEBUG);
		$before = microtime(true);
		$this->rateCalc($processor, $data, $options);
		$after = microtime(true);
		Billrun_Factory::log('Rate calculator time: ' . ($after - $before) * 1000 . " ms", Zend_Log::DEBUG);
		$before = microtime(true);
		$this->customerPricingCalc($processor, $data, $options);
		$after = microtime(true);
		Billrun_Factory::log('CustomerPricing calculator time: ' . ($after - $before) * 1000 . " ms", Zend_Log::DEBUG);
		$before = microtime(true);
		$this->unifyCalc($processor, $data);
		$after = microtime(true);
		Billrun_Factory::log('Unify calculator time: ' . ($after - $before) * 1000 . " ms", Zend_Log::DEBUG);


		Billrun_Factory::log('Plugin calc cpu end', Zend_Log::INFO);
	}

	protected function getCalculator($type, $options, $line = array()) {
		switch ($type) {
			case 'customer':
				$customerAPISettings = Billrun_Factory::config()->getConfigValue('customer.calculator', array());
				$customerOptions = array(
					'type' => $type,
					'calculator' => $customerAPISettings,
				);
				return Billrun_Calculator::getInstance(array_merge_recursive($options, $customerOptions));
			case 'rate':
				$entity = new Mongodloid_Entity($line);
				return Billrun_Calculator_Rate::getRateCalculator($entity, $options);
			case 'unify':
				return Billrun_Calculator_Unify::getInstance(array('type' => 'unify', 'autoload' => false, 'line' => $line));
		}

		Billrun_Factory::log('Cannot find ' . $type . ' calculator for line: ' . $line, Zend_Log::ERR);
	}

	/**
	 * 
	 * @param type $data
	 * @param type $options
	 * @todo do this with one query
	 */
	protected function reuseExistingFields(&$data, $options) {
		$sessionIdFields = Billrun_Factory::config()->getConfigValue('session_id_field', array());
		foreach ($data['data'] as &$line) {
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

	public function afterProcessorStore($processor, $realtime = false) {
		Billrun_Factory::log('Plugin calc cpu triggered after processor store', Zend_Log::INFO);
		$customerPricingCalc = Billrun_Calculator::getInstance(array('type' => 'customerPricing', 'autoload' => false, 'realtime' => $realtime));
		foreach ($this->tx_saved_rows as $row) {
			$customerPricingCalc->removeBalanceTx($row);
		}
		if (isset($this->unifyCalc) && $this->unifyCalc) {
			$this->unifyCalc->releaseAllLines();
		}
	}

	protected function removeDuplicates(Billrun_Processor $processor) {
		Billrun_Factory::log('Plugin calc cpu remove duplicates', Zend_Log::INFO);
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
				Billrun_Factory::log('Plugin calc cpu skips duplicate line ' . $stamp, Zend_Log::ALERT);
				$processor->unsetRow($stamp);
				$processor->unsetQueueRow($stamp);
			}
		}
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

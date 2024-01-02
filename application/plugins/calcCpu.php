<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
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

	public function beforeProcessorStore($processor) {
		Billrun_Factory::log('Plugin calc cpu triggered before processor store', Zend_Log::INFO);
		$options = array(
			'autoload' => 0,
		);

		$remove_duplicates = Billrun_Factory::config()->getConfigValue('calcCpu.remove_duplicates', true);
		if ($remove_duplicates) {
			$this->removeDuplicates($processor);
		}

		$data = &$processor->getData();
		Billrun_Factory::log('Plugin calc cpu rate', Zend_Log::INFO);
		foreach ($data['data'] as &$line) {
			$entity = new Mongodloid_Entity($line);

			//Move external_pricing CDRs  tothe external pricing  stage  no need to run other  calcaltors on them.
			if($line['type'] === 'ild_external_pricing') {
				$processor->setQueueRowStep($entity['stamp'], 'pricing'); continue;
			}

			$rateCalc = Billrun_Calculator_Rate::getRateCalculator($entity, $options);
			if ($rateCalc->isLineLegitimate($entity)) {
				if ($rateCalc->updateRow($entity) !== FALSE) {
					$processor->setQueueRowStep($entity['stamp'], 'rate');
				}
			} else {
				$processor->setQueueRowStep($entity['stamp'], 'rate');
			}
			$line = $entity->getRawData();
			$processor->addAdvancedPropertiesToQueueRow($line);
		}

		Billrun_Factory::log('Plugin calc cpu customer.', Zend_Log::INFO);
		$customerAPISettings = Billrun_Factory::config()->getConfigValue('customer.calculator', array());
		$customerOptions = array(
			'type' => 'customer',
			'calculator' => $customerAPISettings,
		); 
		$customerCalc = Billrun_Calculator::getInstance(array_merge($options, $customerOptions));
		$queue_data = $processor->getQueueData();
		if ($customerCalc->isBulk()) {
			$customerCalc->loadSubscribers($data['data']);
		}
		$remove_garbage = Billrun_Factory::config()->getConfigValue('calcCpu.remove_garbage', false);
		$garbage_counter = 0;
		foreach ($data['data'] as $key => &$line) {
			if ($remove_garbage) {
				if (($line['type'] == 'ggsn' && isset($line['usagev']) && $line['usagev'] === 0) || ($line['type'] == 'nsn' && isset($line['usaget']) && $line['usaget'] === 'sms')
				) {
					$garbage_counter++;
					$processor->unsetQueueRow($line['stamp']);
					unset($queue_data[$line['stamp']]);
					unset($data['data'][$key]);
				}
			}
			if (isset($queue_data[$line['stamp']]) && $queue_data[$line['stamp']]['calc_name'] == 'rate') {
				$entity = new Mongodloid_Entity($line);
				if (!isset($entity['usagev']) || $entity['usagev'] === 0) {
					$processor->unsetQueueRow($entity['stamp']);
				} else if ($customerCalc->isLineLegitimate($entity)) {
					if ($customerCalc->updateRow($entity) !== FALSE) {
						$processor->setQueueRowStep($entity['stamp'], 'customer');
						$processor->addAdvancedPropertiesToQueueRow($entity);
					}
				} else {
					$processor->setQueueRowStep($entity['stamp'], 'customer');
				}
				$line = $entity->getRawData();
				if (!empty($line['prepaid'])) {
					$processor->unsetQueueRow($line['stamp']);
				}
			}
		}

		$data['header']['linesStats']['garbage'] = $garbage_counter;

		Billrun_Factory::log('Plugin calc cpu customer pricing', Zend_Log::INFO);
		$customerPricingCalc = Billrun_Calculator::getInstance(array('type' => 'customerPricing', 'autoload' => false));
		$queue_data = $processor->getQueueData();
		$queue_calculators = Billrun_Factory::config()->getConfigValue("queue.calculators");

		foreach ($data['data'] as &$line) {
			if (isset($queue_data[$line['stamp']]) && $queue_data[$line['stamp']]['calc_name'] == 'customer') {
				$entity = new Mongodloid_Entity($line);
				if ($customerPricingCalc->isLineLegitimate($entity)) {
					if ($customerPricingCalc->updateRow($entity) !== FALSE) {
						// if this is last calculator, remove from queue
						if ($queue_calculators[count($queue_calculators) - 1] == 'pricing') {
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
					if ($queue_calculators[count($queue_calculators) - 1] == 'pricing') {
						$processor->unsetQueueRow($entity['stamp']);
					} else {
						$processor->setQueueRowStep($entity['stamp'], 'pricing');
					}
				}
				$line = $entity->getRawData();
			}
		}
		// External Pricing calculator
		if (in_array('external_pricing', $queue_calculators)) {
			$queue_data = $processor->getQueueData();
			$extPricingOptions = array(
				'type' => 'ExternalPricing',
				'calculator' => Billrun_Factory::config()->getConfigValue('ild_external_pricing.calculator', array()),
			);
			$extPricingCalc = Billrun_Calculator::getInstance(array_merge($options, $extPricingOptions));
			Billrun_Factory::log('Plugin calc Cpu external pricing ' . count($queue_data) . ' lines', Zend_Log::INFO);
			foreach ($data['data'] as $key => &$line) {
				if (isset($queue_data[$line['stamp']]) && $queue_data[$line['stamp']]['calc_name'] == 'pricing') {
					$entity = new Mongodloid_Entity($line);
					if ($extPricingCalc->isLineLegitimate($entity)) {
						if ( ($entity = $extPricingCalc->updateRow($entity)) !== FALSE ) {
							if ( $entity['external_pricing_state'] !== Billrun_Calculator_ExternalPricing::STATE_WAITING ){
								$processor->setQueueRowStep($entity['stamp'], 'external_pricing');
							}
							$processor->addAdvancedPropertiesToQueueRow($entity);
						}
					} else {
						$processor->setQueueRowStep($entity['stamp'], 'external_pricing');
						$processor->addAdvancedPropertiesToQueueRow($entity);
					}
					if($entity) {
						$line = $entity->getRawData();
					}
				}
			}
		}

		if (Billrun_Factory::config()->getConfigValue('calcCpu.wholesale_calculators', true)) {
			$this->wholeSaleCalculators($data, $processor);
		}

		if (in_array('unify', $queue_calculators)) {
			$this->unifyCalc = Billrun_Calculator::getInstance(array('type' => 'unify', 'autoload' => false));
			$this->unifyCalc->init();
			$queue_data = $processor->getQueueData();
			$sucessfulyUnified = array();
			Billrun_Factory::log('Plugin calc Cpu unifying ' . count($queue_data) . ' lines', Zend_Log::INFO);

			foreach ($data['data'] as $key => &$line) {
				if (isset($queue_data[$line['stamp']]) && $queue_data[$line['stamp']]['calc_name'] == 'external_pricing') {
					$entity = new Mongodloid_Entity($line);
					if ($this->unifyCalc->isLineLegitimate($entity)) {
						if ($this->unifyCalc->updateRow($entity) !== FALSE) {
							//mark lines that were successfuly unified.						
							$sucessfulyUnified[$entity['stamp']] = $key;
						}
					} else {
						//Billrun_Factory::log("Line $key isnt legitimate : ".print_r($line,1), Zend_Log::INFO);
						// if this is last calculator, remove from queue
						if ($queue_calculators[count($queue_calculators) - 1] == 'unify') {
							$processor->unsetQueueRow($entity['stamp']);
						} else {
							$processor->setQueueRowStep($entity['stamp'], 'unify');
						}
					}

					$line = $entity->getRawData();
				}
			}

			//handle update errors unmark  lines that thier unified line failed to update.
			foreach ($this->unifyCalc->updateUnifiedLines() as $failedLine) {
				foreach ($failedLine['lines'] as $stamp) {
					unset($sucessfulyUnified[$stamp]);
				}
			}

			//remove lines that where succesfully unified.
			foreach ($sucessfulyUnified as $stamp => $dataKey) {
				$processor->unsetQueueRow($stamp);
				unset($data['data'][$dataKey]);
			}
			$this->unifyCalc->saveLinesToArchive();
			//Billrun_Factory::log(count($data['data']), Zend_Log::INFO);
		}

		Billrun_Factory::log('Plugin calc cpu end', Zend_Log::INFO);
	}

	/**
	 * calculate all wholesale calculators in CPU
	 * 
	 * @param array $data the lines to run the calculate for
	 */
	protected function wholeSaleCalculators(&$data, $processor) {
		Billrun_Factory::log('Plugin calc cpu wholesale calculators', Zend_Log::INFO);
		$queue_calculators = Billrun_Factory::config()->getConfigValue("queue.calculators");
		$customerCarrier = Billrun_Calculator::getInstance(array('type' => 'carrier', 'autoload' => false));
		$customerWholesaleNsn = Billrun_Calculator::getInstance(array('type' => 'Wholesale_Nsn', 'autoload' => false));
		$customerWholesalePricing = Billrun_Calculator::getInstance(array('type' => 'Wholesale_WholesalePricing', 'autoload' => false));
		$customerWholesaleNationalRoamingPricing = Billrun_Calculator::getInstance(array('type' => 'Wholesale_NationalRoamingPricing', 'autoload' => false));
		$queue_data = $processor->getQueueData();
		$previous_stage = $queue_calculators[array_search('wsc', $queue_calculators) - 1]; //TODO what if wsc is the first stage?
		foreach ($data['data'] as &$line) {
			if (isset($queue_data[$line['stamp']]) && $queue_data[$line['stamp']]['calc_name'] == $previous_stage) {
				$entity = new Mongodloid_Entity($line);
				if (in_array('wsc', $queue_calculators) && $customerCarrier->isLineLegitimate($entity)) {
					$customerCarrier->updateRow($entity);
				}
				$processor->setQueueRowStep($line['stamp'], 'wsc');

				if (in_array('pzone', $queue_calculators) && $customerWholesaleNsn->isLineLegitimate($entity)) {
					$customerWholesaleNsn->updateRow($entity);
				}

				$processor->setQueueRowStep($line['stamp'], 'pzone');

				if (in_array('pprice', $queue_calculators) && $customerWholesalePricing->isLineLegitimate($entity)) {
					$customerWholesalePricing->updateRow($entity);
				}

				$processor->setQueueRowStep($line['stamp'], 'pprice');

				if (in_array('price_nr', $queue_calculators) && $customerWholesaleNationalRoamingPricing->isLineLegitimate($entity)) {
					$customerWholesaleNationalRoamingPricing->updateRow($entity);
				}

				if ($queue_calculators[count($queue_calculators) - 1] == 'price_nr') {
					$processor->unsetQueueRow($line['stamp']);
				} else {
					$processor->setQueueRowStep($line['stamp'], 'price_nr');
				}
				$line = $entity->getRawData();
			}
		}
	}

	public function afterProcessorStore($processor) {
		Billrun_Factory::log('Plugin calc cpu triggered after processor store', Zend_Log::INFO);
		$customerPricingCalc = Billrun_Calculator::getInstance(array('type' => 'customerPricing', 'autoload' => false));
		foreach ($this->tx_saved_rows as $row) {
			$customerPricingCalc->removeBalanceTx($row);
		}
		if (isset($this->unifyCalc)) {
			$this->unifyCalc->releaseAllLines();
		}
	}

	protected function removeDuplicates($processor) {
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
			$existing_lines = $lines_coll->query($query)->cursor();
			$count_duplicate_lines = 0;
			foreach ($existing_lines as $line) {
				$count_duplicate_lines = $count_duplicate_lines + 1;
				$stamp = $line['stamp'];
				if ($count_duplicate_lines <= 3) {
					$print_stamps[] = $stamp;
				} 
				unset($data['data'][$stamps[$stamp]]);
				$processor->unsetQueueRow($stamp);
			}
			if ($count_duplicate_lines > 0){
				$print_stamps = implode(", ", $print_stamps);
				Billrun_Factory::log('Plugin calc cpu skips ' . $count_duplicate_lines . ' duplicate lines in file ' . $line['file'] . ', example lines: ' . '(' . $print_stamps . ')', Zend_Log::ALERT);
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
			'type' => 'golanxml',
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

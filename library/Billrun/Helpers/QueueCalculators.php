<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Helper class for queue calculators
 *
 * @package  Util
 * @since    5.3
 */
class Billrun_Helpers_QueueCalculators {
	
	protected static $queue_calculators = array();
	protected static $tx_saved_rows = array();

	public static function runQueueCalculators(Billrun_Processor $processor, $data, $realtime, $options = array()) {
		$unifyCalc = null;
		self::$queue_calculators = self::getQueueCalculators($realtime);
		$calc_name_in_queue = array_merge(array(false), self::$queue_calculators);
		$last_calc = array_pop($calc_name_in_queue);
		$index = 0;
		foreach (self::$queue_calculators as $calc_name) {
			Billrun_Factory::log('Plugin calc cpu ' . $calc_name, Zend_Log::INFO);
			$calc_options = self::getCalcOptions($calc_name);
			if (self::isUnify($calc_name)) {
				$unifyCalc = self::unifyCalc($processor, $data);
				continue;
			}
			$queue_data = $processor->getQueueData();
			$calc = Billrun_Calculator::getInstance(array_merge($options, $calc_options));
			$calc->prepareData($data['data']);
			foreach ($data['data'] as $key => &$line) {
				if (isset($queue_data[$line['stamp']]) && $queue_data[$line['stamp']]['calc_name'] == $calc_name_in_queue[$index]) {
					$line['realtime'] = $realtime;
					$entity = new Mongodloid_Entity($line);
					if ($calc->isLineLegitimate($entity)) {
						if ($calc->updateRow($entity) !== FALSE) {
							if (self::isLastCalc($calc_name, $last_calc)) {
								$processor->unsetQueueRow($entity['stamp']);
							} else {
								$processor->setQueueRowStep($entity['stamp'], $calc_name);
								$processor->addAdvancedPropertiesToQueueRow($line);
							}
						}
						self::calcPricingCase($entity, $calc_name);
					} else {
						if (self::isLastCalc($calc_name, $last_calc)) {
							$processor->unsetQueueRow($entity['stamp']);
						} else {
							$processor->setQueueRowStep($entity['stamp'], $calc_name);
						}
					}
					$line = $entity->getRawData();
				}

				if ($realtime && $processor->getQueueData()[$line['stamp']]['calc_name'] !== $calc_name) {
					$line['granted_return_code'] = Billrun_Factory::config()->getConfigValue('realtime.granted_code.failed_calculator.' . $calc_name, -999);
					$unifyCalc = self::unifyCalc($processor, $data);
					return array(false, $unifyCalc, self::$tx_saved_rows);
				}
			}
			$index++;
		}
		return array(true, $unifyCalc, self::$tx_saved_rows);
	}
	
	protected static function getQueueCalculators($realtime) {
		$queue_calcs = Billrun_Factory::config()->getConfigValue("queue.calculators", array());
		if ($realtime && !array_search('unify', $queue_calcs)) { // realtime must run a unify calculator
			$queue_calcs[] = 'unify';
		}
		return $queue_calcs;
	}
	
	protected static function getCalcOptions($calc_name) {
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
	
	protected static function isUnify($calc_name) {
		return ($calc_name == 'unify');
	}
	
	protected static function isLastCalc($calc_name, $last_calc) {
		return ($calc_name == $last_calc);
	}
	
	protected static function unifyCalc(Billrun_Processor $processor, &$data) {
		if (in_array('unify', self::$queue_calculators)) {
			$unifyCalc = null;
			$queue_data = $processor->getQueueData();
			Billrun_Factory::log('Plugin calc Cpu unifying ' . count($queue_data) . ' lines', Zend_Log::INFO);
			foreach ($data['data'] as $key => &$line) {
				$unifyCalc = Billrun_Calculator_Unify::getInstance(array('type' => 'unify', 'autoload' => false, 'line' => $line));
				$unifyCalc->prepareData($data['data']);
				if (isset($queue_data[$line['stamp']]) && $queue_data[$line['stamp']]['calc_name'] == 'pricing') {
					$entity = new Mongodloid_Entity($line);
					if ($unifyCalc->isLineLegitimate($entity)) {
						$unifyCalc->updateRow($entity);
					} else {
						//Billrun_Factory::log("Line $key isnt legitimate : ".print_r($line,1), Zend_Log::INFO);
						// if this is last calculator, remove from queue
						if (self::$queue_calculators[count(self::$queue_calculators) - 1] == 'unify') {
							$processor->unsetQueueRow($entity['stamp']);
						} else {
							$processor->setQueueRowStep($entity['stamp'], 'unify');
						}
					}

					$line = $entity->getRawData();
				}
			}

			$unifyCalc->updateUnifiedLines();

			//remove lines that where succesfully unified / needed archive only.
			foreach (array_keys($unifyCalc->getArchiveLines()) as $stamp) {
				$processor->unsetQueueRow($stamp);
				$processor->unsetRow($stamp);
			}
			$unifyCalc->saveLinesToArchive();
			//Billrun_Factory::log(count($data['data']), Zend_Log::INFO);
			return $unifyCalc;
		}
	}
	
	protected static function calcPricingCase($entity, $calc_name) {
		if ($calc_name == 'pricing') {
			if (!empty($entity['tx_saved'])) {
				self::$tx_saved_rows[] = $entity;
				unset($entity['tx_saved']);
			}
		}
	}

}

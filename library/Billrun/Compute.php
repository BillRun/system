<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2021 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing compute base class
 *
 * @package  compute
 */
abstract class Billrun_Compute extends Billrun_Base {

	public static function getInstance() {
		$args = func_get_args();
		$args = $args[0];
		$class = 'Billrun_Compute_' . ucfirst($args['type']);
		if (isset($args['recalculation_type'])) {
			$class .= '_' . ucfirst($args['recalculation_type']) . 'Recalculation';
		}
		if (!@class_exists($class, true)) {
			Billrun_Factory::log("Can't find class: " . $class, Zend_Log::EMERG);
			return false;
		}
		return new $class();
	}

	abstract public function compute();

	abstract public function write();

	abstract public function getComputedType();

	public static function run($options) {
		try {
			Billrun_Factory::log()->log("Loading Compute ", Zend_Log::INFO);
			$computed = self::getInstance($options);
			if (!$computed) {
				Billrun_Factory::log("Compute cannot be loaded", Zend_Log::INFO);
			} else {
				Billrun_Factory::log()->log("Compute " . $computed->getComputedType() . " loaded", Zend_Log::INFO);
				Billrun_Factory::log()->log("Starting to compute " . $computed->getComputedType() . ". This action can take a while...", Zend_Log::INFO);
				$computed->compute();
				Billrun_Factory::log()->log("Writing compute " . $computed->getComputedType() . " data.", Zend_Log::INFO);
				$computed->write();
				Billrun_Factory::log()->log("Compute " . $computed->getComputedType() . " finished.", Zend_Log::INFO);
			}
		} catch (Throwable $th) {
			Billrun_Factory::log()->log($th->getMessage(), Zend_Log::ALERT);
		} catch (Exception $ex) {
			Billrun_Factory::log()->log($ex->getMessage(), Zend_Log::ALERT);
		}
	}

}

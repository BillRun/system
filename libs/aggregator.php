<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing interface aggregator class
 *
 * @package  calculator
 * @since    1.0
 */
abstract class aggregator extends base {

	/**
	 * execute aggregate
	 */
	abstract public function aggregate();

	/**
	 * load the data to aggregate
	 */
	abstract public function load();

	abstract protected function updateBillingLine($subscriber_id, $item);

	abstract protected function updateBillrun($billrun, $row);

	protected function loadSubscriber($phone_number, $time) {
		return new stdClass();
	}

	static public function getInstance() {
		$args = func_get_args();
		if (!is_array($args)) {
			$type = $args['type'];
			$args = array();
		} else {
			$type = $args[0]['type'];
			unset($args[0]['type']);
			$args = $args[0];
		}

		$file_path = __DIR__ . DIRECTORY_SEPARATOR . 'aggregator' . DIRECTORY_SEPARATOR . $type . '.php';

		if (!file_exists($file_path)) {
			// @todo raise an error
			return false;
		}

		require_once $file_path;
		$class = 'aggregator_' . $type;

		if (!class_exists($class)) {
			// @todo raise an error
			return false;
		}

		return new $class($args);
	}

	abstract protected function save($data);
}
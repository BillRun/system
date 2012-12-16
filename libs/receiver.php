<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract receiver class
 *
 * @package  Billing
 * @since    1.0
 */
abstract class receiver extends base {

	/**
	 * general function to receive
	 * 
	 * @return mixed
	 */
	abstract public function receive();

	static public function getInstance() {
		$args = func_get_args();
		$type = $args[0];
		unset($args[0]);

		$file_path = __DIR__ . DIRECTORY_SEPARATOR . 'receiver' . DIRECTORY_SEPARATOR . $type . '.php';

		if (!file_exists($file_path)) {
			// @todo raise an error
			return false;
		}

		require_once $file_path;
		$class = 'receiver_' . $type;

		if (!class_exists($class)) {
			// @todo raise an error
			return false;
		}

		return new $class($args);
	}

}

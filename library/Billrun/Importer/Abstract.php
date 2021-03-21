<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Importer test class
 *
 * @package  Billrun
 * @since    4.0
 */
abstract class Billrun_Importer_Abstract implements Billrun_Importer {

	protected $path = null;

	public function __construct($options) {
		if (isset($options['path'])) {
			$this->setPath($options['path']);
		}
	}

	public function setPath($path) {
		Billrun_Factory::log("Importer path: " . $path, Zend_Log::INFO);
		$this->path = $path;
	}

	public function getPath() {
		return $this->path;
	}

}

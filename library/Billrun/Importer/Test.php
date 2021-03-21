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
class Billrun_Importer_Test extends Billrun_Importer_Abstract {

	public function import() {
		Billrun_Factory::log("This is test importer");
		Billrun_Factory::log("The path to import is " . $this->path);
	}

}

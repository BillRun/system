<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Import action controller class
 *
 * @package  Action
 * @since    4.0
 */
class ImportAction extends ApiAction {

	/**
	 * method to execute the import
	 * it's called automatically by the cli main controller
	 * @return boolean true if successfull.
	 */
	public function execute() {
		$possibleOptions = array(
			'type' => false,
			'path' => false,
		);

		if (($options = $this->getController()->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}
		$import = Billrun_Factory::importer($options);
		$import->import();
	}

}

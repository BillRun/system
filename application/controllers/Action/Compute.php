<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2021 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Compute action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 */
class ComputeAction extends Action_Base {

	/**
	 * method to execute the compute suggestion process
	 * it's called automatically by the cli main controller
	 */
	public function execute() {

		$possibleOptions = array('type' => false);

		if (($options = $this->getController()->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}
		$extraParams = $this->getController()->getParameters();
		if (!empty($extraParams)) {
			$options = array_merge($extraParams, $options);
		}
		Billrun_Compute::run($options);
	}

}

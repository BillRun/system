<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Cron action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       5.8
 */
class CronAction extends Action_Base {
	
	/**
	 * method to execute the cron controller
	 * it's called automatically by the cli main controller
	 */
	public function execute() {
		$possibleOptions = array(
			'type' => false,
		);

		if (($options = $this->_controller->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}

		$this->forward('Cron', $options['type']);
	}
}

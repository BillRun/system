<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Api abstract action class
 *
 * @package  Action
 * @since    0.8
 */
abstract class ApiAction extends Action_Base {

	function setError($error_message, $input = null) {
		Billrun_Factory::log()->log("Sending Error : {$error_message}", Zend_Log::NOTICE);
		$output = array(
			'status' => 0,
			'desc' => $error_message,
		);
		if (!is_null($input)) {
			$output['input'] = $input;
		}
		$this->getController()->setOutput(array($output));
		return;
	}

}

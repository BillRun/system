<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Helper class to manage the API actions.
 *
 * @author Tom Feigin
 */
abstract class Billrun_ActionManagers_APIAction {
	
	use Billrun_ActionManagers_ErrorReporter;

	protected function __construct($params) {
		if (isset($params['error'])) {
			$this->error = $params['error'];
		}
		$this->errors = Billrun_Factory::config()->getConfigValue('errors', array());
	}
}

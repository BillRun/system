<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This Trait is used by controllers that enable login and logout logic
 */
trait Billrun_Traits_AuthenticatingController {
	/**
	 * Init the session expiration
	 * @param int $defaultTimeout - Default timeout in seconds, one hour by default.
	 */
	protected function initSession($defaultTimeout=3600) {
		$session_timeout = Billrun_Factory::config()->getConfigValue('admin.session.timeout', $defaultTimeout);
		ini_set('session.gc_maxlifetime', $session_timeout);
		
		/* Set expiration time to one hour */
		session_set_cookie_params($session_timeout);
	}
}

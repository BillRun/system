<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This Trait is used for API modules that handle additional input.
 *
 */
trait Billrun_Traits_Api_PageRedirect {

	/**
	 * Force redirecting to an input url.
	 * @param string $uri - URI to direct to.
	 */
	protected function forceRedirect($uri) {
		if (empty($uri)) {
			$uri = '/';
		}
		header('Location: ' . $uri);
		exit();
	}

}

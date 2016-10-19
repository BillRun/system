<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Interface for API actions
 *
 * @package  Action Managers
 * @since    5.1
 */
interface Billrun_ActionManagers_IAPIAction {
	/**
	 * Parse a request to build the action logic.
	 * 
	 * @param request $request The received request in the API.
	 * @return true if valid.
	 */
	public function parse($request);

	/**
	 * Execute the action logic.
	 * 
	 * @return true if sucessfull.
	 */
	public function execute();
}

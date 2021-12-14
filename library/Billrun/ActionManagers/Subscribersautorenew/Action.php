<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a prototype for a subscriber action.
 *
 */
abstract class Billrun_ActionManagers_Subscribersautorenew_Action {

	use Billrun_ActionManagers_ErrorReporter;

	protected $collection = null;

	/**
	 * Create an instance of the SubscibersAction type.
	 */
	public function __construct($params) {
		$this->baseCode = 1300;
		$this->collection = Billrun_Factory::db()->subscribersCollection();
	}

	protected function normalizeInterval($interval) {
		$normalized = strtolower($interval);

		$intervals = Billrun_Factory::config()->getConfigValue('autorenew.interval');

		if (!in_array($normalized, $intervals)) {
			return false;
		}

		return $normalized;
	}

	/**
	 * Parse a request to build the action logic.
	 * 
	 * @param request $request The received request in the API.
	 * @return true if valid.
	 */
	public abstract function parse($request);

	/**
	 * Execute the action logic.
	 * 
	 * @return true if sucessfull.
	 */
	public abstract function execute();

	/**
	 * Get the array of fields to be set in the query record from the user input.
	 * @return array - Array of fields to set.
	 */
	protected function getQueryFields() {
		return Billrun_Factory::config()->getConfigValue('subscribers_auto_renew.query_fields');
	}

}

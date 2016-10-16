<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a prototype for a services action.
 *
 */
abstract class Billrun_ActionManagers_Services_Action implements Billrun_ActionManagers_IAPIAction {

	use Billrun_ActionManagers_ErrorReporter;
	
	protected $collection = null;

	/**
	 * Create an instance of the ServiceAction type.
	 */
	public function __construct($params = array()) {
		$this->collection = Billrun_Factory::db()->servicesCollection();
		$this->baseCode = 1500;
	}

	/**
	* Get the array of fields to be set in the query record from the user input.
	* @return array - Array of fields to set.
	*/
	protected function getQueryFields() {
		return Billrun_Factory::config()->getConfigValue('services.fields');
	}	
}

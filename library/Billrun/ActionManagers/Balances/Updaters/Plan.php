<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Holds the logic for updating balances using plans.
 *
 * @author tom
 */
class Billrun_ActionManagers_Balances_Updaters_Plan {
	
	protected $plans = null;
	
	/**
	 * Create a new instance of the plans updater.
	 */
	public function __construct() {
		 $this->$plans = Billrun_Factory::db()->plansCollection();
	}

	public function update($recordToSet) {
		// TODO: Use the plans DB/API proxy.
		
	}
}

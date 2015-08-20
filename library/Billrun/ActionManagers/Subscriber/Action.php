<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a prototype for a subscriber action.
 *
 * @author tom
 */
abstract class Billrun_ActionManagers_Subscriber_Action {

	protected $collection = null;
	
	/**
	 * Create an instance of the SubscibersAction type.
	 */
	public function __construct() {
		$this->collection = Billrun_Factory::db()->subscribersCollection();
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
}

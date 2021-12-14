<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a prototype for a cards action.
 *
 */
abstract class Billrun_ActionManagers_Cards_Action {

	use Billrun_ActionManagers_ErrorReporter;

	protected $collection = null;

	/**
	 * Create an instance of the CardsAction type.
	 */
	public function __construct($params) {
		$this->collection = Billrun_Factory::db()->cardsCollection();
		$this->baseCode = 1100;
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

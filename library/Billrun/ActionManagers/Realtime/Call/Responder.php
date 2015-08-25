<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a prototype for a Realtime response action.
 *
 */
abstract class Billrun_ActionManagers_Realtime_Call_Responder {

	protected $row;


	/**
	 * Create an instance of the RealtimeAction type.
	 */
	public function __construct($row) {
		$this->row = $row;
	}
	
	/**
	 * Get response message
	 */
	public abstract function getResponse();
}

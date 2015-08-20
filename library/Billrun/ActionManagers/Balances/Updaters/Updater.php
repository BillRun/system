<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Description of Updater
 *
 * @author tom
 */
abstract class Billrun_ActionManagers_Balances_Updaters_Updater {
	
	protected $isIncrement = true;
	
	/**
	 * Create a new instance of the updater class.
	 */
	public function __construct($increment = true) {
		$this->isIncrement = $increment;
	}
}

<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing db class
 *
 * @package  Db
 * @since    0.5
 */
class Billrun_Connection extends Mongodloid_Connection {

	/**
	 * create instance of the connection db
	 * 
	 * @param MongoDB $newDb The PHP Driver MongoDb instance
	 * 
	 * @return Mongodloid_Db instance
	 */
	protected function createInstance($newDb) {
		return new Billrun_Db($newDb, $this);
	}

}
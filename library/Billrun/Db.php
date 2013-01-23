<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing db class
 *
 * @package  Db
 * @since    1.0
 */
class Billrun_Db extends Mongodloid_Db {
	
	/**
	 * constant of log collection name
	 */
	const log_table = 'log';

	/**
	 * constant of lines collection name
	 */
	const lines_table = 'lines';

	/**
	 * constant of billrun collection name
	 */
	const billrun_table = 'billrun';

	/**
	 * constant of events collection name
	 */
	const events_table = 'events';

	static public function getInstance() {
		$config = Billrun_Factory::config();
		$conn = Billrun_Connection::getInstance($config->db->host, $config->db->port);
		return $conn->getDB($config->db->name);
	}

}
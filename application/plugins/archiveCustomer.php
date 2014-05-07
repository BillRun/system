<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Customer lines archiving plugin
 * 
 * @package  Application
 * @subpackage Plugins
 * @since    0.5
 */
class archiveCustomerPlugin extends archivePlugin {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'archiveCustomer';

	/**
	 * method to declare the archive scope data
	 * 
	 * @return array query to run. the results lines will be removed
	 * @todo 000000 is not a valid billrun stamp anymore
	 */
	protected function getQuery() {
		return array(
			'urt' => array('$lte' => new MongoDate(strtotime($this->archivingHorizon))),
			'billrun' => array('$exists' => true, '$ne' => '000000'),
		);
	}

}

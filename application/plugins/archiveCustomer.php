<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Customer lines archiving plugin
 * (TODO unify this logic with archive wholesale)
 * @package  Application
 * @subpackage Plugins
 * @since    0.5
 */
class archiveCustomerPlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'archiveCustomer';

	/**
	 * the container dataCustomer of the archive
	 * @var array
	 */
	protected $dataCustomer = array();

	/**
	 * this variable hold the time to start archiving  from.
	 * @var type 
	 */
	protected $archivingHorizon = '-3 months';

	/**
	 * method to declare the archive scope data
	 * 
	 * @return array query to run. the results lines will be removed
	 */
	protected function getQuery() {
		return array(
			'urt' => array('$lte' => new MongoDate(strtotime($this->archivingHorizon))),
			'billrun' => array('$exists' => true, '$ne' => '000000'),
		);
	}

}

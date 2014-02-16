<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Wholesale line archiving plugin
 *
 * @package  Application
 * @subpackage Plugins
 * @since    0.5
 */
class archiveWholesalePlugin extends archivePlugin {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'archiveWholesale';

	/**
	 * method to declare the archive scope data
	 * 
	 * @return array query to run. the results lines will be removed
	 */
	protected function getQuery() {
		return array(
			'urt' => array('$lte' => new MongoDate(strtotime($this->archivingHorizon))), //TODO  move this to configuration
			'arate' => false,
		);
	}

}

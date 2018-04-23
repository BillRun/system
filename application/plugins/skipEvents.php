<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Skip events plugin mark lines which don't need to trigger events.
 *
 * @package  Application
 * @subpackage Plugins
 * @since    5.8
 */
class skipEventsPlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'skipEvents';

	public function beforeRebalancingLines($line) {
		$line['skip_events'] = true;
	}
	
	public function beforeUpdateRebalanceLines(&$updateQuery) {
		Billrun_Factory::log('Plugin skipEvents triggered', Zend_Log::INFO);
		$updateQuery['$set']['skip_events'] = true;
	}
	
	public function beforeTriggerEvents(&$skipEvents, $row) {
		if (!empty($row['skip_events'])) {
			$skipEvents = true;
		}
	}

}

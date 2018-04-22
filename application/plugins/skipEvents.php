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

	public function BeforeRebalancingLines($line) {
		$line['skip_events'] = true;
		Billrun_Factory::log('Line ' . $line['stamp'] . ' marked as skip events', Zend_Log::INFO);
	}
	
	public function BeforeUpdateRebalanceLines(&$skipEvents) {
		$skipEvents = true;
	}
	
	public function BeforeTriggerEvents($skipEvents, $row) {
		if (!empty($row['skip_events'])) {
			$skipEvents = true;
		}
	}

}

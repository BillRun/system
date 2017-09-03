<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2017 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Plugin to handle auto renew charges
 *
 * @package  Application
 * @subpackage Plugins
 * @since    5.6
 */
class autorenewPlugin extends Billrun_Plugin_BillrunPluginBase {
	
	public function cronDay() {
		Billrun_Factory::log('Running auto renew...', Billrun_Log::DEBUG);
		$autoRenews = $this->getAutoRenews();
		foreach ($autoRenews as $autoRenew) {
			$autoRenewHandler = Billrun_Autorenew_Manager::getInstance($autoRenew);
			$autoRenewHandler->autoRenew();
		}
	}
	
	protected function getAutoRenewsQuery() {
		$query = Billrun_Utils_Mongo::getDateBoundQuery();
		$query['next_renew'] = array(
			'$gte' => new MongoDate(strtotime(('midnight'))),
			'$lt' => new MongoDate(strtotime(('tomorrow midnight'))),
		);
		$query['cycles_remaining'] = array('$gt' => 0);
		return $query;
	}
	
	protected function getAutoRenews() {
		$query = $this->getAutoRenewsQuery();
		return Billrun_Factory::db()->autorenewCollection()->query($query);
	}
}

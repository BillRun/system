<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi unique get operation
 * Retrieve list of entities while the key or name field is unique
 * This is accounts unique get
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Action_Get_Balances extends Models_Action_Get {

	protected function runQuery() {
		$this->adjustQuery();
		return parent::runQuery();
	}

	protected function adjustQuery() {
		$config = Billrun_Factory::config();
		$isSidLevel = $config->getConfigValue("balances.sid_level", false);
		$query = $this->query;

		if ($isSidLevel && isset($query['aid'])) {
			$sids = Billrun_Factory::db()->subscribersCollection()->distinct('sid', ['aid'=>$query['aid']]);
			$query['$or'] = array(
				array(
					'aid' => $query['aid'],
					'sid' => 0
				),
				array(
					'sid' => array('$in' => $sids)
				)
			);
			unset($query['aid']);
			$this->query = $query;
		}
	}
}

<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Balances model class
 *
 * @package  Models
 * @subpackage Table
 * @since    0.5
 */
class BalancesModel extends TableModel {

	public function __construct(array $params = array()) {
		$params['collection'] = 'balances';
                $params['db'] = 'balances';
		parent::__construct($params);
		$this->search_key = "stamp";
	}

	/**
	 * method to receive the balances lines that over requested date usage
	 * 
	 * @return Mongodloid_Cursor Mongo cursor for iteration
	 */
	public function getBalancesVolume($plan, $data_usage, $from_account_id, $to_account_id, $billrun) {
		$params['name'] = $plan;
		$params['time'] = Billrun_Util::getStartTime($billrun);
		$plan_id = Billrun_Factory::plan($params);
		$id = $plan_id->get('_id')->getMongoID();
		$data_usage_bytes = Billrun_Util::megabytesToBytesFormat($data_usage);
		
		return $this->collection->query(array(
					'balance.totals.data.usagev' => array('$gt' => $data_usage_bytes),
					'billrun_month' => $billrun,
					'current_plan'=> Billrun_Factory::db()->plansCollection()->createRef($id),
					'aid' => array('$gte' => (int)$from_account_id, '$lte' => (int)$to_account_id),
		))->cursor()->setReadPreference(MongoClient::RP_SECONDARY_PREFERRED)->hint(array('aid' => 1, 'billrun_month'))->limit($this->size);
	}

}
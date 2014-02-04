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
		$params = array(
			'name' => $plan,
			'time' => Billrun_Util::getStartTime($billrun),
		);
		$plan_id = Billrun_Factory::plan($params);
		$id = $plan_id->get('_id')->getMongoID();
		$data_usage_bytes = Billrun_Util::megabytesToBytesFormat((int) $data_usage);

		$query = array(
			'aid' => array('$gte' => (int) $from_account_id, '$lte' => (int) $to_account_id),
			'billrun_month' => $billrun,
			'balance.totals.data.usagev' => array('$gt' => (float) $data_usage_bytes),
			'current_plan' => Billrun_Factory::db()->plansCollection()->createRef($id),
		);
//		print_R($query);die;
		return $this->collection->query($query)->cursor()->setReadPreference(MongoClient::RP_SECONDARY_PREFERRED)->hint(array('aid' => 1, 'billrun_month' => 1))->limit($this->size);
	}

	/**
	 * method to receive the balances lines that over requested thershold
	 * 
	 * @return Mongodloid_Cursor Mongo cursor for iteration
	 */
	public function getFraudBalances($filterBy, $thershold, $exclude = NULL, $usaget = NULL, $plan = NULL) {
		$billrun = Billrun_Util::getBillrunKey(time());

		if (!empty($plan)) {
			$params = array(
				'name' => $plan,
				'time' => Billrun_Util::getStartTime($billrun),
			);

			$plan_id = Billrun_Factory::plan($params);
			$id = $plan_id->get('_id')->getMongoId();
			$plan = Billrun_Factory::db()->plansCollection()->createRef($id);
		} else {
			$plan = array('$exists' => true);
		}

		$properties = array('call', 'data', /* 'incoming_call', 'incoming_sms', */ 'intl_roam_call', 'intl_roam_data', 'intl_roam_incoming_call',
							'intl_roam_incoming_sms', 'intl_roam_mms', 'intl_roam_sms', 'mms', 'out_plan_call', 'out_plan_sms', 'sms');

		$arr = array();
		foreach ($properties as $value) {

			if ((empty($exclude) || !preg_match("/^$exclude/", $value)) && (empty($usaget) || preg_match("/$usaget/", $value) !== 0)) {
				$arr[] = '$balance.totals.' . $value . '.' . $filterBy;
			}
		}

		$hint_month = array(
			'$match' => array(
				'aid' => array('$gt' => 0),
				'billrun_month' => $billrun,
		));

		$match = array(
			'$match' => array(
				'current_plan' => $plan,
		));

		$group = array(
			'$group' => array(
				'_id' => array('aid' => '$aid', 'sid' => '$sid'),
				'sum' => array(
					'$sum' => array(
						'$add' => $arr
		))));

		$match2 = array(
			'$match' => array(
				'sum' => array('$gte' => (float) $thershold),
		));
		$skip = array(
			'$skip' => max(0,$this->size * $this->page - $this->size) 
		);
		
		$limit = array(
			'$limit' => $this->size
		);

		return $this->collection->aggregate($hint_month, $match, $group, $match2, $skip, $limit);
	}

}

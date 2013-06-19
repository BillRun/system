<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Subscriber
 *
 * @author eran
 */
class Billrun_Model_Subscriber {

	/**
	 * Get subscriber information for a certain month
	 * @param type $subscriberId
	 * @param type $billrunKey
	 * @return boolean
	 */
	static public function getBalance($subscriberId, $billrunKey) {
		$subcr = Billrun_Factory::db()->balancesCollection()->query(array(
				'subscriber_id' => $subscriberId,
				'billrun_month' => $billrunKey
			))->cursor()->current();

		if (!count($subcr->getRawData())) {
			return FALSE;
		}
		return $subcr;
	}

	/**
	 * Create a new subscriber balance entry in a given month.
	 * @param type $billrun_month
	 * @param type $subscriber_id
	 * @param type $plan
	 * @param type $account_id
	 * @return boolean
	 */
	static public function createBalance($billrunKey, $subscriberId, $plan_ref, $accountId) {
		if (!self::getBalance($subscriberId, $billrunKey)) { //@TODO remove this check if all functions use createBalanceIfMissing function before
			Billrun_Factory::log('Adding subscriber ' . $subscriberId . ' to balances collection', Zend_Log::INFO);
			$newSubscriber = new Mongodloid_Entity(self::getEmptySubscriberBalanceEntry($billrunKey, $accountId, $subscriberId, $plan_ref));
			$newSubscriber->collection(Billrun_Factory::db()->balancesCollection());
			$newSubscriber->save();
			return self::getBalance($subscriberId, $billrunKey);
		}
		return FALSE;
	}

	/**
	 * get a new subscriber array to be place in the DB.
	 * @param type $billrun_month
	 * @param type $account_id
	 * @param type $subscriber_id
	 * @param type $plan_current
	 * @return type
	 */
	static public function getEmptySubscriberBalanceEntry($billrun_month, $account_id, $subscriber_id, $plan_ref) {
		return array(
			'billrun_month' => $billrun_month,
			'account_id' => $account_id,
			'subscriber_id' => $subscriber_id,
			'plan_current' => $plan_ref,
			//'number' => $this->subscriberNumber, //@TODO remove before production here to allow offline subscriber search...
			'balance' => self::getEmptyBalance(),
		);
	}

	static public function getEmptyBalance() {
		$ret = array(
			'totals' => array(),
			'cost' => 0,
		);
		$usage_types = array('call', 'incoming_call', 'sms', 'data', 'inter_roam_incoming_call', 'inter_roam_call', 'inter_roam_callback', 'inter_roam_sms', 'inter_roam_data', 'inter_roam_incoming_sms',);
		foreach ($usage_types as $usage_type) {
			$ret['totals'][$usage_type] = self::getEmptyUsageTypeTotals();
		}
		return $ret;
	}

	static public function getEmptyUsageTypeTotals() {
		return array(
			'usagev' => 0,
			'cost' => 0,
		);
	}

	static public function getBillrun($account_id, $billrun_key) {
		$billrun = Billrun_Factory::billrun(array(
			'account_id' => $account_id,
			'billrun_key' => $billrun_key,
		));
		return $billrun;
	}

}

?>

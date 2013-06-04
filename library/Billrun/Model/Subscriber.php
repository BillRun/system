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
	static public function get( $subscriberId, $billrunKey ) {		
		$subcr = Billrun_Factory::db()->subscribersCollection()->query(array(
						'subscriber_id' => $subscriberId, 
						'billrun_month' => $billrunKey
					 ))->cursor()->current();
		
		if(!count($subcr->getRawData())) {
			return FALSE;
		}
		return $subcr;
	}
	
	/**
	 * Create aa new subscriber in a given month.
	 * @param type $billrun_month
	 * @param type $subscriber_id
	 * @param type $plan
	 * @param type $account_id
	 * @return boolean
	 */
	static public function create($billrunKey, $subscriberId, $plan, $accountId) {
		if(!self::get( $subscriberId, $billrunKey )) {
			Billrun_Factory::log('Adding subscriber ' .  $subscriberId . ' to subscribers collection', Zend_Log::INFO);
			$newSubscriber = new Mongodloid_Entity(self::getEmptySubscriberEntry($billrunKey, $accountId, $subscriberId, $plan));
			$newSubscriber->collection(Billrun_Factory::db()->subscribersCollection());
			$newSubscriber->save();
			return self::get( $subscriberId, $billrunKey );
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
	static public function getEmptySubscriberEntry($billrun_month, $account_id, $subscriber_id, $plan_current) {
		return array(
			'billrun_month' => $billrun_month,
			'account_id' => $account_id,
			'subscriber_id' => $subscriber_id,
			'plan_current' => $plan_current,
			//'number' => $this->subscriberNumber, //@TODO remove before production here to allow offline subscriber search...
			'balance' => array(
				'usage_counters' => array(
					'call' => 0,
					'sms' => 0,
					'data' => 0,
					'inter_call' => 0,
					'inter_sms' => 0,
					'inter_roam_call' => 0,
					'inter_roam_callback' => 0,
					'inter_roam_sms' => 0,
					'inter_roam_data' => 0,
				),
				'current_charge' => 0,
			),
		);
	}
}

?>

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
		
		if(!count($subcr->getRawData()) {
			return FALSE;
		}
		return $subcr;
	}
	
	/**
	 * Create aa new subscriber in a given month.
	 * @param type $billrun_month
	 * @param type $subscriber_id
	 * @param type $plan_current
	 * @param type $account_id
	 * @return boolean
	 */
	static public function create($billrun_month, $subscriber_id, $plan_current, $account_id) {
		if(self::get( $subscriber_id, $billrun_month )) {
			Billrun_Factory::log('Adding subscriber ' .  $subscriber_id . ' to subscribers collection', Zend_Log::INFO);
			$newSubscriber = new Mongodloid_Entity(self::getEmptySubscriberEntry($billrun_key, $account_id, $subscriber_id, $plan));
			$newSubscriber->collection(Billrun_Factory::db()->subscribersCollection());
			$newSubscriber->save();
			return self::get( $subscriber_id, $billrun_month );
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
					'international_call' => 0,
					'international_sms' => 0,
				),
				'current_charge' => 0,
			),
		);
	}
}

?>

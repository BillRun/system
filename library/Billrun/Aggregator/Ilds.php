<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
require_once __DIR__ . '/../../../application/golan/' . 'subscriber.php';

/**
 * Billing aggregator class for ilds records
 *
 * @package  calculator
 * @since    1.0
 */
class Billrun_Aggregator_Ilds extends Billrun_Aggregator {


		/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'ilds';
	
	/**
	 * execute aggregate
	 */
	public function aggregate() {
		// @TODO trigger before aggregate
		$this->dispatcher->trigger('beforeAggregate', array($this->data,&$this));
		 
		foreach ($this->data as $item) {
			$this->dispatcher->trigger('beforeAggregateLine', array(&$item,&$this));
			$time = $item->get('call_start_dt');
			
			// @TODO make it configurable
			$previous_month = date("Ymt235959", strtotime("previous month"));
			
			if ($time > $previous_month) {
				$this->log->log("time frame is not till the end of previous month " . $time . "; continue to the next line", Zend_Log::INFO);
				continue;
			}

			if (!$item->get('account_id') || !$item->get('subscriver_id')) {
				// load subscriber
				$phone_number = $item->get('caller_phone_no');
				$subscriber = golan_subscriber::get($phone_number, $time);
				if (!$subscriber) {
					$this->log->log("subscriber not found. phone:" . $phone_number . " time: " . $time, Zend_Log::INFO);
					continue;
				}
			} else {
				$subscriber = array(
					'account_id' => $item->get('account_id'),
					'id' => $item->get('subscriver_id'),
				);
			}


			$subscriber_id = $subscriber['id'];
			$account_id = $subscriber['account_id'];
			
			// update billing line with billrun stamp
			if (!$this->updateBillingLine($subscriber_id, $account_id, $item)) {
				$this->log->log("subscriber " . $subscriber_id . " cannot update billing line", Zend_Log::INFO);
				continue;
			}
			
			if(isset($this->excludes['subscribers']) && in_array($subscriber_id, $this->excludes['subscribers'])) {
				$this->log->log("subscriber " . $subscriber_id . " is in the excluded list skipping billrun for him.", Zend_Log::INFO);
				//mark line as excluded.
				$item['billrun_excluded'] = true; 
			}
			
			$save_data = array();
			
			//if the subscriber should be excluded dont update the billrun.
			if(!(isset($item['billrun_excluded']) && $item['billrun_excluded']) ) {
				// load the customer billrun line (aggregated collection)
				$billrun = $this->loadSubscriberBillrun($subscriber);

				if (!$billrun) {
					$this->log->log("subscriber " . $subscriber_id . " cannot load billrun", Zend_Log::INFO);
					continue;
				}

				// update billrun subscriber with amount
				if (!$this->updateBillrun($billrun, $item)) {
					$this->log->log("subscriber " . $subscriber_id . " cannot update billrun", Zend_Log::INFO);
					continue;
				}
				
				$save_data[self::billrun_table] = $billrun;
			}
			
		
			$save_data[self::lines_table] = $item;
			
			$this->dispatcher->trigger('beforeAggregateSaveLine', array(&$save_data, &$this));
			
			if (!$this->save($save_data)) {
				$this->log->log("subscriber " . $subscriber_id . " cannot save data", Zend_Log::INFO);
				continue;
			}

			$this->log->log("subscriber " . $subscriber_id . " saved successfully", Zend_Log::INFO);
		}
		// @TODO trigger after aggregate
		$this->dispatcher->trigger('afterAggregate', array($this->data,&$this));
	}

	/**
	 * load the subscriber billrun raw (aggregated)
	 * if not found, create entity with default values
	 * @param type $subscriber
	 *
	 * @return Mongodloid_Entity
	 */
	public function loadSubscriberBillrun($subscriber) {

		$billrun = $this->db->getCollection(self::billrun_table);
		$resource = $billrun->query()
			//->exists("subscriber.{$subscriber['id']}")
			->equals('account_id', $subscriber['account_id'])
			->equals('stamp', $this->getStamp());

		if ($resource && $resource->count()) {
			foreach ($resource as $entity) {
				break;
			} // @todo make this in more appropriate way
			return $entity;
		}

		$values = array(
			'stamp' => $this->stamp,
			'account_id' => $subscriber['account_id'],
			'subscribers' => array($subscriber['id'] => array('cost' => array())),
			'cost' => array(),
		);

		return new Mongodloid_Entity($values, $billrun);
	}

	/**
	 * method to update the billrun by the billing line (row)
	 * @param Mongodloid_Entity $billrun the billrun line
	 * @param Mongodloid_Entity $line the billing line
	 *
	 * @return boolean true on success else false
	 */
	protected function updateBillrun($billrun, $line) {
		// @TODO trigger before update row
		
		$current = $billrun->getRawData();
		$added_charge = $line->get('price_customer');

		if (!is_numeric($added_charge)) {
			//raise an error
			return false;
		}

		$type = $line->get('type');
		$subscriberId = $line->get('subscriber_id');
		if (!isset($current['subscribers'][$subscriberId])) {
			$current['subscribers'][$subscriberId] = array('cost' => array());
		}
		if (!isset($current['cost'][$type])) {
			$current['cost'][$type] = $added_charge;
			$current['subscribers'][$subscriberId]['cost'][$type] = $added_charge;
		} else {
			$current['cost'][$type] += $added_charge;
			$subExist = isset($current['subscribers'][$subscriberId]['cost']) && isset($current['subscribers'][$subscriberId]['cost'][$type]);
			$current['subscribers'][$subscriberId]['cost'][$type] = ($subExist ? $current['subscribers'][$subscriberId]['cost'][$type] : 0 ) + $added_charge;
		}

		$billrun->setRawData($current);
		// @TODO trigger after update row
		// the return values will be used for revert
		return array(
			'newCost' => $current['cost'],
			'added' => $added_charge,
		);
	}

	/**
	 * update the billing line with stamp to avoid another aggregation
	 *
	 * @param int $subscriber_id the subscriber id to update
	 * @param Mongodloid_Entity $line the billing line to update
	 *
	 * @return boolean true on success else false
	 */
	protected function updateBillingLine($subscriber_id, $account_id, $line) {
		$current = $line->getRawData();
		$added_values = array(
			'account_id' => $account_id,
			'subscriber_id' => $subscriber_id,
			'billrun' => $this->getStamp(),
		);
		$newData = array_merge($current, $added_values);
		$line->setRawData($newData);
		return true;
	}

	/**
	 * load the data to aggregate
	 */
	public function load($initData = true) {
		$query = "price_customer EXISTS and price_provider EXISTS and billrun NOT EXISTS";		
		
		if ($initData) {
			$this->data = array();
		}

		$lines = $this->db->getCollection(self::lines_table);
		$resource = $lines->query($query);

		foreach ($resource as $entity) {
			$this->data[] = $entity;
		}

		$this->log->log("aggregator entities loaded: " . count($this->data), Zend_Log::INFO);
		
		$this->dispatcher->trigger('afterAggregatorLoadData', array('aggregator' => $this));
	}

	protected function save($data) {
		foreach ($data as $coll_name => $coll_data) {
			$coll = $this->db->getCollection($coll_name);
			$coll->save($coll_data);
		}
		return true;
	}

}

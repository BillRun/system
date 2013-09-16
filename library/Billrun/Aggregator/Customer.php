<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing aggregator class for Golan customers records
 *
 * @package  calculator
 * @since    0.5
 */
class Billrun_Aggregator_Customer extends Billrun_Aggregator {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'customer';

	/**
	 * 
	 * @var int
	 */
	protected $page = 1;

	/**
	 * 
	 * @var int
	 */
	protected $size = 200;

	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $plans = null;

	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $lines = null;

	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $billrun = null;

	/**
	 *
	 * @var int invoice id to start from
	 */
	protected $min_invoice_id = 101;

	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['page']) && $options['page']) {
			$this->page = $options['page'];
		}
		if (isset($options['size']) && $options['size']) {
			$this->size = $options['size'];
		}

		$this->plans = Billrun_Factory::db()->plansCollection();
		$this->lines = Billrun_Factory::db()->linesCollection();
		$this->billrun = Billrun_Factory::db()->billrunCollection();
	}

	/**
	 * load the data to aggregate
	 */
	public function load() {
		$date = Billrun_Util::getLastChargeTime();
		$subscriber = Billrun_Factory::subscriber();
		$this->data = $subscriber->getList($this->page, $this->size, $date);

		Billrun_Factory::log()->log("aggregator entities loaded: " . count($this->data), Zend_Log::INFO);

		Billrun_Factory::dispatcher()->trigger('afterAggregatorLoadData', array('aggregator' => $this));
	}

	/**
	 * execute aggregate
	 */
	public function aggregate() {
		// @TODO trigger before aggregate
		Billrun_Factory::dispatcher()->trigger('beforeAggregate', array($this->data, &$this));

		$billrun_key = $this->getStamp();

		foreach ($this->data as $account_id => $account) {
			foreach ($account as $subscriber) {
				Billrun_Factory::dispatcher()->trigger('beforeAggregateLine', array(&$subscriber, &$this));
				$account_id = $subscriber->account_id;
				$subscriber_id = $subscriber->subscriber_id;
				$plan_name = $subscriber->plan;
				if (is_null($plan_name)) {
					$subscriber_status = "closed";
					Billrun_Billrun::setSubscriberStatus($account_id, $subscriber_id, $billrun_key, $subscriber_status);
				} else {
					$subscriber_status = "open";
					$flat_price = $subscriber->getFlatPrice();
					if (is_null($flat_price)) {
						Billrun_Factory::log()->log("Couldn't find flat price for subscriber " . $subscriber_id . " for billrun " . $billrun_key, Zend_Log::ALERT);
						continue;
					}
					Billrun_Factory::log('Adding flat to subscriber ' . $subscriber_id, Zend_Log::INFO);
					$flat_line = $this->saveFlatLine($subscriber, $billrun_key);

					$plan = $flat_line['plan_ref'];
					if (!$billrun = Billrun_Billrun::updateBillrun($billrun_key, array(), array('price_customer' => $flat_price), $flat_line, $plan['vatable'], $subscriber_status)) {
						Billrun_Factory::log()->log("Flat costs already exist in billrun collection for subscriber " . $subscriber_id . " for billrun " . $billrun_key, Zend_Log::NOTICE);
					} else {
						Billrun_Billrun::setSubscriberStatus($account_id, $subscriber_id, $billrun_key, $subscriber_status);
						$flat_line['billrun_ref'] = $billrun->createRef($this->billrun);
						$flat_line->save();
					}
				}
			}
			Billrun_Billrun::close($account_id, $billrun_key, $this->min_invoice_id);
		}
//		Billrun_Factory::dispatcher()->trigger('beforeAggregateSaveLine', array(&$save_data, &$this));
		// @TODO trigger after aggregate
		Billrun_Factory::dispatcher()->trigger('afterAggregate', array($this->data, &$this));
	}

	protected function saveFlatLine($subscriber, $billrun_key) {
		$account_id = $subscriber->account_id;
		$subscriber_id = $subscriber->subscriber_id;
		$flat_entry = new Mongodloid_Entity($subscriber->getFlatEntry($billrun_key));
		$flat_entry->collection($this->lines);
		$query = array(
			'account_id' => $account_id,
			'subscriber_id' => $subscriber_id,
			'billrun_key' => $billrun_key,
			'type' => 'flat',
		);
		$update = array(
			'$setOnInsert' => $flat_entry->getRawData(),
		);
		$options = array(
			'upsert' => true,
			'new' => true,
		);
		return $this->lines->findAndModify($query, $update, array(), $options);
	}

	protected function save($data) {
		
	}

	/**
	 *
	 * @param type $subscriber_id
	 * @param type $item
	 * @deprecated update of billing line is done in customer pricing stage
	 */
	protected function updateBillingLine($subscriber_id, $item) {
		
	}

	/**
	 * method to update the billrun by the billing line (row)
	 * @param Mongodloid_Entity $billrun the billrun line
	 * @param Mongodloid_Entity $line the billing line
	 *
	 * @return boolean true on success else false
	 */
	protected function updateBillrun($billrun, $line) {
		
	}

}

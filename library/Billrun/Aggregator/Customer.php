<?php

require_once 'application/helpers/Subscriber/Golan.php';

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
	protected $page = 0;

	/**
	 * 
	 * @var int
	 */
	protected $size = 10000;

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
	 * @var int aggregation period in months
	 */
	protected $months = 4;

	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['page']) && $options['page']) {
			$this->page = $options['page'];
		}
		if (isset($options['size']) && $options['size']) {
			$this->size = $options['size'];
		}
		if (isset($options['aggregator']['months'])) {
			$this->months = $options['aggregator']['months'];
		}
		$this->plans = Billrun_Factory::db()->plansCollection();
		$this->lines = Billrun_Factory::db()->linesCollection();
	}

	/**
	 * load the data to aggregate
	 */
	public function load() {
		$date = Billrun_Util::getLastChargeTime(true);
		$this->data = Subscriber_Golan::getList($this->page, $this->size, $date);

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

		// Add flat to lines
		foreach ($this->data as $subscriber) {
			try {
				$subscriber->addFlat($billrun_key);
			} catch (Exception $e) {
				Billrun_Factory::log()->log("Flat already exists for subscriber " . $subscriber->subscriber_id . " for billrun " . $billrun_key, Zend_Log::NOTICE);
				continue;
			}
		}

		$billrun_upper_bound_date = new MongoDate(Billrun_Util::getLastChargeTime(true));
		$billrun_lower_bound_date = new MongoDate(strtotime($this->months . " months ago", $billrun_upper_bound_date->sec));

		foreach ($this->data as $subscriber) {
			Billrun_Factory::dispatcher()->trigger('beforeAggregateLine', array(&$subscriber, &$this));

			$subscriber_lines = $this->lines->query()
				->query('subscriber_id', $subscriber->subscriber_id)
//				->less('unified_record_time', $billrun_upper_bound_date) //@TODO uncomment this
				->greaterEq('unified_record_time', $billrun_lower_bound_date)
				->notExists('billrun')
				->query(array('$or' => array(
						array('customer_rate' => array(
								'$exists' => true,
								'$ne' => false,
							),),
						array('flat_key' => array(
								'$exists' => true,
							),),
					)))
				->exists('price_customer')
				->cursor();

			foreach ($subscriber_lines as $line) {
				//##################################
//				if (!$this->updateBillingLine($subscriber, $line)) {
//					Billrun_Factory::log()->log("subscriber " . $subscriber_id . " cannot update billing line", Zend_Log::INFO);
//					continue;
//				}
//
//				if (isset($this->excludes['subscribers']) && in_array($subscriber_id, $this->excludes['subscribers'])) {
//					Billrun_Factory::log()->log("subscriber " . $subscriber_id . " is in the excluded list skipping billrun for him.", Zend_Log::INFO);
//					//mark line as excluded.
//					$line['billrun_excluded'] = true;
//				}
//
//				$save_data = array();
//
//				//if the subscriber should be excluded dont update the billrun.
//				if (!(isset($line['billrun_excluded']) && $line['billrun_excluded'])) {
//					// load the customer billrun line (aggregated collection)
//					$billrun = $this->loadSubscriberBillrun($subscriber);
//
//					if (!$billrun) {
//						Billrun_Factory::log()->log("subscriber " . $subscriber_id . " cannot load billrun", Zend_Log::INFO);
//						continue;
//					}
//
//					// update billrun subscriber with amount
//					if (!$this->updateBillrun($billrun, $line)) {
//						Billrun_Factory::log()->log("subscriber " . $subscriber_id . " cannot update billrun", Zend_Log::INFO);
//						continue;
//					}
//
//					$save_data[Billrun_Factory::db()->billrun] = $billrun;
//				}
//
//
//				$save_data[Billrun_Factory::db()->lines] = $line;
//
//				Billrun_Factory::dispatcher()->trigger('beforeAggregateSaveLine', array(&$save_data, &$this));
//				
//				if (!$this->save($save_data)) {
//					Billrun_Factory::log()->log("subscriber " . $subscriber_id . " cannot save data", Zend_Log::INFO);
//					continue;
//				}
//				
//				Billrun_Factory::log()->log("subscriber " . $subscriber_id . " saved successfully", Zend_Log::INFO);
				//##################################
			}

			Billrun_Factory::dispatcher()->trigger('beforeAggregateSaveLine', array(&$save_data, &$this));
		}
		// @TODO trigger after aggregate
		Billrun_Factory::dispatcher()->trigger('afterAggregate', array($this->data, &$this));
	}

	/**
	 * load the subscriber billrun raw (aggregated)
	 * if not found, create entity with default values
	 * @param type $subscriber
	 *
	 * @return Mongodloid_Entity
	 */
	public function loadSubscriberBillrun($subscriber) {

		$billrun = Billrun_Factory::db()->billrunCollection();
		$resource = $billrun->query()
			//->exists("subscriber.{$subscriber['id']}")
			->equals('account_id', $subscriber->account_id)
			->equals('stamp', $this->getStamp());

		if ($resource && $resource->count()) {
			foreach ($resource as $entity) {
				break;
			} // @todo make this in more appropriate way
			return $entity;
		}

		$values = array(
			'stamp' => $this->stamp,
			'account_id' => $subscriber->account_id,
			'subscribers' => array($subscriber->id => array('cost' => array())),
			'cost' => array(),
			'source' => self::$type,
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
	protected function updateBillingLine($subscriber, $line) {
		$current = $line->getRawData();
		$added_values = array(
			'billrun' => $this->getStamp(),
		);

		$newData = array_merge($current, $added_values);
		$line->setRawData($newData);
		return true;
	}

	protected function save($data) {
		foreach ($data as $coll_name => $coll_data) {
			$coll = Billrun_Factory::db()->getCollection($coll_name);
			$coll->save($coll_data);
		}
		return true;
	}

}

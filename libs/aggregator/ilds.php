<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */
require_once __DIR__ . '/../' . 'subscriber.php';

/**
 * Billing aggregator class for ilds records
 *
 * @package  calculator
 * @since    1.0
 */
class aggregator_ilds extends aggregator {

	/**
	 * execute aggregate
	 */
	public function aggregate() {
		// @TODO trigger before aggregate
		foreach ($this->data as $item) {
			// load subscriber
			$phone_number = $item->get('caller_phone_no');
			$time = $item->get('call_start_dt');
			// load subscriber
			$subscriber = subscriber::get($phone_number, $time);

			if (!$subscriber) {
				print "subscriber not found. phone:" . $phone_number . " time: " . $time . PHP_EOL;
				continue;
			}

			$subscriber_id = $subscriber['id'];
			// load the customer billrun line (aggregated collection)
			$billrun = $this->loadSubscriberBillrun($subscriber);

			if (!$billrun) {
				print "subscriber " . $subscriber_id . " cannot load billrun" . PHP_EOL;
				continue;
			}

			// update billrun subscriber with amount
			if (!$this->updateBillrun($billrun, $item)) {
				print "subscriber " . $subscriber_id . " cannot update billrun" . PHP_EOL;
				continue;
			}

			// update billing line with billrun stamp
			if (!$this->updateBillingLine($subscriber_id, $item)) {
				print "subscriber " . $subscriber_id . " cannot update billing line" . PHP_EOL;
				continue;
			}

			$save_data = array(
				self::lines_table => $item,
				self::billrun_table => $billrun,
			);

			if (!$this->save($save_data)) {
				print "subscriber " . $subscriber_id . " cannot save data" . PHP_EOL;
				continue;
			}

			print "subscriber " . $subscriber_id . " saved successfully" . PHP_EOL;
		}
		// @TODO trigger after aggregate	
	}

	public function loadSubscriberBillrun($subscriber) {

		$billrun = $this->db->getCollection(self::billrun_table);
		$resource = $billrun->query()
			->equals('subscriber_id', $subscriber['id'])
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
			'subscriber_id' => $subscriber['id'],
			'cost' => array(),
		);

		return new Mongodloid_Entity($values, $billrun);
	}

	protected function updateBillrun($billrun, $row) {
		// @TODO trigger before update row
		$current = $billrun->getRawData();
		$added_charge = $row->get('price_customer');

		if (!is_numeric($added_charge)) {
			//raise an error 
			return false;
		}

		$type = $row->get('type');
		if (!isset($current['cost'][$type])) {
			$current['cost'][$type] = $added_charge;
		} else {
			$current['cost'][$type] += $added_charge;
		}

		$billrun->setRawData($current);
		// @TODO trigger after update row
		// the return values will be used for revert
		return array(
			'newCost' => $current['cost'],
			'added' => $added_charge,
		);
	}

	protected function updateBillingLine($subscriber_id, $row) {
		$current = $row->getRawData();
		$added_values = array(
			'subscriber_id' => $subscriber_id,
			'billrun' => $this->getStamp(),
		);
		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);
		return true;
	}

	/**
	 * load the data to aggregate
	 */
	public function load($initData = true) {
		$lines = $this->db->getCollection(self::lines_table);
		$query = "price_customer EXISTS and price_provider EXISTS and billrun NOT EXISTS";
		if ($initData) {
			$this->data = array();
		}

		$resource = $lines->query($query);

		foreach ($resource as $entity) {
			$this->data[] = $entity;
		}

		print "aggregator entities loaded: " . count($this->data) . PHP_EOL;
	}

	protected function save($data) {
		foreach ($data as $coll_name => $coll_data) {
			$coll = $this->db->getCollection($coll_name);
			$coll->save($coll_data);
		}
		return true;
	}

}
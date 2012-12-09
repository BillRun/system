<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing aggregator class for ilds records
 *
 * @package  calculator
 * @since    1.0
 */
class aggregator_ilds extends aggregator
{

	/**
	 * execute aggregate
	 */
	public function aggregate()
	{
		// @TODO trigger before aggregate
		foreach ($this->data as $item)
		{
			// load subscriber
//			$phone_number = $item->get('caller_phone_no');
//			$time = $item->get('call_start_dt');
			// load subscriber
//			$subscriber = $this->loadSubscriber($phone_number, $time);
			// temp usage
			$subscriber = array(
				'id' => 123456,
				'account_id' => 1234,
			);

			if (!$subscriber)
			{
				//raise warning
				continue;
			}

			// load the customer billrun line (aggregated collection)
			$billrun = $this->loadSubscriberBillrun($subscriber);

			if (!$billrun)
			{
				//raise warning
				continue;
			}

			// update billrun subscriber with amount
			if (!$this->updateBillrun($billrun, $item))
			{
				//raise warning
				continue;
			}

			// update billing line with billrun stamp
			if (!$this->updateBillingLine($item))
			{
				// revert updateBillrun
				// raise warning
				continue;
			}

			$save_data = array(
				self::lines_table => $item,
				self::billrun_table => $billrun,
			);

			$this->save($save_data);

		}
		// @TODO trigger after aggregate	
	}

	protected function loadSubscriberBillrun($subscriber)
	{
		$query = 'stamp = ' . $this->stamp .
			' and subscriber_id = ' . $subscriber['id'] .
			' and account_id = ' . $subscriber['account_id'];
		$billrun = $this->db->getCollection(self::billrun_table);
		$resource = $billrun->query($query);

		if ($resource && count($resource->cursor()->current()->getRawData()))
		{
			return $resource[0];
		}

		$values = array(
			'stamp' => $this->stamp,
			'account_id' => $subscriber['account_id'],
			'subscriber_id' => $subscriber['id'],
			'cost' => 0,
		);

		return new Mongodloid_Entity($values, $billrun);
	}

	protected function updateBillrun($billrun, $row)
	{
		// @TODO trigger before update row
		$current = $billrun->getRawData();
		$added_charge = $row->get('price_customer');
		if (!$added_charge || !is_numeric($added_charge))
		{
			//raise an error 
			return false;
		}

		$current['cost'] += $added_charge;

		$billrun->setRawData($current);
		// @TODO trigger after update row
		// the return values will be used for revert
		return array(
			'newCost' => $current['cost'],
			'added' => $added_charge,
		);
	}

	protected function updateBillingLine($row)
	{
		$current = $row->getRawData();
		$added_values = array(
			'billrun' => $this->getStamp(),
		);
		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);
		return true;
	}

	/**
	 * load the data to aggregate
	 */
	public function load($initData = true)
	{
		$lines = $this->db->getCollection(self::lines_table);
		$query = "price_customer EXISTS and price_provider EXISTS and billrun NOT EXISTS";
		if ($initData)
		{
			$this->data = array();
		}

		$resource = $lines->query($query);

		foreach ($resource as $entity)
		{
			$this->data[] = $entity;
		}

		print "aggregator entities loaded: " . count($this->data) . PHP_EOL;
	}

	protected function save($data)
	{
		foreach ($data as $coll_name => $coll_data) {
			$coll = $this->db->getCollection($coll_name);
			$coll->save($coll_data);
		}
		return true;
	}

}
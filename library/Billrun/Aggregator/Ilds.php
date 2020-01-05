<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
//require_once __DIR__ . '/../../../application/golan/' . 'subscriber.php';
require_once __DIR__ . '/../../../application/helpers/Subscriber/' . 'Golan.php';
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
		Billrun_Factory::dispatcher()->trigger('beforeAggregate', array($this->data, &$this));

		foreach ($this->data as $item) {
			Billrun_Factory::dispatcher()->trigger('beforeAggregateLine', array(&$item, &$this));
			if ($item['source'] == 'api' && $item['type'] == 'refund') {
				$time = date("YmtHis", $item->get('unified_record_time')->sec);
				$phone_number = $item->get('NDC_SN');
			} else {
				$time = $item->get('call_start_dt');
				$phone_number = $item->get('caller_phone_no');
			}
			// @TODO make it configurable
			$previous_month = date("Ymt235959", strtotime("previous month"));


			if ($time > $previous_month) {
				Billrun_Factory::log()->log("time frame is not till the end of previous month " . $time . "; continue to the next line", Zend_Log::INFO);
				continue;
			}

			if (!$item->get('account_id') || !$item->get('subscriber_id')) {
				// load subscriber
				$phone_number = Billrun_Util::cleanLeadingZeros($item->get('caller_phone_no'));
				$subscriber_golan = Billrun_Factory::subscriber();
				$subsriber_details = $subscriber_golan->load(array("NDC_SN" => $phone_number, "time" => $time));
				$subscriber['account_id'] = $subsriber_details->account_id;
				$subscriber['id'] = $subsriber_details->subscriber_id; //
				if (!$subscriber) {
					Billrun_Factory::log()->log("phone number has not necessary details: account_id & subscriber_id", Zend_Log::INFO);
					continue;
				}
			} else {
				Billrun_Factory::log()->log("subscriber " . $item->get('subscriber_id') . " already in line " . $item->get('stamp'), Zend_Log::INFO);
				$subscriber = array(
					'account_id' => $item->get('account_id'),
					'id' => $item->get('subscriber_id'),
				);
			}

			$subscriber_id = $subscriber['id'];

			// update billing line with billrun stamp
			if (!$this->updateBillingLine($subscriber, $item)) {
				Billrun_Factory::log()->log("subscriber " . $subscriber_id . " cannot update billing line", Zend_Log::INFO);
				continue;
			}

			if (isset($this->excludes['subscribers']) && in_array($subscriber_id, $this->excludes['subscribers']) || !empty($item['prepaid'])) {
				Billrun_Factory::log()->log("subscriber " . $subscriber_id . " is in the excluded list skipping billrun for him.", Zend_Log::INFO);
				//mark line as excluded.
				$item['billrun_excluded'] = true;
			}

			$save_data = array();

			//if the subscriber should be excluded dont update the billrun.
			if (!(isset($item['billrun_excluded']) && $item['billrun_excluded'])) {
				// load the customer billrun line (aggregated collection)
				$billrun = $this->loadSubscriberBillrun($subscriber);

				if (!$billrun) {
					Billrun_Factory::log()->log("subscriber " . $subscriber_id . " cannot load billrun", Zend_Log::INFO);
					continue;
				}

				// update billrun subscriber with amount
				if (!$this->updateBillrun($billrun, $item)) {
					Billrun_Factory::log()->log("subscriber " . $subscriber_id . " cannot update billrun", Zend_Log::INFO);
					continue;
				}

				$save_data[Billrun_Factory::db()->billrun] = $billrun;
			}


			$save_data[Billrun_Factory::db()->lines] = $item;

			Billrun_Factory::dispatcher()->trigger('beforeAggregateSaveLine', array(&$save_data, &$this));

			if (!$this->save($save_data)) {
				Billrun_Factory::log()->log("subscriber " . $subscriber_id . " cannot save data", Zend_Log::INFO);
				continue;
			}

			Billrun_Factory::log()->log("subscriber " . $subscriber_id . " saved successfully", Zend_Log::INFO);
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
			->equals('account_id', $subscriber['account_id'])
			->equals('stamp', $this->getStamp())->notIn('prepaid',[1,true]);

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
			'source' => 'ilds',
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
		if (isset($subscriber['id'])) {
			$subscriber_id = $subscriber['id'];
		} else {
			// todo: alert to log
			return false;
		}
		$current = $line->getRawData();
		$added_values = array(
			'account_id' => $subscriber['account_id'],
			'subscriber_id' => $subscriber_id,
			'billrun' => $this->getStamp(),
		);

		if (isset($subscriber['account_id'])) {
			$added_values['account_id'] = $subscriber['account_id'];
		}

		$newData = array_merge($current, $added_values);
		$line->setRawData($newData);
		return true;
	}

	/**
	 * load the data to aggregate
	 */
	public function load() {

		$min_time = (string) date('Ymd000000', strtotime('7 months ago')); //was 3 months
		$lines = Billrun_Factory::db()->linesCollection();

		$this->data = $lines->query(array(
					'$or' => array(
						array('source' => array('$in' => array('ilds', 'premium'))), //premium or ilds!!!
						array('source' => 'api', 'type' => 'refund', 'reason' => 'ILDS_DEPOSIT')
					),
					'call_start_dt' => array('$gte' => $min_time),
				))
				->notExists('billrun')
				->exists('price_provider')
				->exists('price_customer')
				->cursor();

		Billrun_Factory::log()->log("aggregator entities loaded: " . $this->data->count(), Zend_Log::INFO);

		Billrun_Factory::dispatcher()->trigger('afterAggregatorLoadData', array('aggregator' => $this));
	}

	protected function save($data) {
		foreach ($data as $coll_name => $coll_data) {
			$coll = Billrun_Factory::db()->getCollection($coll_name);
			$coll->save($coll_data);
		}
		return true;
	}

}

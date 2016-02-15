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
class Billrun_Aggregator_Balance extends Billrun_Aggregator_Ilds {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'balance';
	protected $subscriber_billrun;
	protected $aid;
	
	public function __construct($options = array()) {
		parent::__construct($options);
		$this->aid = $options['aid'];
		
	}

	/**
	 * execute aggregate
	 */
	public function aggregate() {
		// @TODO trigger before aggregate
//		Billrun_Factory::dispatcher()->trigger('beforeAggregate', array($this->data, &$this));

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
				$phone_number = $item->get('caller_phone_no');
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

			if (isset($this->excludes['subscribers']) && in_array($subscriber_id, $this->excludes['subscribers'])) {
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

		}
		return $save_data;
	}

	/**
	 * load the subscriber billrun raw (aggregated)
	 * if not found, create entity with default values
	 * @param type $subscriber
	 *
	 * @return Mongodloid_Entity
	 */
	public function loadSubscriberBillrun($subscriber) {

		if(!empty($this->subscriber_billrun[$subscriber['account_id']])){
			return $this->subscriber_billrun[$subscriber['account_id']];
		}
		$values = array(
			'stamp' => $this->stamp,
			'account_id' => $subscriber['account_id'],
			'subscribers' => array($subscriber['id'] => array('cost' => array())),
			'cost' => array(),
			'source' => 'ilds',
		);
		$billrun = Billrun_Factory::db()->billrunCollection();
		$this->subscriber_billrun[$subscriber['account_id']] = new Mongodloid_Entity($values, $billrun);
		return $this->subscriber_billrun[$subscriber['account_id']];
	}


	/**
	 * load the data to aggregate
	 */
	public function load() {

		$min_time = (string) date('Ymd000000', strtotime('3 months ago')); //was 3 months
		$lines = Billrun_Factory::db()->linesCollection();
		$count = $lines->count();	
		$this->data = $lines->query(
			array(
					'$or' => array(
						array('source' => array('$in' => array('ilds', 'premium'))), //premium or ilds!!!
						array('source' => 'api', 'type' => 'refund', 'reason' => 'ILDS_DEPOSIT')
					),
					'call_start_dt' => array('$gte' => $min_time),
						'account_id' => "$this->aid",	
				)
			)
				->notExists('billrun')
				->exists('price_provider')
				->exists('price_customer')
				->cursor();

		Billrun_Factory::log()->log("aggregator entities loaded: " . $this->data->count(), Zend_Log::INFO);

		Billrun_Factory::dispatcher()->trigger('afterAggregatorLoadData', array('aggregator' => $this));
	}

}

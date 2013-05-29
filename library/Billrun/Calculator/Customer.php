<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing calculator class for ilds records
 *
 * @package  calculator
 * @since    0.5
 */
class Billrun_Calculator_Customer extends Billrun_Calculator {

	/**
	 * the item we are running on
	 * 
	 * @var Mongo Entity
	 */
	protected $item;

	/**
	 * the relevant record in subscribers document
	 * 
	 * @var Mongo Entity
	 */
	protected $subscriber_line;

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'customer';

	public function __construct($options = array()) {
		parent::__construct($options);
		$this->limit = 10000;
	}

	/**
	 * method to get calculator lines
	 */
	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();

		$query = $lines->query()
			->in('type', array('nsn', 'ggsn', 'smsc', 'mmsc', 'smpp'))
			->exists('customer_rate')
			->notExists('subscriber_id');

		if ($this->limit > 0) {
			$query->limit($this->limit);
		}

		return $query;
	}

	/**
	 * execute the calculation process
	 */
	public function calc() {
		Billrun_Factory::log('Starting calculator ' . self::$type . ' calc', Zend_Log::INFO);
		$subscriber = Billrun_Factory::subscriber();
		$subscribers = Billrun_Factory::db()->subscribersCollection();

		foreach ($this->data as $item) {
			$this->item = &$item;
			$this->subscriber_line = null;
			Billrun_Factory::log('Load line ' . $this->item->get('stamp'), Zend_Log::INFO);
			// @TODO: move the iteration code snippet into function; this is the reason we load the item to class property
			// @TODO: load it by config
			if ($imsi = $this->item->get('imsi')) {
				$key = 'IMSI';
				$value = $imsi;
			} else if ($msisdn = $this->item->get('msisdn')) {
				$key = 'NDC_SN';
				$value = $msisdn;
			} else if ($calling_number = $this->item->get('calling_number')) {
				$key = 'NDC_SN';
				$value = $calling_number;
			} else {
				Billrun_Factory::log('Couldn\'t identify caller for line of stamp ' . $this->item->get('stamp'), Zend_Log::ALERT);
				// log something
				continue;
			}

			$lineStrTime = date(Billrun_Base::base_dateformat, $this->item->get('unified_record_time')->sec);
			$params = array(
				$key => $value,
				'time' => $lineStrTime
			);

			$subscriber->load($params);

			$this->item->collection(Billrun_Factory::db()->linesCollection());
			foreach ($subscriber->getAvailableFields() as $field) {
				$subscriber_field = $subscriber->{$field};
				if (is_null($subscriber_field)) {
					Billrun_Factory::log('Missing subscriber info for line' . $this->item->get('stamp'), Zend_Log::ALERT);
					continue 2;
				}
				$item->set($field, $subscriber_field);
			}

			// TODO not configurable
			$billrun_key = Billrun_Util::getNextChargeKey($this->item->get('unified_record_time')->sec);
			if ($subscribers->query(array('subscriber_id' => $subscriber->subscriber_id, 'billrun_month' => $billrun_key))->count() == 0) { // create empty balance row
				$this->subscriber_line = new Mongodloid_Entity($this->getEmptyBalance($billrun_key, $subscriber->account_id, $subscriber->subscriber_id, $subscriber->plan));
				$this->subscriber_line->collection($subscribers);
			}

			$this->write();

			// remove the link entity
			$this->item = null;
		}
	}

	/**
	 * execute write the calculation output into DB
	 */
	public function write() {
		if (!is_null($this->item)) {
			Billrun_Factory::log('Write calculator customer line ' . $this->item->get('stamp'), Zend_Log::INFO);
			$this->item->save();
		}
		if (!is_null($this->subscriber_line)) {
			Billrun_Factory::log('Adding subscriber ' . $this->item->get('subscriber_id') . ' to subscribers collection', Zend_Log::INFO);
			$this->subscriber_line->save();
		}
	}

	/**
	 * write the calculation into DB
	 */
	protected function updateRow($row) {
		
	}

	protected function getEmptyBalance($billrun_month, $account_id, $subscriber_id, $plan_current) {
		return array(
			'billrun_month' => $billrun_month,
			'account_id' => $account_id,
			'subscriber_id' => $subscriber_id,
			'plan_current' => $plan_current,
			'balance' => array(
				'usage_counters' => array(
					'call' => 0,
					'sms' => 0,
					'data' => 0,
				),
				'curr_charge' => 0,
			),
		);
	}

}
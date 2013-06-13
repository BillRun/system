<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
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
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'customer';

	/**
	 * Array for translating CDR line values to  customer identifing values (finding out thier MSISDN/IMSI numbers)
	 * @var array
	 */
	protected $translateCustomerIdentToAPI = array();

	public function __construct($options = array()) {
		parent::__construct($options);

		if (isset($options['calculator']['customer_identification_translation'])) {
			$this->translateCustomerIdentToAPI = $options['calculator']['customer_identification_translation'];
		}

		$this->subscriber = Billrun_Factory::subscriber();
		$this->balances = Billrun_Factory::db()->balancesCollection();
		$this->plans = Billrun_Factory::db()->plansCollection();
	}

	/**
	 * method to get calculator lines
	 */
	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();

		return $lines->query()
				->in('type', array('nsn', 'ggsn', 'smsc', 'mmsc', 'smpp', 'tap3'))
				->exists('customer_rate')->notEq('customer_rate', FALSE)
				->notExists('subscriber_id')->cursor()->limit($this->limit);
	}

	/**
	 * write the calculation into DB
	 */
	protected function updateRow($row) {
		//Billrun_Factory::log('Load line ' . $row->get('stamp'), Zend_Log::INFO);
		$subscriber = $this->loadSubscriberForLine($row);

		if (!$subscriber || !$subscriber->isValid()) {
			Billrun_Factory::log('Missing subscriber info for line with stamp : ' . $row->get('stamp'), Zend_Log::ALERT);
			return;
		}

		foreach ($subscriber->getAvailableFields() as $field) {
			$subscriber_field = $subscriber->{$field};
			$row[$field] = $subscriber_field;
		}
		$this->addPlanRef($row, $subscriber->plan);
		$this->createSubscriberIfMissing($subscriber, Billrun_Util::getNextChargeKey($row->get('unified_record_time')->sec), $row['plan_ref']);
	}

	/**
	 * Load a subscriber for a given CDR line.
	 * @param type $row
	 * @return type
	 */
	protected function loadSubscriberForLine($row) {

		// @TODO: move the iteration code snippet into function; this is the reason we load the item to class property

		$params = array();
		foreach ($this->translateCustomerIdentToAPI as $key => $toKey) {

			if ($row->get($key)) {
				$params[$toKey['toKey']] = preg_replace($toKey['clearRegex'], '', $row->get($key));
				//$this->subscriberNumber = $params[$toKey['toKey']];
				Billrun_Factory::log("found indetification from {$key} to : " . $toKey['toKey'] . ' with value :' . $params[$toKey['toKey']], Zend_Log::DEBUG);
				break;
			}
		}

		if (count($params) == 0) {
			Billrun_Factory::log('Couldn\'t identify caller for line of stamp ' . $row->get('stamp'), Zend_Log::ALERT);
			return;
		}

		$params['time'] = date(Billrun_Base::base_dateformat, $row->get('unified_record_time')->sec);

		return $this->subscriber->load($params);
	}

	/**
	 * Create a subscriber  entry if none exists. 
	 * @param type $subscriber
	 */
	protected function createSubscriberIfMissing($subscriber, $billrun_key, $plan_ref) {
		if (!Billrun_Model_Subscriber::get($subscriber->subscriber_id, $billrun_key)) {
			Billrun_Model_Subscriber::create($billrun_key, $subscriber->subscriber_id, $plan_ref, $subscriber->account_id);
		}
	}

	/**
	 * Add plan reference to line
	 * @param Mongodloid_Entity $row
	 * @param string $plan
	 */
	protected function addPlanRef($row, $plan) {
		$planObj = Billrun_Factory::plan(array('name' => $plan, 'time' => $row['unified_record_time']->sec));
		if(!$planObj->get('_id')) {
				Billrun_Factory::log("Couldn't get plan for CDR line : {$row['stamp']} with plan $plan", Zend_Log::ALERT);
				return;
		}
		$row['plan_ref'] = $planObj->get('_id')->getMongoID();
	}

}
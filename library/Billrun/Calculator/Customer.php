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
		$this->lines_coll = Billrun_Factory::db()->linesCollection();
	}

	/**
	 * method to get calculator lines
	 */
	protected function getLines() {
		return $this->getQueuedLines(array('type' => array('$in' => array('nsn', 'ggsn', 'smsc', 'mmsc', 'smpp', 'tap3', 'credit'))));
	}

	/**
	 * write the calculation into DB
	 */
	protected function updateRow($row) {

		$row->collection($this->lines_coll);
		//Billrun_Factory::log('Load line ' . $row->get('stamp'), Zend_Log::INFO);
		$subscriber = $this->loadSubscriberForLine($row);

		if (!$subscriber || !$subscriber->isValid()) {
			Billrun_Factory::log('Missing subscriber info for line with stamp : ' . $row->get('stamp'), Zend_Log::ALERT);
			return false;
		}

		foreach ($subscriber->getAvailableFields() as $field) {
			if (is_numeric($subscriber->{$field})) {
				$subscriber->{$field} = intval($subscriber->{$field}); // remove this conversion when Vitali changes the output of the CRM to integers
			}
			$subscriber_field = $subscriber->{$field};
			$row[$field] = $subscriber_field;
		}

		$plan_ref = $this->addPlanRef($row, $subscriber->plan);
		if (is_null($plan_ref)) {
			Billrun_Factory::log('No plan found for subscriber ' . $subscriber->subscriber_id, Zend_Log::ALERT);
			return false;
		}
		$billrun_key = Billrun_Util::getBillrunKey($row->get('unified_record_time')->sec);
		$this->createBalanceIfMissing($subscriber, $billrun_key, $plan_ref);
		return true;
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
	protected function createBalanceIfMissing($subscriber, $billrun_key, $plan_ref) {
		$balance = Billrun_Factory::balance(array('subscriber_id' => $subscriber->subscriber_id, 'billrun_key' => $billrun_key));
		if (!$balance->isValid()) {
			$balance->create($billrun_key, $subscriber, $plan_ref);
		}
	}

	/**
	 * Add plan reference to line
	 * @param Mongodloid_Entity $row
	 * @param string $plan
	 */
	protected function addPlanRef($row, $plan) {
		$planObj = Billrun_Factory::plan(array('name' => $plan, 'time' => $row['unified_record_time']->sec));
		if (!$planObj->get('_id')) {
			Billrun_Factory::log("Couldn't get plan for CDR line : {$row['stamp']} with plan $plan", Zend_Log::ALERT);
			return;
		}
		$row['plan_ref'] = $planObj->createRef();
		return $row->get('plan_ref', true);
	}

	/**
	 * @see Billrun_Calculator::getCalculatorQueueType
	 */
	static protected function getCalculatorQueueType() {
		return self::$type;
	}

	/**
	 * 
	 */
	protected function setCalculatorTag() {
		$queue = Billrun_Factory::db()->queueCollection();
		$calculator_tag = $this->getCalculatorQueueTag();
		foreach ($this->data as $item) {
			$query = array('stamp' => $item['stamp']);
			$update = array('$set' => array($calculator_tag => true));
			if (isset($item['account_id'])) {
				$update['$set']['account_id'] = $item['account_id'];
			}
			$queue->update($query, $update);
		}
	}

	/**
	 * @see Billrun_Calculator::isLineLegitimate
	 */
	protected function isLineLegitimate($line) {
		if (!isset($line['usagev']) || $line['usagev'] !== 0) {
			foreach ($this->translateCustomerIdentToAPI as $key => $toKey) {
				if (isset($line[$key]) && strlen($line[$key])) {
					return (isset($line['customer_rate']) && $line['customer_rate']); //it  depend on customer rate to detect if the line is incoming or outgoing.
				}
			}
		}
		return false;
	}

}
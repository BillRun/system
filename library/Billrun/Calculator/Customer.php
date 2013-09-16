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

	/**
	 *
	 * @var Billrun_Subscriber 
	 */
	protected $subscriber;

	/**
	 * array of Billrun_Subscriber
	 * @var array
	 */
	protected $subscribers;

	/**
	 * Whether or not to use the subscriber bulk API method
	 * @var boolean
	 */
	protected $bulk = true;

	public function __construct($options = array()) {
		parent::__construct($options);

		if (isset($options['calculator']['customer_identification_translation'])) {
			$this->translateCustomerIdentToAPI = $options['calculator']['customer_identification_translation'];
		}
		if (isset($options['calculator']['bulk'])) {
			$this->bulk = $options['calculator']['bulk'];
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

	protected function subscribersByStamp() {
		if (!isset($this->subscribers_by_stamp) || !$this->subscribers_by_stamp) {
			$subs_by_stamp = array();
			foreach ($this->subscribers as $sub) {
				$subs_by_stamp[$sub->getStamp()] = $sub;
			}
			$this->subscribers = $subs_by_stamp;
			$this->subscribers_by_stamp = true;
		}
	}

	/**
	 * write the calculation into DB
	 */
	protected function updateRow($row) {
		$row->collection($this->lines_coll);
		if ($this->bulk) {
			$this->subscribersByStamp();
			$subscriber = isset($this->subscribers[$row['stamp']]) ? $this->subscribers[$row['stamp']] : FALSE;
		} else {
			$subscriber = $this->loadSubscriberForLine($row);
		}
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
	 * 
	 * @param type $queueLines
	 * @return type
	 * @todo consider moving isLineLegitimate to here if possible
	 */
	protected function pullLines($queueLines) {
		$lines = parent::pullLines($queueLines);
		if ($this->bulk) { // load all the subscribers in one call
			$this->subscribers = $this->loadSubscribers($lines);
		}
		return $lines;
	}

	protected function loadSubscribers($rows) {
		$params = array();
		foreach ($rows as $key => $row) {
			$line_params = $this->getIdentityParams($row);
			if (count($line_params) == 0) {
				Billrun_Factory::log('Couldn\'t identify caller for line of stamp ' . $row->get('stamp'), Zend_Log::ALERT);
			} else if ($this->isLineLegitimate($row)) {
				$line_params['time'] = date(Billrun_Base::base_dateformat, $row->get('unified_record_time')->sec);
				$line_params['stamp'] = $row->get('stamp');
				$params[] = $line_params;
			}
		}
		return $this->subscriber->getSubscribersByParams($params);
	}

	/**
	 * Load a subscriber for a given CDR line.
	 * @param type $row
	 * @return type
	 */
	protected function loadSubscriberForLine($row) {
		$params = $this->getIdentityParams($row);

		if (count($params) == 0) {
			Billrun_Factory::log('Couldn\'t identify caller for line of stamp ' . $row->get('stamp'), Zend_Log::ALERT);
			return;
		}

		$params['time'] = date(Billrun_Base::base_dateformat, $row->get('unified_record_time')->sec);
		$params['stamp'] = $row->get('stamp');

		return $this->subscriber->load($params);
	}

	protected function getIdentityParams($row) {
		$params = array();
		foreach ($this->translateCustomerIdentToAPI as $key => $toKey) {
			if ($row->get($key)) {
				$params[$toKey['toKey']] = preg_replace($toKey['clearRegex'], '', $row->get($key));
				//$this->subscriberNumber = $params[$toKey['toKey']];
				Billrun_Factory::log("found identification for row : {$row['stamp']} from {$key} to " . $toKey['toKey'] . ' with value :' . $params[$toKey['toKey']], Zend_Log::DEBUG);
				break;
			}
		}
		return $params;
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
	protected function setCalculatorTag($query = array(), $update = array()) {
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
		if (isset($line['usagev']) && $line['usagev'] !== 0) {
			foreach ($this->translateCustomerIdentToAPI as $key => $toKey) {
				if (isset($line[$key]) && strlen($line[$key])) {
					return (isset($line['customer_rate']) && $line->get('customer_rate', true)); //it  depend on customer rate to detect if the line is incoming or outgoing.
				}
			}
		}
		return false;
	}

}
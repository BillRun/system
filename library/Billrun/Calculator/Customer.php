<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing customer calculator class for ilds records
 *
 * @package  calculator
 * @since    1.0
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
	protected $subscriberSettings = array();
	static protected $time;
	protected $unrecognizedSubscribers = array();

	public function __construct($options = array()) {
		$this->subscriberSettings = $options;
		if (isset($options['calculator']['customer_identification_translation'])) {
			$this->translateCustomerIdentToAPI = $options['calculator']['customer_identification_translation'];
		}
		if (isset($options['calculator']['bulk'])) {
			$this->bulk = $options['calculator']['bulk'];
		}

		$this->subscriber = Billrun_Factory::subscriber();
		$this->plans = Billrun_Factory::db()->plansCollection();
		$this->lines_coll = Billrun_Factory::db()->linesCollection();
		parent::__construct($options);
	}

	/**
	 * method to receive the lines the calculator should take care
	 * 
	 * @return Mongodloid_Cursor Mongo cursor for iteration
	 */
	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();

		$rows = $lines->query(array(
					'source' => 'nrtrde',
					'$or' => array(
						array('aid' => array('$exists' => false)),
						array('sid' => array('$exists' => false)),
						array('plan' => array('$exists' => false)),
					),
					'unified_record_time' => array(
						'$gte' => new MongoDate(strtotime($this->subscriberSettings['calculator']['subscriber']['urt_lower_bound'] . ' ago')),
					),
				))->cursor()->limit($this->subscriberSettings['calculator']['subscriber']['limit']);
		$this->loadSubscribers($rows);
		return $rows;
	}

	protected function getIdentityParams($row) {
		$params = array();
		$customer_identification_translation = $this->translateCustomerIdentToAPI;
		foreach ($customer_identification_translation as $key => $toKey) {
			if (isset($row[$key])) {
				$params[$toKey['toKey']] = preg_replace($toKey['clearRegex'], '', $row[$key]);
				//$this->subscriberNumber = $params[$toKey['toKey']];
				Billrun_Factory::log("found identification for row: {$row['stamp']} from {$key} to " . $toKey['toKey'] . ' with value:' . $params[$toKey['toKey']], Zend_Log::DEBUG);
				break;
			}
		}
		return $params;
	}

	protected function loadSubscribers($rows) {
		$this->subscribers_by_stamp = false;
		$params = array();
		foreach ($rows as $row) {
			$line_params = $this->getIdentityParams($row);
			if (count($line_params) == 0) {
				Billrun_Factory::log('Couldn\'t identify caller for line of stamp ' . $row['stamp'], Zend_Log::ALERT);
			} else {
				$line_params['time'] = date(Billrun_Base::base_dateformat, $row['unified_record_time']->sec);
				$line_params['stamp'] = $row['stamp'];
				$params[] = $line_params;
			}
		}
		$this->subscribers = $this->subscriber->getSubscribersByParams($params, $this->subscriber->getAvailableFields());
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

	public function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorUpdateRow', array($row, $this));
		$row->collection($this->lines_coll);
		if ($this->isBulk()) {
			$this->subscribersByStamp();
			$subscriber = isset($this->subscribers[$row['stamp']]) ? $this->subscribers[$row['stamp']] : FALSE;
		} else {
			$subscriber = $this->loadSubscriberForLine($row);
		}
		if (!$subscriber || !$subscriber->isValid()) {
			$customer_identification_translation = $this->translateCustomerIdentToAPI;
			foreach ($customer_identification_translation as $key => $toKey) {
				if (isset($row[$key]) && strlen($row[$key])) {
					$id = $row[$key];
					break;
				}
			}
			if (!isset($row['subscriber_not_found'])) {
				$row['subscriber_not_found'] = true;
				if (!isset($this->unrecognizedSubscribers[$id])) {
					$msg = "Failed when sending event to subscriber_plan_by_date.rpc.php - sent: " . PHP_EOL . $key . ': ' . $id . PHP_EOL . 'time: ' . date(Billrun_Base::base_dateformat, $row['unified_record_time']->sec) . PHP_EOL . " returned: NULL";
					$this->sendEmailOnFailure($msg);
					$this->sendSmsOnFailure("Failed when sending event to subscriber-plan-by-date.rpc.php, null returned, see email for more details");
					$this->unrecognizedSubscribers[$id] = true;
				}
			}

			Billrun_Factory::log()->log("No subscriber returned" . PHP_EOL . $key . ': ' . $id . PHP_EOL . 'time: ' . date(Billrun_Base::base_dateformat, $row['unified_record_time']->sec), Zend_Log::INFO);
			return false;
		}

		foreach (array_keys($subscriber->getAvailableFields()) as $key) {
			if (is_numeric($subscriber->{$key})) {
				$subscriber->{$key} = intval($subscriber->{$key}); // remove this conversion when Vitali changes the output of the CRM to integers
			}
			$subscriber_field = $subscriber->{$key};
			$row[$key] = $subscriber_field;
			if($key == 'sid') {
				$row['subscriber_id'] = $row[$key];
			}
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculatorUpdateRow', array($row, $this));
		return $row;
	}

	/**
	 * Execute the calculation process
	 */
	public function calc() {
		foreach ($this->data as $item) {
			// update billing line with billrun stamp
			if (!$this->updateRow($item)) {
				Billrun_Factory::log()->log("cannot update billing line", Zend_Log::INFO);
				continue;
			}
		}
	}

	protected function sendEmailOnFailure($msg) {
		return true;
		Billrun_Factory::log()->log($msg, Zend_Log::ALERT);
		$recipients = Billrun_Factory::config()->getConfigValue('fraudAlert.failed_alerts.recipients', array());
		$subject = "Failed Fraud Alert, subscriber not found" . date(Billrun_Base::base_dateformat);
		return Billrun_Util::sendMail($subject, $msg, $recipients);
	}

	protected function sendSmsOnFailure($msg) {
		return true;
		$recipients = Billrun_Factory::config()->getConfigValue('smsAlerts.processing.recipients', array());
		return Billrun_Util::sendSms($msg, $recipients);
	}

	/**
	 * Execute write down the calculation output
	 */
	public function write() {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteData', array('data' => $this->data));
		$lines = Billrun_Factory::db()->linesCollection();
		foreach ($this->data as $item) {
			$newFields = array_intersect_key($item->getRawData(), array('sid' => true, 'aid' => true, 'plan' => true, 'subscriber_not_found' => true));
			if ($newFields) {
				$lines->update(array('stamp' => $item['stamp']), array('$set' => $newFields));
			}
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteData', array('data' => $this->data));
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

		$params['time'] = date(Billrun_Base::base_dateformat, $row->get('urt')->sec);
		$params['stamp'] = $row->get('stamp');

		return $this->subscriber->load($params);
	}

	public function isBulk() {
		return $this->bulk;
	}

	/**
	 * @see Billrun_Calculator::isLineLegitimate
	 */
	public function isLineLegitimate($line) {
		if (isset($line['usagev']) && $line['usagev'] !== 0 && $this->isCustomerable($line)) {
			$customer = $this->isOutgoingCall($line) ? "caller" : "callee";
			if (isset($this->translateCustomerIdentToAPI[$customer])) {
				$customer_identification_translation = $this->translateCustomerIdentToAPI[$customer];
				foreach ($customer_identification_translation as $key => $toKey) {
					if (isset($line[$key]) && strlen($line[$key])) {
						return true;
					}
				}
			}
		}
		return false;
	}

}

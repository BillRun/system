<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator class for records
 *
 * @package  calculator
 * @since    5.0
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
	protected $bulk = false;

	/**
	 * Extra customer fields to be saved by line type
	 * @var array
	 */
	protected $extraData = array();

	/**
	 * Should the mandatory customer fields be overriden if they exist
	 * @var boolean
	 */
	protected $overrideMandatoryFields = TRUE;

	public function __construct($options = array()) {
		parent::__construct($options);

		if (isset($options['calculator']['customer_identification_translation'])) {
			$this->translateCustomerIdentToAPI = $this->getCustomerIdentificationTranslation();
		}
		if (isset($options['calculator']['bulk'])) {
			$this->bulk = $options['calculator']['bulk'];
		}
		if (isset($options['calculator']['extra_data'])) {
			$this->extraData = $options['calculator']['extra_data'];
		}
		if (isset($options['realtime'])) {
			$this->overrideMandatoryFields = !boolval($options['realtime']);
		}
		if (isset($options['calculator']['override_mandatory_fields'])) {
			$this->overrideMandatoryFields = boolval($options['calculator']['override_mandatory_fields']);
		}

		$this->subscriber = Billrun_Factory::subscriber();
		$this->plans = Billrun_Factory::db()->plansCollection();
		$this->lines_coll = Billrun_Factory::db()->linesCollection();
	}

	/**
	 * method to get calculator lines
	 */
	protected function getLines() {
		return $this->getQueuedLines(array());
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

	
	
	public function prepareData($lines) {
		if ($this->isBulk()) {
			$this->loadSubscribers($lines);
		}
	}

	/**
	 * make the  calculation
	 */
	public function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorUpdateRow', array(&$row, $this));
		$row->collection($this->lines_coll);
		if ($this->isBulk()) {
			$this->subscribersByStamp();
			$subscriber = isset($this->subscribers[$row['stamp']]) ? $this->subscribers[$row['stamp']] : FALSE;
		} else {
			if ($this->loadSubscriberForLine($row)) {
				$subscriber = $this->subscriber;
			} else {
				Billrun_Factory::log('Error loading subscriber for row ' . $row->get('stamp'), Zend_Log::NOTICE);
				return false;
			}
		}
		if (!$subscriber || !$subscriber->isValid()) {
			if ($this->isOutgoingCall($row)) {
				Billrun_Factory::log('Missing subscriber info for line with stamp : ' . $row->get('stamp'), Zend_Log::NOTICE);
				return false;
			} else {
				Billrun_Factory::log('Missing subscriber info for line with stamp : ' . $row->get('stamp'), Zend_Log::DEBUG);
				return $row;
			}
		}

		foreach (array_keys($subscriber->getAvailableFields()) as $key) {
			if (is_numeric($subscriber->{$key})) {
				$subscriber->{$key} = intval($subscriber->{$key}); // remove this conversion when the CRM output contains integers
			}
			$subscriber_field = $subscriber->{$key};
			if (is_array($row[$key]) && (is_array($subscriber_field) || is_null($subscriber_field))) {
				$row[$key] = array_merge($row[$key], is_null($subscriber_field) ? array() : $subscriber_field);
			} else {
				$row[$key] = $subscriber_field;
			}
		}
		
		foreach (array_keys($subscriber->getCustomerExtraData())as $key) {
			if ($this->isExtraDataRelevant($row, $key)) {
				$subscriber_field = $subscriber->{$key};
				if (is_array($row[$key]) && (is_array($subscriber_field) || is_null($subscriber_field))) { // if existing value is array and in input value is array let's do merge
					$row[$key] = array_merge($row[$key], is_null($subscriber_field) ? array() : $subscriber_field);
				} else {
					$row[$key] = $subscriber_field;
				}
			}
		}
		$row['subscriber_lang'] = $subscriber->language;

		$plan = Billrun_Factory::plan(array('name' => $row['plan'], 'time' => $row['urt']->sec));
		$plan_ref = $plan->createRef();
		if (is_null($plan_ref)) {
			Billrun_Factory::log('No plan found for subscriber ' . $row['sid'], Zend_Log::ALERT);
			$row['usagev'] = 0;
			$row['apr'] = 0;
			return false;
		}
		$row['plan_ref'] = $plan_ref;

		Billrun_Factory::dispatcher()->trigger('afterCalculatorUpdateRow', array(&$row, $this));
		return $row;
	}

	/**
	 * Returns whether to save the extra data field to the line or not
	 * @param Mongodloid_Entity $line
	 * @param string $field
	 * @return boolean
	 */
	public function isExtraDataRelevant(&$line, $field) {
		if (empty($this->extraData[$line['type']]) || !in_array($field, $this->extraData[$line['type']])) {
			return false;
		}
		if ($line['type'] == 'ggsn') {
			if (is_array($line)) {
				$arate = $line['arate'];
			} else {
				$arate = $line->get('arate', true);
			}
			return isset($this->intlGgsnRates[strval($arate['$id'])]);
		}
		return true;
	}

	/**
	 * Override parent calculator to save changes with update (not save)
	 */
	public function writeLine($line, $dataKey) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteLine', array('data' => $line, 'calculator' => $this));

		$save = array(
			'$set' => array(),
		);
		$saveProperties = $this->getPossiblyUpdatedFields();
		foreach ($saveProperties as $p) {
			if (!is_null($val = $line->get($p, true))) {
				$save['$set'][$p] = $val;
			}
		}

		if (count($save['$set'])) {
			$where = array('stamp' => $line['stamp']);
			Billrun_Factory::db()->linesCollection()->update($where, $save);
		}

		if (!isset($line['usagev']) || $line['usagev'] === 0) {
			$this->removeLineFromQueue($line);
			unset($this->data[$dataKey]);
		}

		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteLine', array('data' => $line, 'calculator' => $this));
	}

	public function getPossiblyUpdatedFields() {
		return array_merge($this->getCustomerPossiblyUpdatedFields(), array('granted_return_code', 'usagev'));
	}

	public function getCustomerPossiblyUpdatedFields() {
		$subscriber = Billrun_Factory::subscriber();
		$availableFileds = array_keys($subscriber->getAvailableFields());
		$customerExtraData = array_keys($subscriber->getCustomerExtraData());
		return array_merge($availableFileds, $customerExtraData, array('subscriber_lang'));
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
			$this->loadSubscribers($lines);
		}
		return $lines;
	}

	public function isBulk() {
		return $this->bulk;
	}

	public function loadSubscribers($rows) {
		$this->subscribers_by_stamp = false;
		$params = array();
		$subscriber_extra_data = array_keys($this->subscriber->getCustomerExtraData());
		foreach ($rows as $row) {
			if ($this->isLineLegitimate($row)) {
				$line_params = $this->getIdentityParams($row);
				if (count($line_params) == 0) {
					Billrun_Factory::log('Couldn\'t identify caller for line of stamp ' . $row['stamp'], Zend_Log::ALERT);
				} else {
					$line_params['time'] = date(Billrun_Base::base_datetimeformat, $row['urt']->sec);
					$line_params['stamp'] = $row['stamp'];
					$line_params['EXTRAS'] = 0;
					foreach ($subscriber_extra_data as $key) {
						if ($this->isExtraDataRelevant($row, $key)) {
							$line_params['EXTRAS'] = 1;
							break;
						}
					}
					$params[] = $line_params;
				}
			}
		}
		$this->subscribers = $this->subscriber->getSubscribersByParams($params, $this->subscriber->getAvailableFields());
	}

	/**
	 * Load a subscriber for a given CDR line.
	 * @param type $row
	 * @return type
	 */
	protected function loadSubscriberForLine($row) {
		$params = $this->getIdentityParams($row);

		if (count($params) == 0) {
			Billrun_Factory::log('Couldn\'t identify subscriber for line of stamp ' . $row->get('stamp'), Zend_Log::ALERT);
			return;
		}

		$params['time'] = date(Billrun_Base::base_datetimeformat, $row->get('urt')->sec);
		$params['stamp'] = $row->get('stamp');

		return $this->subscriber->load($params);
	}
	
	protected function getIdentityParams($row) {
		$params = array();
		$customer_identification_translation = Billrun_Util::getFieldVal($this->translateCustomerIdentToAPI[$row['type']], array());
		foreach ($customer_identification_translation as $translationRules) {
			if (!empty($translationRules['conditions'])) {
				foreach ($translationRules['conditions'] as $condition) {
					if (!preg_match($condition['regex'], $row[$condition['field']])) {
						continue 2;
					}
				}
			}
			$key = $translationRules['src_key'];
			if (isset($row[$key])) {
				if (isset($translationRules['clear_regex'])) {
					$params[$translationRules['target_key']] = preg_replace($translationRules['clear_regex'], '', $row[$key]);
				} else {
					if ($translationRules['target_key'] === 'msisdn') {
						$params[$translationRules['target_key']] = Billrun_Util::msisdn($row[$key]);
					} else {
						$params[$translationRules['target_key']] = $row[$key];
					}
				}
				Billrun_Factory::log("found identification for row: {$row['stamp']} from {$key} to " . $translationRules['target_key'] . ' with value: ' . $params[$translationRules['target_key']], Zend_Log::DEBUG);
				break;
			}
			else {
				Billrun_Factory::log('Customer calculator missing field ' . $key . ' for line with stamp ' . $row['stamp'], Zend_Log::ALERT);
			}
		}
		return $params;
	}

	/**
	 * @see Billrun_Calculator::getCalculatorQueueType
	 */
	public function getCalculatorQueueType() {
		return self::$type;
	}

	/**
	 * 
	 * @param type $query
	 * @param type $update
	 */
	protected function setCalculatorTag($query = array(), $update = array()) {
		$queue = Billrun_Factory::db()->queueCollection();
		$calculator_tag = $this->getCalculatorQueueType();
		$advance_stamps = array();
		foreach ($this->lines as $stamp => $item) {
			if (!isset($this->data[$stamp]['aid'])) {
				$advance_stamps[] = $stamp;
			} else {
				$query = array('stamp' => $stamp);
				$update = array('$set' => array('calc_name' => $calculator_tag, 'calc_time' => false, 'aid' => $this->data[$stamp]['aid'], 'sid' => $this->data[$stamp]['sid']));
				$queue->update($query, $update);
			}
		}

		if (!empty($advance_stamps)) {
			$query = array('stamp' => array('$in' => $advance_stamps));
			$update = array('$set' => array('calc_name' => $calculator_tag, 'calc_time' => false));
			$queue->update($query, $update, array('multiple' => true));
		}
	}

	/**
	 * @see Billrun_Calculator::isLineLegitimate
	 */
	public function isLineLegitimate($line) {
//		if ($this->isCustomerable($line)) {
//			if (!$this->overrideMandatoryFields) {
//				$validSubscriber = TRUE;
//				foreach ($this->subscriber->getAvailableFields() as $requiredField) {
//					if (!isset($line[$requiredField]) || is_null($line[$requiredField])) {
//						$validSubscriber = FALSE;
//						break;
//					}
//				}
//				if ($validSubscriber) {
//					return FALSE;
//				}
//			}
//			$customer = $this->isOutgoingCall($line) ? "caller" : "callee";
//			if (isset($this->translateCustomerIdentToAPI[$customer])) {
//				$customer_identification_translation = $this->translateCustomerIdentToAPI[$customer];
//				foreach ($customer_identification_translation as $key => $toKey) {
//					if (isset($line[$key]) && strlen($line[$key])) {
//						return true;
//					}
//				}
//			}
//		}
//		return false;
		return true;
	}

	protected function isCustomerable($line) {
		if ($line['type'] == 'nsn') {
			$record_type = $line['record_type'];
			if ($record_type == '11' || $record_type == '12') {
				$relevant_cg = $record_type == '11' ? $line['in_circuit_group'] : $line['out_circuit_group'];
				if (!in_array($relevant_cg, Billrun_Util::getRoamingCircuitGroups())) {
					return false;
				}
				if ($record_type == '11' && in_array($line['out_circuit_group'], array('3060', '3061'))) {
					return false;
				}
				// what about IN direction (3060/3061)?
			} else if (!in_array($record_type, array('01', '02'))) {
				return false;
			}
		}
		return true;
	}

	/**
	 * It is assumed that the line is customerable
	 * @param type $line
	 * @return boolean
	 */
	protected function isOutgoingCall($line) {
		$outgoing = true;
		if ($line['type'] == 'nsn') {
			$outgoing = in_array($line['record_type'], array('01', '11'));
		}
		if (in_array($line['usaget'], Billrun_Factory::config()->getConfigValue('realtimeevent.incomingCallUsageTypes', array()))) {
			return false;
		}
		return $outgoing;
	}

	protected function loadIntlGgsnRates() {
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$query = array(
			'params.sgsn_addresses' => array(
				'$exists' => TRUE,
			),
			'key' => array(
				'$ne' => 'INTERNET_BILL_BY_VOLUME',
			),
		);
		$rates = $rates_coll->query($query)->cursor();
		foreach ($rates as $rate) {
			$this->intlGgsnRates[strval($rate->getId())] = $rate;
		}
	}
	
	protected function getCustomerIdentificationTranslation() {
		$customerIdentificationTranslation = array();
		foreach (Billrun_Factory::config()->getConfigValue('file_types', array()) as $fileSettings) {
			if (!empty($fileSettings['customer_identification_fields'])) {
				$customerIdentificationTranslation[$fileSettings['file_type']] = $fileSettings['customer_identification_fields'];
			}
		}
		return $customerIdentificationTranslation;
	}

}

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
	public function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteRow', array($row, $this));
		$row->collection($this->lines_coll);
		if ($this->isBulk()) {
			$this->subscribersByStamp();
			$subscriber = isset($this->subscribers[$row['stamp']]) ? $this->subscribers[$row['stamp']] : FALSE;
		} else {
			$subscriber = $this->loadSubscriberForLine($row);
		}
		if (!$subscriber || !$subscriber->isValid()) {
			Billrun_Factory::log('Missing subscriber info for line with stamp : ' . $row->get('stamp'), Zend_Log::ALERT);
			return false;
		}

		foreach (array_keys($subscriber->getAvailableFields()) as $key) {
			if (is_numeric($subscriber->{$key})) {
				$subscriber->{$key} = intval($subscriber->{$key}); // remove this conversion when Vitali changes the output of the CRM to integers
			}
			$subscriber_field = $subscriber->{$key};
			$row[$key] = $subscriber_field;
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteRow', array($row, $this));
		return true;
	}

	/**
	 * Override parent calculator to save changes with update (not save)
	 */
	public function writeLine($line, $dataKey) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteLine', array('data' => $line));
		$save = array();
		$saveProperties = array_keys(Billrun_Factory::subscriber()->getAvailableFields());
		foreach ($saveProperties as $p) {
			if (!is_null($val = $line->get($p, true))) {
				$save['$set'][$p] = $val;
			}
		}
		$where = array('stamp' => $line['stamp']);
		Billrun_Factory::db()->linesCollection()->update($where, $save);
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteLine', array('data' => $line));
		if (!isset($line['usagev']) || $line['usagev'] === 0) {
			$this->removeLineFromQueue($line);
			unset($this->data[$dataKey]);
		}
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
		foreach ($rows as $row) {
			if ($this->isLineLegitimate($row)) {
				$line_params = $this->getIdentityParams($row);
				if (count($line_params) == 0) {
					Billrun_Factory::log('Couldn\'t identify caller for line of stamp ' . $row['stamp'], Zend_Log::ALERT);
				} else {
					$line_params['time'] = date(Billrun_Base::base_dateformat, $row['urt']->sec);
					$line_params['stamp'] = $row['stamp'];
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
			Billrun_Factory::log('Couldn\'t identify caller for line of stamp ' . $row->get('stamp'), Zend_Log::ALERT);
			return;
		}

		$params['time'] = date(Billrun_Base::base_dateformat, $row->get('urt')->sec);
		$params['stamp'] = $row->get('stamp');

		return $this->subscriber->load($params);
	}

	protected function getIdentityParams($row) {
		$params = array();
		foreach ($this->translateCustomerIdentToAPI as $key => $toKey) {
			if (isset($row[$key])) {
				$params[$toKey['toKey']] = preg_replace($toKey['clearRegex'], '', $row[$key]);
				//$this->subscriberNumber = $params[$toKey['toKey']];
				Billrun_Factory::log("found identification for row : {$row['stamp']} from {$key} to " . $toKey['toKey'] . ' with value :' . $params[$toKey['toKey']], Zend_Log::DEBUG);
				break;
			}
		}
		return $params;
	}

	/**
	 * @see Billrun_Calculator::getCalculatorQueueType
	 */
	static protected function getCalculatorQueueType() {
		return self::$type;
	}

	/**
	 * 
	 * @param type $query
	 * @param type $update
	 */
	protected function setCalculatorTag($query = array(), $update = array()) {
		$queue = Billrun_Factory::db()->queueCollection();
		$calculator_tag = static::getCalculatorQueueType();
		$advance_stamps = array();
		foreach ($this->lines as $stamp => $item) {
			if (!isset($item['aid'])) {
				$advance_stamps[] = $stamp;
			} else {
				$query = array('stamp' => $stamp);
				$update = array('$set' => array('calc_name' => $calculator_tag, 'calc_time' => false, 'aid' => $item['aid']));
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
		if (isset($line['usagev']) && $line['usagev'] !== 0) {
			foreach ($this->translateCustomerIdentToAPI as $key => $toKey) {
				if (isset($line[$key]) && strlen($line[$key])) {
					if (is_array($line)) {
						$arate = $line['arate'];
					} else {
						$arate = $line->get('arate', true);
					}
//					$arate = $line['arate'];
					return (isset($arate) && $arate); //it  depend on customer rate to detect if the line is incoming or outgoing.
				}
			}
		}
		return false;
	}

}

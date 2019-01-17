<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
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

	/**
	 * Extra customer fields to be saved by line type
	 * @var array
	 */
	protected $extraData = array();

	/**
	 * all international ggsn rates
	 * @var array
	 */
	protected $intlGgsnRates = array();

	/**
	 *
	 * @var type 
	 */
	protected $ratesModel;

	public function __construct($options = array()) {
		parent::__construct($options);

		if (isset($options['calculator']['customer_identification_translation'])) {
			$this->translateCustomerIdentToAPI = $options['calculator']['customer_identification_translation'];
		}
		if (isset($options['calculator']['bulk'])) {
			$this->bulk = $options['calculator']['bulk'];
		}
		if (isset($options['calculator']['extra_data'])) {
			$this->extraData = $options['calculator']['extra_data'];
		}

		$this->subscriber = Billrun_Factory::subscriber();
		$this->plans = Billrun_Factory::db()->plansCollection();
		$this->lines_coll = Billrun_Factory::db()->linesCollection();

		$this->loadIntlGgsnRates();
		$this->ratesModel = new RatesModel();
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
	 * make the  calculation
	 */
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
			if ($subscriber->isPrepaidAccount()) {
				$row['prepaid'] = $subscriber->{'prepaid'};
				Billrun_Factory::log('Skipping prepaid line ' . $row->get('stamp'), Zend_Log::INFO);
				return $row;
			}
			if ($this->isOutgoingCallOrSms($row)) {
				Billrun_Factory::log('Missing subscriber info for line with stamp : ' . $row->get('stamp'), Zend_Log::NOTICE);
				return false;
			} else {
				Billrun_Factory::log('Missing subscriber info for line with stamp : ' . $row->get('stamp'), Zend_Log::DEBUG);
				return $row;
			}
		}

		foreach (array_keys($subscriber->getAvailableFields()) as $key) {
			if (is_numeric($subscriber->{$key})) {
				$subscriber->{$key} = intval($subscriber->{$key}); // remove this conversion when Vitali changes the output of the CRM to integers
			}
			$subscriber_field = $subscriber->{$key};
			$row[$key] = $subscriber_field;
		}
		foreach (array_keys($subscriber->getCustomerExtraData())as $key) {
			if ($this->isExtraDataRelevant($row, $key)) {
				$subscriber_field = $subscriber->{$key};
				$row[$key] = $subscriber_field;
				if ($key == 'last_vlr') { // also it's possible to add alpha3 only if daily_ird_plan is true
					if ($subscriber->{$key}) {
						$rate = $this->ratesModel->getRateByVLR($subscriber->{$key});
						if ($rate) {
							$row['alpha3'] = $rate['alpha3'];
						}
					}
				}
			}
		}
		foreach (array_keys($subscriber->getCustomerOpionalData()) as $key) {
			$subscriber_field = $subscriber->{$key};
			$row[$key] = $subscriber_field;
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculatorUpdateRow', array($row, $this));
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
		$saveProperties = array_merge(array('last_vlr', 'alpha3'), array_keys(Billrun_Factory::subscriber()->getAvailableFields()), array_keys(Billrun_Factory::subscriber()->getCustomerExtraData()), array_keys(Billrun_Factory::subscriber()->getCustomerOpionalData()));
		foreach ($saveProperties as $p) {
			if (!is_null($val = $line->get($p, true))) {
				$save['$set'][$p] = $val;
			}
		}

		if (count($save['$set'])) {
			$where = array('stamp' => $line['stamp']);
			Billrun_Factory::db()->linesCollection()->update($where, $save);
			Billrun_Factory::db()->queueCollection()->update($where, $save);
		}

		if (!isset($line['usagev']) || $line['usagev'] === 0) {
			$this->garbageQueueLines[] = $line['stamp'];
			unset($this->data[$dataKey]);
		}
		
		if (!empty($line['prepaid'])) {
			$this->garbageQueueLines[] = $line['stamp'];
		}

		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteLine', array('data' => $line, 'calculator' => $this));
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
					$line_params['time'] = date(Billrun_Base::base_dateformat, $row['urt']->sec);
					$line_params['stamp'] = $row['stamp'];
					$line_params['EXTRAS'] = 0;
					foreach ($subscriber_extra_data as $key) {
						if ($this->isExtraDataRelevant($row, $key)) {
							$line_params['EXTRAS'] = 1;
							break;
						}
					}
					if (($row['type'] == 'tap3' && $row['usaget'] != 'sms') || ( $row['type'] == 'ggsn' && $line_params['EXTRAS'] )	|| isset($row['roaming'])) {
						$line_params['IRP'] = 1;
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
			Billrun_Factory::log('Couldn\'t identify caller for line of stamp ' . $row->get('stamp'), Zend_Log::ALERT);
			return;
		}

		$params['time'] = date(Billrun_Base::base_dateformat, $row->get('urt')->sec);
		$params['stamp'] = $row->get('stamp');

		return $this->subscriber->load($params);
	}

	protected function getIdentityParams($row) {
		$params = array();
		$customer = $this->isOutgoingCallOrSms($row) ? "caller" : "callee";
		$customer_identification_translation = $this->translateCustomerIdentToAPI[$customer];
		foreach ($customer_identification_translation as $key => $toKey) {
			if (!empty($row[$key])) {
				$params[$toKey['toKey']] = preg_replace($toKey['clearRegex'], '', $row[$key]);
				//$this->subscriberNumber = $params[$toKey['toKey']];
				Billrun_Factory::log("found identification for row: {$row['stamp']} from {$key} to " . $toKey['toKey'] . ' with value:' . $params[$toKey['toKey']], Zend_Log::DEBUG);
				break;
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
		if (is_array($line)) {
			if (isset($line['arate'])){
				$arate = $this->lines_coll->getRef($line['arate']);
			}
			else{
				$arate = false;
			}
		} else { 
			$line->collection(Billrun_Factory::db()->linesCollection());
			$arate = $line->get('arate', false);
		}
		if (!empty($arate['skip_calc']) && in_array(self::$type, $arate['skip_calc'])) {
			return false;
		}
		if (isset($line['usagev']) && $line['usagev'] !== 0 && $this->isCustomerable($line)) {
			$customer = $this->isOutgoingCallOrSms($line) ? "caller" : "callee";
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

	protected function isCustomerable($line) {
		if ($line['type'] == 'nsn') {
			$record_type = $line['record_type'];
			if (in_array($record_type, array('11','30','12','31')) ){
				$relevant_cg = in_array($record_type, array('11','30')) ? $line['in_circuit_group'] : $line['out_circuit_group'];
				if (!in_array($relevant_cg, Billrun_Util::getRoamingCircuitGroups())) {
					return false;
				}
				if (in_array($record_type, array('11','30')) && in_array($line['out_circuit_group'], array('3060', '3061'))) {
					return false;
				}
				// what about GOLAN IN direction (3060/3061)?
			} else if (!in_array($record_type, array('01', '02'))) {
				return false;
			}
		} else if ($line['type'] == 'smsc') {
			$record_type = $line['record_type'];
			if ($record_type == '4') {
				if ($line['org_protocol'] != '0') {
					return false;
				}
			}
			if (!in_array($record_type, ['1','2','4'])){
				return false;
			}	
		} else {
			if (is_array($line)) {
				$arate = $line['arate'];
			} else {
				$arate = $line->get('arate', true);
			}
			return (isset($arate) && $arate); // for non-nsn records we currently identify only outgoing usage, based on arate.
		}
		return true;
	}

	/**
	 * It is assumed that the line is customerable
	 * @param type $line
	 * @return boolean
	 */
	protected function isOutgoingCallOrSms($line) {
		$outgoing = true;
		if ($line['type'] == 'nsn') {
			$outgoing = in_array($line['record_type'], array('01', '11','30'));
		}
		if ($line['type'] == 'smsc') {
			$outgoing = in_array($line['record_type'], array('2'));
			if (($line['dest_protocol'] == 3) && $line['arate'] === false){
				$outgoing = false;
			}
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

}

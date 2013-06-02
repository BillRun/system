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
class Billrun_Calculator_Customer extends Billrun_Calculator_Base_Rate {

	/**
	 * the item we are running on
	 * 
	 * @var Mongo Entity
	 */
	protected $item;

	/**
	 *
	 * @var type 
	 */
	protected $subscriberNumber;

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'customer';

	public function __construct($options = array()) {
		parent::__construct($options);

		$this->subscriber = Billrun_Factory::subscriber();
		$this->subscribers = Billrun_Factory::db()->subscribersCollection();
	}

	/**
	 * method to get calculator lines
	 */
	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();

		return $lines->query()
				->in('type', array('nsn', 'ggsn', 'smsc', 'mmsc', 'smpp'))
				->exists('customer_rate')
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
		$this->createSubscriberIfMissing($subscriber, Billrun_Util::getNextChargeKey($row->get('unified_record_time')->sec));
	}

	/**
	 * load a subscriber for a given CDR line.
	 * @param type $row
	 * @return type
	 */
	protected function loadSubscriberForLine($row) {

		// @TODO: move the iteration code snippet into function; this is the reason we load the item to class property
		// @TODO: load it by config
		// 
		// translate the customer identifing fields so they can used by the CRM API.
		$translateCustomerIdentToAPI = array(
			'imsi' => array('toKey' => 'IMSI', 'clearRegex' => '//'),
			'msisdn' => array('toKey' => 'NDC_SN', 'clearRegex' => '/^0*\+{0,1}972/'),
			'calling_number' => array('toKey' => 'NDC_SN', 'clearRegex' => '/^0*\+{0,1}972/'),
		);
		$params = array();

		foreach ($translateCustomerIdentToAPI as $key => $toKey) {
			if (isset($row[$key])) {
				$params[$toKey['toKey']] = preg_replace($toKey['clearRegex'], '', $row[$key]);
				$this->subscriberNumber = $params[$toKey['toKey']];
				Billrun_Factory::log('found indetification of : ' . $toKey['toKey'] . ' with value :' . $params[$toKey['toKey']], Zend_Log::DEBUG);
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
	 * Create a subscriber  entery if none exists. 
	 * (TODO   may move this to the Billrun_Subscriber class?)
	 * @param type $subscriber
	 */
	protected function createSubscriberIfMissing($subscriber, $billrun_key) {
		if (!Billrun_Model_Subscriber::get($subscriber->subscriber_id, $billrun_key)) {
			Billrun_Model_Subscriber::create($billrun_key, $subscriber->subscriber_id, $subscriber->plan, $subscriber->account_id);
		}
	}

}
<?php

/**
 * @category   Application
 * @package    Helpers
 * @subpackage Generator
 * @copyright  Copyright (C) 2013 S.D.O.C. LTD. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

/**
 * Balance generator
 *
 * @package    Generator
 * @subpackage Balance
 * @since      1.0
 */
class Generator_Balance extends Generator_Golan {

	/**
	 * Account for which to get the current balance
	 * @var int 
	 */
	protected $account_id = null;

	/**
	 * subscribers for whom to output lines (0 means all, empty means none)
	 * @var array subscriber ids
	 */
	protected $subscribers = array();

	/**
	 *
	 * @var array the updated account data received from the CRM
	 */
	protected $account_data = array();

	/**
	 * the balance date
	 * @var string a formatted date string
	 */
	protected $date = null;

	public function __construct($options) {
		parent::__construct($options);
		self::$type = 'balance';
		if (isset($options['account_id']) && $options['account_id']) {
			$this->setAccountId($options['account_id']);
		}
		if (isset($options['subscribers']) && $options['subscribers']) {
			$this->setSubscribers($options['subscribers']);
		}
	}

	public function load() {
		$billrun = Billrun_Billrun::getLastOpenBillrun($this->account_id);
		$this->date = date(Billrun_Base::base_dateformat);
		$subscriber = Billrun_Factory::subscriber();
		$this->account_data = current($subscriber->getList(2, 1, $this->date, $this->account_id));

		foreach ($this->account_data as $subscriber) {
			if (!$billrun->exists($subscriber->subscriber_id)) {
				$billrun->addSubscriber($subscriber->subscriber_id);
			}
		}

		$this->data = $billrun->getRawData();
	}

	public function generate() {
		return $this->getXML($this->data);
	}

	protected function setAccountId($account_id) {
		$this->account_id = intval($account_id);
	}

	protected function setSubscribers($subscribers) {
		$this->subscribers = $subscribers;
	}

	/**
	 * 
	 * @param array $subscriber subscriber entry from billrun collection
	 * @return array
	 */
	protected function getFlatCosts($subscriber) {
		$subscriber_id = $subscriber['sub_id'];
		$plan_name = $this->getPlanName($subscriber);
		if (!$plan_name) {
			//@error
			return;
		}
		$planObj = Billrun_Factory::plan(array('name' => $plan_name, 'time' => strtotime($this->date)));
		if (!$planObj->get('_id')) {
			Billrun_Factory::log("Couldn't get plan $plan_name data", Zend_Log::ALERT);
			return;
		}
		$plan_price = $planObj->get('price');
		return array('vatable' => $plan_price, 'vat_free' => 0);
	}

	protected function billingLinesNeeded($subscriber_id) {
		return in_array($subscriber_id, $this->subscribers) || in_array(0, $this->subscribers);
	}

	/**
	 * 
	 * @param array $subscriber subscriber entry from billrun collection
	 */
	protected function getPlanName($subscriber) {
		$plan_name = false;
		foreach ($this->account_data as $sub) {
			if ($sub->subscriber_id == $subscriber['sub_id']) {
				$plan_name = $sub->plan;
				break;
			}
		}
		return $plan_name;
	}

}
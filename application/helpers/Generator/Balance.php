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
class Generator_Balance extends Generator_Golanxml {

	/**
	 * Account for which to get the current balance
	 * @var int 
	 */
	protected $aid = null;

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
		$options['auto_create_dir'] = false;
		parent::__construct($options);
		self::$type = 'balance';
		if (isset($options['aid']) && $options['aid']) {
			$this->setAccountId($options['aid']);
		}
		if (isset($options['subscribers']) && $options['subscribers']) {
			$this->setSubscribers($options['subscribers']);
		}
		$this->now = time();
	}

	public function load() {
		$this->date = date(Billrun_Base::base_dateformat, $this->now);
		$subscriber = Billrun_Factory::subscriber();
		$this->account_data = array();
		$res = $subscriber->getList(0, 1, $this->date, $this->aid);
		if (!empty($res)) {
			$this->account_data = current($res);
		}
		$previous_billrun_key = Billrun_Util::getPreviousBillrunKey($this->stamp);
		if (Billrun_Billrun::exists($this->aid, $previous_billrun_key)) {
			$start_time = 0; // maybe some lines are late (e.g. tap3)
		} else {
			$start_time = Billrun_Util::getStartTime($this->stamp); // to avoid getting lines of previous billruns
		}
		$billrun_params = array(
			'aid' => $this->aid,
			'billrun_key' => $this->stamp,
			'autoload' => false,
		);
		$billrun = Billrun_Factory::billrun($billrun_params);
		$flat_lines = array();
		foreach ($this->account_data as $subscriber) {
			if ($billrun->subscriberExists($subscriber->sid)) {
				Billrun_Factory::log()->log("Billrun " . $this->stamp . " already exists for subscriber " . $subscriber->sid, Zend_Log::ALERT);
				continue;
			}
			$next_plan_name = $subscriber->getNextPlanName();
			if (is_null($next_plan_name) || $next_plan_name == "NULL") {
				$subscriber_status = "closed";
			} else {
				$subscriber_status = "open";
				$flat_lines[] = new Mongodloid_Entity($subscriber->getFlatEntry($this->stamp));
			}
			$billrun->addSubscriber($subscriber, $subscriber_status);
		}
		$this->lines = $billrun->addLines($start_time, $flat_lines);

		$this->data = $billrun->getRawData();
	}

	public function generate() {
		$this->writer->openURI('php://output'); 
		return $this->writeXML($this->data, $this->lines);
	}

	protected function setAccountId($aid) {
		$this->aid = intval($aid);
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
		$plan_name = $this->getNextPlanName($subscriber);
		if (!$plan_name) {
			//@error
			return array();
		}
		$planObj = Billrun_Factory::plan(array('name' => $plan_name, 'time' => Billrun_Util::getStartTime(Billrun_Util::getFollowingBillrunKey($this->stamp))));
		if (!$planObj->get('_id')) {
			Billrun_Factory::log("Couldn't get plan $plan_name data", Zend_Log::ALERT);
			return array();
		}
		$plan_price = $planObj->get('price');
		return array('vatable' => $plan_price, 'vat_free' => 0);
	}

	protected function billingLinesNeeded($sid) {
		return in_array($sid, $this->subscribers) || in_array(0, $this->subscribers);
	}

	/**
	 * 
	 * @param array $subscriber subscriber entry from billrun collection
	 */
	protected function getNextPlanName($subscriber) {
		$plan_name = false;
		foreach ($this->account_data as $sub) {
			if ($sub->sid == $subscriber['sid']) {
				$next_plan = $sub->getNextPlanName();
				if (!is_null($next_plan) && $next_plan != "NULL") {
					$plan_name = $next_plan;
				}
				break;
			}
		}
		return $plan_name;
	}

	protected function getInvoiceId($row) {
		return '00000000000';
	}

}

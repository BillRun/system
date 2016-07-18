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
		$subscriber->setBillrunKey($this->stamp);
		$this->account_data = array();
		$res = $subscriber->getList(0, 1, $this->date, $this->aid);
		if (!empty($res)) {
			$this->account_data = current($res);
		}

		$billrun_params = array(
			'aid' => $this->aid,
			'billrun_key' => $this->stamp,
			'autoload' => false,
		);
		$billrun = Billrun_Factory::billrun($billrun_params);
		$manual_lines = array();
		$deactivated_subscribers = array();
		foreach ($this->account_data as $subscriber) {
			if (!Billrun_Factory::db()->rebalance_queueCollection()->query(array('sid' => $subscriber->sid), array('sid' => 1))
					->cursor()->current()->isEmpty()) {
				$subscriber_status = "REBALANCE";
				$billrun->addSubscriber($subscriber, $subscriber_status);
				continue;
			}

			if ($billrun->subscriberExists($subscriber->sid)) {
				Billrun_Factory::log()->log("Billrun " . $this->stamp . " already exists for subscriber " . $subscriber->sid, Zend_Log::ALERT);
				continue;
			}
			$next_plan_name = $subscriber->getNextPlanName();
			if (is_null($next_plan_name) || $next_plan_name == "NULL") {
				$subscriber_status = "closed";
				$current_plan_name = $subscriber->getCurrentPlanName();
				if (is_null($current_plan_name) || $current_plan_name == "NULL") {

					$deactivated_subscribers[] = array("sid" => $subscriber->sid);
				}
			} 
			$plan_to_charge = $subscriber->chargeByPlan();
			if (!is_null($plan_to_charge) && $plan_to_charge != "NULL") {
				$subscriber_status = "open";
				$subscriber->setBillrunKey($this->stamp);
				$flat_entry = $subscriber->getFlatEntry($this->stamp, true);
				$manual_lines = array_merge($manual_lines, array($flat_entry['stamp'] => $flat_entry));
			}
			$manual_lines = array_merge($manual_lines, $subscriber->getCredits($this->stamp, true), $subscriber->getServices($this->stamp, true));
			$billrun->addSubscriber($subscriber, $subscriber_status);
		}
//		print_R($manual_lines);die;
		$this->lines = $billrun->addLines($manual_lines, $deactivated_subscribers);
		$billrun->filter_disconected_subscribers($deactivated_subscribers);

		$this->data = $billrun->getRawData();
	}

	public function generate() {
		if ($this->buffer) {
			$this->writer->openMemory();
		} else {
			$this->writer->openURI('php://output');
		}

		$this->writeXML($this->data, $this->lines);
		if ($this->buffer) {
			return $this->writer->outputMemory();
		}
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

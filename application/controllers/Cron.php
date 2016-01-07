<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing cron controller class
 * Used for is alive checks
 * 
 * @package  Controller
 * @since    2.8
 */
class CronController extends Yaf_Controller_Abstract {

	protected $mailer;
	protected $smser;

	public function init() {
		Billrun_Factory::log("BillRun Cron is running", Zend_Log::INFO);
		$this->smser = Billrun_Factory::smser();
		$this->mailer = Billrun_Factory::mailer();
	}

	/**
	 * main action to do basic tests
	 * 
	 * @return void
	 */
	public function indexAction() {
		// do nothing
	}

	public function receiveAction() {
		Billrun_Factory::log("Check receive", Zend_Log::INFO);
		$alerts = $this->locate(('receive'));
		if (!empty($alerts)) {
			$this->sendAlerts('receive', $alerts);
		}
	}

	public function processAction() {
		Billrun_Factory::log("Check process", Zend_Log::INFO);
		$alerts = $this->locate(('process'));
		if (!empty($alerts)) {
			$this->sendAlerts('process', $alerts);
		}
	}

	public function checkWholesaleAction() {
		$this->weeklyTestWholesale();
	}

	protected function locate($process) {
		$logsModel = new LogModel();
		$empty_types = array();
		$filter_field = Billrun_Factory::config()->getConfigValue('cron.log.' . $process . '.field');
		$types = Billrun_Factory::config()->getConfigValue('cron.log.' . $process . '.types', array());
		foreach ($types as $type => $timediff) {
			$query = array(
				'source' => $type,
				$filter_field => array('$gt' => date('Y-m-d H:i:s', (time() - $timediff)))
			);
			$results = $logsModel->getData($query)->current();
			if ($results->isEmpty()) {
				$empty_types[] = $type;
			}
		}
		return $empty_types;
	}

	protected function sendAlerts($process, $empty_types) {
		if (empty($empty_types)) {
			return;
		}
		$events_string = implode(', ', $empty_types);
		Billrun_Factory::log("Send alerts for " . $process, Zend_Log::INFO);
		Billrun_Factory::log("Events types: " . $events_string, Zend_Log::INFO);
		$actions = Billrun_Factory::config()->getConfigValue('cron.log.' . $process . '.actions', array());
		if (isset($actions['email'])) {
			//'GT BillRun - file did not %s: %s'
			if (isset($actions['email']['recipients'])) {
				$recipients = $actions['email']['recipients'];
			} else {
				$recipients = $this->getEmailsList();
			}
			$this->mailer->addTo($recipients);
			$this->mailer->setSubject($actions['email']['subject']);
			$message = sprintf($actions['email']['message'], $process, $events_string);
			$this->mailer->setBodyText($message);
			$this->mailer->send();
		}
		if (isset($actions['sms'])) {
			//'GT BillRun - file types did not %s: %s'
			$message = sprintf($actions['sms']['message'], $process, $events_string);
			if (isset($actions['sms']['recipients'])) {
				$recipients = $actions['sms']['recipients'];
			} else {
				$recipients = $this->getSmsList();
			}
			$this->smser->send($message, $recipients);
		}
	}

	/**
	 * method to add output to the stream and log
	 * 
	 * @param string $content the content to add
	 */
	public function addOutput($content) {
		Billrun_Log::getInstance()->log($content, Zend_Log::INFO);
	}

	protected function getEmailsList() {
		return Billrun_Factory::config()->getConfigValue('cron.log.mail_recipients', array());
	}

	protected function getSmsList() {
		return Billrun_Factory::config()->getConfigValue('cron.log.sms_recipients', array());
	}

	/**
	 * method test wholesale data looking for inconsistencies
	 * 
	 */
	protected function weeklyTestWholesale() {
		$db = Billrun_Factory::config()->getConfigValue('wholesale.db');
		$settings = Billrun_Factory::config()->getConfigValue('cron.wholesale');
		$this->db = Zend_Db::factory('Pdo_Mysql', array(
				'host' => $db['host'],
				'username' => $db['username'],
				'password' => $db['password'],
				'dbname' => $db['name']
		));
		$base_query = 'SELECT * FROM wholesale where product REGEXP "^IL" AND carrier REGEXP "^(M|N)" AND network = "all" '
			. 'AND duration > "' . $settings['duration']['minimum'] . '" ';
		$check_day_of_month = date("Y-m-d", strtotime($settings['checkingDay'] . ' days ago'));
		$ref_day_of_month = date("Y-m-d", strtotime(($settings['checkingDay'] + 7) . ' days ago'));

		$check_day_query = $base_query . ' AND dayofmonth ="' . $check_day_of_month . '"';
		$ref_day_query = $base_query . ' AND dayofmonth ="' . $ref_day_of_month . '"';
		$check_day_data = $this->db->fetchAll($check_day_query);
		if (empty($check_day_data)) {
			Billrun_Factory::log("wholesale error: " . $check_day_of_month . " whoelsale data is missing", Zend_Log::ERR);
		}
		$ref_day_data = $this->db->fetchAll($ref_day_query);

		$rearranged_check_day = $this->reorder_wholesale_data($check_day_data);
		$rearranged_ref_day = $this->reorder_wholesale_data($ref_day_data);
		foreach ($rearranged_check_day as $carrier => $carrier_lines) {
			foreach ($carrier_lines as $product => $line) {
				if (!isset($rearranged_ref_day[$carrier][$product])) {
					continue;
				}
				$ref_duration = $rearranged_ref_day[$carrier][$product]['duration'];
				$diff = abs($line['duration'] / $ref_duration - 1);
				if ($diff > $settings['duration']['diff']) {
					$rpMessage = 'wholesale warning: carrier ' . $carrier . ' product ' . $product . ' on ' . $check_day_of_month
						. ' differs by ' . round($diff * 100) . '%' . ' compared to ' . $ref_day_of_month;
					Billrun_Factory::log($rpMessage, Zend_Log::ERR);
				}
			}
		}
	}

	protected function reorder_wholesale_data($wholesale_data) {
		$rearanged = array();
		foreach ($wholesale_data as $wholesale_line) {
			$rearanged[$wholesale_line['carrier']][$wholesale_line['product']] = $wholesale_line;
		}
		return $rearanged;
	}

}

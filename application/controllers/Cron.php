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
		$wh = new WholesaleModel();
		$wh->weeklyTestWholesale();
	}

	protected function locate($process) {
		$logsModel = new LogModel();
		$empty_types = array();
		$filter_field = Billrun_Factory::config()->getConfigValue('cron.log.' . $process . '.field');
		$types = Billrun_Factory::config()->getConfigValue('cron.log.' . $process . '.types', array());
		foreach ($types as $type => $timediff) {
			$ftp_settings = array_keys(Billrun_Factory::config()->getConfigValue($type . '.ftp', array()));
			$hosts = in_array("host", $ftp_settings) ? array("_HOST_") : $ftp_settings;
			foreach ($hosts as $server) {
				$serverConf = Billrun_Factory::config()->getConfigValue("{$type}.ftp.{$server}", array());
				if(!empty($serverConf['is_secondary'])) { continue; }

				if (in_array("_HOST_", $hosts)){ 
					$server = $type;
					$query = array(
						'source' => $type,
						$filter_field => array('$gt' => date('Y-m-d H:i:s', (time() - $timediff)))
					);
				} else {
					$query = array(
						'source' => $type,
						'retrieved_from' => $server,
						$filter_field => array('$gt' => date('Y-m-d H:i:s', (time() - $timediff)))
					);
					$replicatedHosts =  Billrun_Factory::config()->getConfigValue("{$type}.ftp.{$server}.replicated_hosts", array());
					if(!empty($replicatedHosts)) {
						$hostnames =  array_merge([$server],$replicatedHosts);
						asort($hostnames);
						$query['$or'] = [['retrieved_from' => $server],['retrieved_from' => implode('_',$hostnames)]];
						unset($query['retrieved_from']);
					}
				}
				$results = $logsModel->getData($query)->current();
				if ($results->isEmpty()) {
					$empty_types[] = array($type => $server);
				}
			}
		}
		return $empty_types;
	}

	protected function sendAlerts($process, $empty_types) {
		if (empty($empty_types)) {
			return;
		}
		foreach ($empty_types as $values) {
			foreach ($values as $type => $server) {
				$events_string[$type]= $type;
			}
		}
		$events_string = implode(', ', $events_string);
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
			foreach ($empty_types as $values) {
				foreach ($values as $type => $server) {
					$messages[] = sprintf($actions['email']['message'], $process, $type, $server);
				}
			}
			$message = implode("\n", $messages);
			$this->mailer->setBodyText($message);
			$this->mailer->send();
		}
		if (isset($actions['sms'])) {
			//'GT BillRun - file types did not %s: %s'
			foreach ($empty_types as $values) {
				foreach ($values as $type => $server) {
					$messages[] = sprintf($actions['sms']['message'], $process, $type, $server);
				}
			}
			$message = implode("\n", $messages);
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

}

<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
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
			return ;
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

	public function autoRenewServicesAction() {
		$handler = new Billrun_Autorenew_Handler();
		$handler->autoRenewServices();
	}
	
	public function closeBalancesAction() {
		$handler = new Billrun_Balances_Handler();
		$handler->closeBalances();
	}

	public function endedPlansAction() {
		$this->cancelSlownessByEndedPlans();
	}

	public function sendNotificationsAction() {
		$this->sendBalanceExpirationdateNotifications();
	}

	public function cancelSlownessByEndedPlans() {
		$balancesCollection = Billrun_Factory::db()->balancesCollection();
		$group = array(
			'$group' => array(
				'_id' => '$sid',
				'to' => array(
					'$first' => '$to',
				),
				'charging_type' => array(
					'$first' => '$charging_type',
				),
			),
		);
		$beginOfDay = strtotime("midnight", time());
		$beginOfYesterday = strtotime("yesterday midnight", time());
		$match = array(
			'$match' => array(
				'charging_type' => 'prepaid',
				'to' => array(
					'$gte' => new MongoDate($beginOfYesterday),
					'$lt' => new MongoDate($beginOfDay),
				),
			),
		);
		$project = array(
			'$project' => array(
				'sid' => '$_id',
			),
		);
		$balances = $balancesCollection->aggregate($group, $match, $project);
		$sids = array_map(function($doc) {
			return $doc['sid'];
		}, iterator_to_array($balances));
		
		Billrun_Factory::dispatcher()->trigger('subscribersPlansEnded', array($sids));
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
		
	protected function sendBalanceExpirationdateNotifications() {
		$plansNotifications = $this->getAllPlansWithExpirationDateNotification();
		foreach ($plansNotifications as $planNotification) {
			$subscribersInPlan = $this->getSubscribersInPlan($planNotification['plan_name']);
			foreach ($subscribersInPlan as $subscriber) {
				$balances = $this->getBalancesToNotify($subscriber->get('sid'), $planNotification['notification']);
				if ($balances) {
					foreach ($balances as $balance) {
						Billrun_Factory::dispatcher()->trigger('balanceExpirationDate', array($balance, $subscriber->getRawData()));
					}
				}
			}
		}
	}
	
	protected function getBalancesToNotify($subscriberId, $notification) {
		$balancesCollection = Billrun_Factory::db()->balancesCollection();
		$query = array(
			'sid' => $subscriberId,
			'to' => array(
				'$gte' => new MongoDate(strtotime('+' . $notification['value'] . ' days midnight')),
				'$lte' => new MongoDate(strtotime('+' . ($notification['value'] + 1) . ' days midnight')),
			),
			'pp_includes_external_id' => array('$in' => $notification['pp_includes']),
		);
		$balances = $balancesCollection->query($query)->cursor();
		if ($balances->count() == 0) {
			return false;
		}
		return $balances;
	}
	
	protected function getSubscribersInPlan($planName) {
		$subscribersCollection = Billrun_Factory::db()->subscribersCollection();
		$query = Billrun_Util::getDateBoundQuery();
		$query['plan'] = $planName;
		$subscribers = $subscribersCollection->query($query)->cursor();
		if ($subscribers->count() == 0) {
			return false;
		}
		return $subscribers;
	}
	
	protected function getAllPlansWithExpirationDateNotification() {
		$match = Billrun_Util::getDateBoundQuery();
		$match["notifications_threshold.expiration_date"] = array('$exists' => 1);
		$unwind = '$notifications_threshold.expiration_date';
		$plansCollection = Billrun_Factory::db()->plansCollection();
		$plans = $plansCollection->aggregate(array('$match' => $match),array('$unwind' => $unwind));
		$plansNotifications = array_map(function($doc) {
			return array('plan_name' => $doc['name'], 'notification' => $doc['notifications_threshold']['expiration_date']);
		}, iterator_to_array($plans));
		return $plansNotifications;
	}

}

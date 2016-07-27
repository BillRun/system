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

	public function cardsExpirationAction() {
		$handler = new Billrun_Cards_Handler();
		$result = $handler->cardsExpiration();

		// TODO: Do something with the result?
	}

	public function autoRenewServicesAction() {
		$params = array();
		$inputDate = $this->getRequest()->get('active_date');
		if (!empty($inputDate)) {
			$inputDate = strtotime($inputDate);
			if ($inputDate > time()) {
				Billrun_Factory::log()->log("Future input date - Current date will be used instead",zend_log::NOTICE);
			} else {
				$params['active_date'] = $inputDate;
			}
		}		
		$handler = new Billrun_Autorenew_Handler($params);
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
		$day_type = Billrun_HebrewCal::getDayType(time());
		if ($day_type == HEBCAL_HOLIDAY || $day_type == HEBCAL_WEEKEND) {
			Billrun_Factory::log("[Cron:sendNotifications] We are on Holiday or Saturday, disable sending notifcations.", Zend_Log::NOTICE);
			return;
		}
		$handler = new Billrun_Balances_Handler();
		$handler->sendBalanceExpirationdateNotifications();
	}

	public function cancelSlownessByEndedPlans() {
		$balancesCollection = Billrun_Factory::db()->balancesCollection();
		$match = array(
			'$match' => array(
				'charging_type' => 'prepaid',
				'charging_by_usaget' => 'data',
				'to' => array(
					'$gt' => new MongoDate(strtotime("yesterday midnight")),
					'$lte' => new MongoDate(strtotime("midnight")),
				),
			),
		);
		$group = array(
			'$group' => array(
				'_id' => '$sid',
			),
		);
		$project = array(
			'$project' => array(
				'sid' => '$_id',
			),
		);
		$balances = $balancesCollection->aggregate($match, $group, $project);
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
	
	public function handleSendRequestErrorAction() {
		// Get all subscribers on data slowness
		$query = array_merge(Billrun_Util::getDateBoundQuery(), array('in_data_slowness' => true));
		$project = array('_id' => false, 'sid' => true);
		$subscribersInDataSlowness = Billrun_Factory::db()->subscribersCollection()->find($query, $project);
		if ($subscribersInDataSlowness->count() === 0) {
			return;
		}
		$inDataSlownessSids = array_column(iterator_to_array($subscribersInDataSlowness), 'sid');

		// Check if one of the subscriber in data slowness has valid data balance
		$minUsagev = abs(Billrun_Factory::config()->getConfigValue('balance.minUsage.data', Billrun_Factory::config()->getConfigValue('balance.minUsage', 0, 'float')));
		$minCost = abs(Billrun_Factory::config()->getConfigValue('balance.minCost.data', Billrun_Factory::config()->getConfigValue('balance.minCost', 0, 'float')));
		$query = array_merge(Billrun_Util::getDateBoundQuery(), 
			array(
				'sid' => array('$in' => array_values($inDataSlownessSids)),
				'charging_by_usaget' => 'data',
				'$or' => array(
					array('balance.totals.data.usagev' => array('$lte' => -$minUsagev)),
					array('balance.totals.data.cost' => array('$lte' => -$minCost)),
				),
			)
		);
		$balances = Billrun_Factory::db()->balancesCollection()->find($query, $project);
		if ($balances->count() === 0) {
			return;
		}
		$sids = array_column(iterator_to_array($balances), 'sid');
		
		Billrun_Factory::dispatcher()->trigger('handleSendRquestErrors', array($sids));
	}
	
	protected function render($tpl, array $parameters = array()) {
		return parent::render('index', $parameters);
	}
}

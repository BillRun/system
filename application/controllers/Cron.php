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
		$this->autoRenewServices();
	}		

	public function nonRecurringAction() {
		$this->cancelSlownessByEndedNonRecurringPlans();
	}
	
		
	protected function getMonthAutoRenewQuery() {
		$lastmonthLower = mktime(0, 0, 0, date("m")-1, date("d"), date("Y"));
		$lastmonthUpper = mktime(23, 59, 59, date("m")-1, date("d"), date("Y"));
		
		$or = array();
		$or[] = array('last_renew_date' => array('$gte' => new MongoDate($lastmonthLower)));
		$or[] = array('last_renew_date' => array('$lte' => new MongoDate($lastmonthUpper)));
		
		// Check if last day.
		if(date('d') == date('t')) {
			$or = array('$or' => $or);
			$or['$or']['$and']['eom'] = 1;
			$or['$or']['$and']['last_renew_date']['$gt'] = new MongoDate($lastmonthUpper);
			$firstday = mktime(0, 0, 0, date("m"), 1, date("Y"));
			$or['$or']['$and']['last_renew_date']['$lt'] = new MongoDate($firstday);
		}
		
		$and = array();
		$and[] = array('$or' => $or);
		$and[] = array("interval" => "month");
		
		return $and;
	}
	
	protected function getDayAutoRenewQuery() {
		$lastdayLower = mktime(0, 0, 0, date("m"), date("d") - 1, date("Y"));
		$lastdayUpper = mktime(23, 59, 59, date("m"), date("d") - 1, date("Y"));
		
		$or = array();
		$or[] = array('last_renew_date' => array('$gte' => new MongoDate($lastdayLower)));
		$or[] = array('last_renew_date' => array('$lte' => new MongoDate($lastdayUpper)));
		
		$and = array();
		$and[] = array('$or' => $or);
		$and[] = array("interval" => "day");
		
		return $and;
	}
	
	/**
	 * Get the auto renew services query.
	 * @return array - Query date.
	 */
	protected function getAutoRenewServicesQuery() {
		$andQuery = array_merge($this->getDayAutoRenewQuery(), $this->getMonthAutoRenewQuery());
		$queryDate = array('$and' => $andQuery);
		$queryDate['remain'] = array('$gt' => 0);
		
		return $queryDate;
	}
	
	public function autoRenewServices() {				
		$queryDate = $this->getAutoRenewServicesQuery();
		
		$collection = Billrun_Factory::db()->subscribers_auto_renew_servicesCollection();
		$autoRenewCursor = $collection->query($queryDate)->cursor();
		
		// Go through the records.
		foreach ($autoRenewCursor as $autoRenewRecord) {
			$this->updateBalanceByAutoRenew($autoRenewRecord);
			
			$this->updateAutoRenewRecord($collection, $autoRenewRecord);
		}
	}
	
	/**
	 * @todo Not completed
	 */
	public function cancelSlownessByEndedNonRecurringPlans() {
		$balancesCollection = Billrun_Factory::db()->balancesCollection();
		$sort = array(
			'$sort' => array(
				'to' => -1,
			),
		);
		$group = array(
			'$group' => array(
				'_id' => '$sid',
				'to' => array(
					'$first' => '$to',
				),
				'charging_type' => array(
					'$first' => '$charging_type',
				)
			),
		);
		$match = array(
			'$match' => array(
				'charging_type' => 'prepaid',
				'to' => array('$lt' => new MongoDate()),
			),
		);
		$project = array(
			'$project' => array(
				'sid' => '$_id',
			),
		);
		$balances = $balancesCollection->aggregate($sort, $group, $match, $project);
		$sids = array_map(function($doc) {
			return $doc['sid'];
		}, iterator_to_array($balances));
	}
	
	/**
	 * Check if we are in 'dead' days
	 * @return boolean
	 */
	protected function areDeadDays() {
		$lastDayLastMonth = date('d', strtotime('last day of previous month'));
		$today = date('d');
		
		if($lastDayLastMonth <= $today) {
			$lastDay = date('t');
			if($today != $lastDay) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Update the auto renew record after usage.
	 * @param type $collection
	 * @param Entity Auto renew record to update.
	 * @return type
	 */
	protected function updateAutoRenewRecord($collection, $autoRenewRecord) {
		$autoRenewRecord['remain'] = $autoRenewRecord['remain'] - 1;
		
		if($autoRenewRecord['eom'] == 1) {
			$autoRenewRecord['last_renew_date'] = new MongoDate(strtotime('last day of this month'));
		} else {
			$autoRenewRecord['last_renew_date'] = new MongoDate();
		}
		$autoRenewRecord['done'] = $autoRenewRecord['done'] + 1;

		return $collection->updateEntity($autoRenewRecord);
	}
	
	/**
	 * Update a balance according to a auto renew record.
	 * @param type $autoRenewRecord
	 * @return boolean
	 */
	protected function updateBalanceByAutoRenew($autoRenewRecord) {
		$updater = new Billrun_ActionManagers_Balances_Update(); 

		$updaterInput['method'] = 'update';
		$updaterInput['sid'] = $autoRenewRecord['sid'];

		// Build the query
		$updaterInputQuery['charging_plan_external_id'] = $autoRenewRecord['charging_plan_external_id'];
		$updaterInputUpdate['from'] = $autoRenewRecord['from'];
		$updaterInputUpdate['to'] = $autoRenewRecord['to'];
		$updaterInputUpdate['operation'] = $autoRenewRecord['operation'];

		$updaterInput['query'] = json_encode($updaterInputQuery,JSON_FORCE_OBJECT);
		$updaterInput['upsert'] = json_encode($updaterInputUpdate,JSON_FORCE_OBJECT);
		
		// Anonymous object
		$jsonObject = new Billrun_AnObj($updaterInput);
		if(!$updater->parse($jsonObject)) {
			// TODO: What do I do here?
			return false;
		}
		if(!$updater->execute()) {
			// TODO: What do I do here?
			return false;
		}
		
		Billrun_Factory::dispatcher()->trigger('afterSubscriberBalanceAutoRenewUpdate', array($autoRenewRecord));
				
		return true;
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

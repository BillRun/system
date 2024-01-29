<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Plugin to handle notifications when there is not activity for period of time
 * The activity can be billing lines processed or file log received
 *
 * @package  Application
 * @subpackage Plugins
 * @since    5.12
 */
class notificationsPlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'notifications';

	/**
	 * notification config container
	 * 
	 * @var array
	 */
	protected $config = array();

	public function __construct(array $options = array()) {
		// first try to fetch notification seperately
		$notificationConfig = Billrun_Factory::config()->getConfigValue('notifications_settings', array());
		if (empty($notificationConfig)) {
			// get input processors settings
			$notificationConfig = Billrun_Factory::config()->getConfigValue('file_types');
		}
		foreach ($notificationConfig as $input) {
			if (!isset($input['monitoring']['recurrence']) || !isset($input['monitoring']['recurrence']['type']) || !isset($input['monitoring']['recurrence']['value'])) {
				$input['monitoring']['recurrence'] = array(
					"type" => "minutely",
					"value" => 15,
				);
			}
			$recurrent_type = $input['monitoring']['recurrence']['type'];
			$this->config[$recurrent_type][] = $input;
		}
	}

	public function cronMinute() {
		$this->cronIteration('minutely', 'i');
	}

	public function cronHour() {
		$this->cronIteration('hourly', 'H');
	}

	public function cronDay() {
		$this->cronIteration('daily', 'd');
	}

	public function cronWeek() {
		$this->cronIteration('weekly', 'W');
	}

	public function cronMonth() {
		$this->cronIteration('monthly', 'm');
	}

	protected function cronIteration($recurrent, $timeOperator) {
		Billrun_Factory::log('Running notifications ' . $recurrent, Billrun_Log::INFO);
		$notificationConfig = !empty($this->config[$recurrent]) ? $this->config[$recurrent] : [];
		$log = array();
		foreach ($notificationConfig as $input) {
			try {
				Billrun_Factory::log('Running notification minutely - start to check ' . $input['file_type'] . ' input processor', Billrun_Log::INFO);

				if (date($timeOperator) % $input['monitoring']['recurrence']['value'] !== 0) {
					Billrun_Factory::log($input['file_type'] . ' recurrent is not matched', Zend_Log::INFO);
					continue;
				}

				if (empty($input['enabled'])) {
					Billrun_Factory::log($input['file_type'] . ' input processor is not enabled', Zend_Log::INFO);
					return true;
				}
				
				if (empty($input['monitoring']['enabled'])) {
					Billrun_Factory::log($input['file_type'] . ' monitoring is not enabled', Zend_Log::INFO);
					return true;
				}

				$receiveStatus = $this->checkReceive($input);
				if ($receiveStatus !== true) {
					$log['receive'][] = $input['file_type'];
					$this->sendNotifications('receive', $input, $receiveStatus);
				}
				$processStatus = $this->checkProcess($input);
				if ($processStatus !== true) {
					$log['process'][] = $input['file_type'];
					$this->sendNotifications('process', $input, $processStatus);
				}
			} catch (Exception $ex) {
				Billrun_Factory::log('Notification plugin failed with the following issue :' . $ex->getCode() . ' - ' . $ex->getMessage() . PHP_EOL . $ex->__toString(), Billrun_Log::WARN);
			}
		}

		if (count($log)) {
			Billrun_Factory::log('Notification plugin trigger the next alerts:' . print_R($log, 1), Billrun_Log::WARN);
		}
	}

	/**
	 * method to check if input processor did not received file in the last configurable time
	 * 
	 * @param array $input the input processor
	 * 
	 * @return boolean true if input processor is ok, else false in case there is not file received or the last file received
	 */
	protected function checkReceive($input) {
		// need to skip realtime as this is not based on batch files (log collection)
		if ($input['type'] == 'realtime') {
			Billrun_Factory::log($input['file_type'] . ' is realtime - skip receive check', Zend_Log::INFO);
			return true;
		}

		if (is_array($input['monitoring']['receive_alert_after'])) {
			$minimum_time = strtotime($input['monitoring']['receive_alert_after']['value'] . ' ' . $input['monitoring']['receive_alert_after']['type'] . ' ago');
		} else if (is_numeric($input['monitoring']['receive_alert_after'])) {
			$minimum_time = strtotime($input['monitoring']['receive_alert_after'] . ' seconds ago');
		} else if (is_string($input['monitoring']['receive_alert_after'])) {
			$minimum_time = strtotime($input['monitoring']['receive_alert_after']);
		} else {
			Billrun_Factory::log($input['file_type'] . ' receive_alert_after is not configured well', Zend_Log::ALERT);
		}
		$query = array(
			'source' => $input['file_type'],
			'received_time' => array(
				'$gte' => new MongoDate($minimum_time),
			),
		);

		$sortReceived = array(
			'received_time' => -1,
		);

		if (isset($input['monitoring']['files_num']) && is_numeric($input['monitoring']['files_num']) && $input['monitoring']['files_num'] > 0 && $input['monitoring']['files_num'] < 99999) {
			$files_num = $input['monitoring']['files_num'];
		} else {
			$files_num = 1;
		}
		$lastReceivedFiles = Billrun_Factory::db()->logCollection()->query($query)->cursor()->sort($sortReceived)->limit($files_num);

		$resultCount = $lastReceivedFiles->count();

		if ($resultCount >= $files_num) {
			// log amount of files not enough to trigger alert
			Billrun_Factory::log($input['file_type'] . ' received files num less than minimum to trigger', Zend_Log::INFO);
			return true;
		}

		$current = $lastReceivedFiles->current();
		if (!empty($current) && !$current->isEmpty()) {
			return $current;
		}

		unset($query['received_time']);
		return Billrun_Factory::db()->logCollection()->query($query)->cursor()->sort($sortReceived)->limit(1)->current();
	}

	/**
	 * method to check if input processor did not process lines in the last configurable time
	 * 
	 * @param array $input the input processor
	 * 
	 * @return mixed true if input processor is ok, else false in case there is not processed or the last line processed
	 */
	protected function checkProcess($input) {
		if (is_array($input['monitoring']['process_alert_after'])) {
			$minimum_time = strtotime($input['monitoring']['process_alert_after']['value'] . ' ' . $input['monitoring']['process_alert_after']['type'] . ' ago');
		} else if (is_numeric($input['monitoring']['receive_alert_after'])) {
			$minimum_time = strtotime($input['monitoring']['process_alert_after'] . ' seconds ago');
		} else if (is_string($input['monitoring']['receive_alert_after'])) {
			$minimum_time = strtotime($input['monitoring']['process_alert_after']);
		} else {
			Billrun_Factory::log($input['file_type'] . ' receive_alert_after is not configured well', Zend_Log::ALERT);
		}

		$query = array(
			'type' => $input['file_type'],
			'urt' => array(
				'$gte' => new MongoDate($minimum_time),
			),
		);

		$sortProcessed = array(
			'urt' => -1,
		);

		if (isset($input['monitoring']['files_num']) && is_numeric($input['monitoring']['files_num']) && $input['monitoring']['files_num'] > 0 && $input['monitoring']['files_num'] < 99999) {
			$files_num = $input['monitoring']['files_num'];
		} else {
			$files_num = 1;
		}


		$lastProcessedLines = Billrun_Factory::db()->linesCollection()->query($query)->cursor()->sort($sortProcessed)->limit($files_num);

		$resultCount = $lastProcessedLines->count();

		if ($resultCount >= $files_num) {
			// log amount of files not enough to trigger alert
			Billrun_Factory::log($input['file_type'] . ' processed files num less than minimum to trigger', Zend_Log::INFO);
			return true;
		}

		$current = $lastProcessedLines->current();
		if (!empty($current) && !$current->isEmpty()) {
			return $current;
		}

		unset($query['urt']);
		return Billrun_Factory::db()->linesCollection()->query($query)->cursor()->sort($sortProcessed)->limit(1)->current();
	}

	/**
	 * method to send notifications
	 * 
	 * @param string $step step that trigger event of (receive or process)
	 * @param array $input the input processor
	 * @param array $lastEvent the last event handled
	 * 
	 * @return void
	 */
	protected function sendNotifications($step, $input, $lastEvent) {
		$subject = 'BillRun Notification - Input Did Not Received';
		$body = 'Hello,' . PHP_EOL . '<br />'
				. 'We have noticed that the input processor ' . $input['file_type'] . ' did not ' . $step . PHP_EOL . '<br />'
				. '{} <br /><br />' . PHP_EOL
				. 'Thank you,' . '<br />' . PHP_EOL
				. 'BillRun Monitoring';

		if (!empty($lastEvent) && !$lastEvent->isEmpty()) {
			$lastEventTimeFieldMapping = [
				'receive' => 'received_time',
				'process' => 'urt',
			];
			$replace = 'The last event info as follow: <ul>' . PHP_EOL
					. '<li>Date time:  ' . date(Billrun_Base::base_datetimeformat, $lastEvent[$lastEventTimeFieldMapping[$step]]->sec) . '</li>' . PHP_EOL
					. '<li>File name: ' . ($step == 'receive' ? $lastEvent['file_name'] : $lastEvent['file']) . '</li>' . PHP_EOL
					. '</ul>' . PHP_EOL;
		} else {
			$replace = '';
		}
		$content = str_replace('{}', $replace, $body);

		$this->sendEmail($input, $subject, $content);
		$this->sendSMS($input, strip_tags($content));
	}

	/**
	 * method to send email notification
	 * 
	 * @param array $input the input processor
	 * 
	 * @return void
	 */
	protected function sendEmail($input, $emailSubject, $emailBody) {
		$recipients = $this->getEmailList($input['monitoring']);
		if (empty($recipients)) {
			Billrun_Factory::log("Notification alert - no email recipients define for input processor " . $input['file_type'], Zend_Log::ALERT);
			return;
		}
		$mailer = Billrun_Factory::mailer();
		$mailer->addTo($recipients);
		$mailer->setSubject($emailSubject);
		$mailer->setBodyHtml($emailBody);
		$mailer->send();
		Billrun_Factory::log('Notification alert -  email sent to  recipients : ' . implode(",", $recipients), Zend_Log::DEBUG);
	}

	/**
	 * method to send sms notification
	 * 
	 * @param array $input the input processor
	 * 
	 * @return void
	 */
	protected function sendSMS($input, $content) {
		$recipients = $this->getSMSList($input['monitoring']);
		if (empty($recipients)) {
			Billrun_Factory::log("Notification alert - no sms recipients define for input processor " . $input['file_type'], Zend_Log::INFO);
			return;
		}
		$smser = Billrun_Factory::smser();
		$smser->send($content, $recipients);
	}

	/**
	 * method to get all email list to notify
	 * 
	 * @param array $input the input processor
	 * 
	 * @return array list of email list
	 */
	protected function getEmailList($input) {
		$ret = array();
		if (!empty($input['notify_by_email']['use_global_addresses']) && !empty(Billrun_Factory::config()->getConfigValue('log.email.writerParams.to'))) {
			$ret = array_merge($ret, Billrun_Factory::config()->getConfigValue('log.email.writerParams.to'));
		}

		if (!empty($input['notify_by_email']['additional_addresses'])) {
			$ret = array_merge($ret, (array) $input['notify_by_email']['additional_addresses']);
		}

		return $ret;
	}

	/**
	 * method to get all email list to notify
	 * 
	 * @param array $input the input processor
	 * 
	 * @return array list of email list
	 */
	protected function getSMSList($input) {
		$ret = array();
		if (!empty($input['notify_by_sms']['use_global_addresses']) && !empty(Billrun_Factory::config()->getConfigValue('log.sms.writerParams.to'))) {
			$ret = array_merge($ret, Billrun_Factory::config()->getConfigValue('log.sms.writerParams.to'));
		}

		if (!empty($input['notify_by_sms']['additional_addresses'])) {
			$ret = array_merge($ret, (array) $input['notify_by_sms']['additional_addresses']);
		}

		return $ret;
	}

}

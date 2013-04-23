<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Email alerts plugin
 *
 * @package  Application
 * @subpackage Plugins
 * @since    0.5
 */
class emailAlertsPlugin extends Billrun_Plugin_BillrunPluginBase  {
	
	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'emailAlerts';

	/**
	 * The timestamp of the start of the script
	 * 
	 * @var timestamp
	 */
	protected $startTime;

	/**
	 * Is the  Alert plugin in a dry run mode (doesn't  actually sends alerts)
	 * @var timestamp
	 */
	protected $isDryRun = false;

	public function __construct($options = array(
	)) {

		$this->isDryRun = isset($options['dryRun']) ?
			$options['dryRun'] :
			Billrun_Factory::config()->getConfigValue('emailAlerts.dry_run', false);

		$this->alertTypes = isset($options['alertTypes']) ?
			$options['alertTypes'] :
			Billrun_Factory::config()->getConfigValue('emailAlerts.alert.types', array('nrtrde', 'ggsn', 'deposit', 'ilds', 'nsn'));

		$this->processingTypes = isset($options['processingTypes']) ?
			$options['processingTypes'] :
			Billrun_Factory::config()->getConfigValue('emailAlerts.processing.types', array('nrtrde', 'ggsn', 'nsn', 'tap3'));

		$this->startTime = time();
	}

	/**
	 * Handle Notification that should be done on events that were logged in the system.
	 * @param type $handler the caller handler.
	 * @return type
	 */
	public function handlerNotify($handler) {

		$ret[] = $this->alertsNotify();
		$ret[] = $this->processingNotify();


		return $ret;
	}

	/**
	 * Gather all the finished events.
	 * @return array return value of each event status
	 */
	protected function alertsNotify() {
		$retValue = array();
		if (!Billrun_Factory::config()->getConfigValue('emailAlerts.alerts.active', true)) {
			return $retValue;
		}

		//Aggregate the  events by imsi  taking only the first one.
		$events = $this->gatherEvents($this->alertTypes);

		foreach ($events as $event) {
			//	Billrun_Log::getInstance()->log("emailAlerts::alertsNotify : ".print_r($event,1), Zend_Log::DEBUG);
			$retValue[] = $event;
		}
		$this->sendAlertsResultsSummary($retValue);
		$this->markSentEmailEvents($events);
		return $retValue;
	}

	/**
	 * Handle Roaming events and try to notify the remote server.
	 * @return array return value of each event status
	 */
	protected function processingNotify() {
		$retValue = array();
		if (!Billrun_Factory::config()->getConfigValue('emailAlerts.processing.active', true)) {
			return $retValue;
		}

		//Aggregate the  events by imsi  taking only the first one.
		$logs = $this->gatherLogs($this->processingTypes);
		$warningTime = strtotime("-" . Billrun_Factory::config()->getConfigValue('emailAlerts.processing.thresholds.warning', '1 day'));
		$alertTime = strtotime("-" . Billrun_Factory::config()->getConfigValue('emailAlerts.processing.thresholds.alert', '12 hours'));

		foreach ($logs as $key => $log) {
			if (isset($log['last_processed'])) {
				$log['warning'] = strtotime($log['last_processed']['process_time']) - $warningTime < 0;
				$log['alert'] = strtotime($log['last_processed']['process_time']) - $alertTime < 0;
			}
			$retValue[$key] = $log;
		}
		$this->sendProcessingSummary($retValue);
		return $retValue;
	}

	/**
	 * get handaled events from the DB.
	 * @param Array $types the type (sources) of the events to gather.
	 * @return Array an array containg the events pulled from the data base.
	 */
	protected function gatherEvents($types) {
		$events = Billrun_Factory::db()->eventsCollection()->query(array(
			'source' => array('$in' => $types),
			'notify_time' => array('$exists' => true),
			'email_sent' => array('$exists' => false),
			'$where' => "this.deposit_stamp == this.stamp",
		));


		return $events;
	}

	/**
	 * get handaled events from the DB.
	 * @param Array $types the type (sources) of the events to gather.
	 * @return Array an array containg the events pulled from the data base.
	 */
	protected function gatherLogs($types) {
		$aggregateLogs = array();
		foreach ($types as $type) {
			$aggregateLogs[$type]['last_processed'] = Billrun_Factory::db()->logCollection()->
					query(array('source' => $type, 'process_time' => array('$exists' => true)))->cursor()->
					sort(array('proccess_time' => -1, '_id' => -1))->limit(1)->current();
			$aggregateLogs[$type]['last_received'] = Billrun_Factory::db()->logCollection()->
					query(array('source' => $type, 'received_time' => array('$exists' => true)))->cursor()->
					sort(array('received_time' => -1, '_id' => -1))->limit(1)->current();
		}
		//Billrun_Log::getInstance()->log("emailAlerts::alertsNotify : ".print_r($aggregateLogs,1), Zend_Log::DEBUG);
		return $aggregateLogs;
	}

	/**
	 * Mark an specific event after email sent. 
	 * @param type $event the event to mark as dealt with.
	 */
	protected function markSentEmailEvents($events) {
		foreach ($events as $event) {
			$event['email_sent'] = true;
			$event->save(Billrun_Factory::db()->eventsCollection());
		}
	}

	/**
	 * send  alerts results by email.
	 */
	protected function sendAlertsResultsSummary($events) {

		Billrun_Log::getInstance()->log("Sending alerts result to email", Zend_Log::DEBUG);

		$failed = $successful = 0;
		foreach ($events as $event) {
			if (isset($event['returned_value']) && isset($event['returned_value']['success']) && $event['returned_value']['success']) {
				$successful++;
			} else {
				$failed++;
			}
		}

		$msg = "Count of failed: $failed" . PHP_EOL .
			"Count of success: $successful" . PHP_EOL;

		if ($failed || $successful) {
			$msg = PHP_EOL . "This mail contain 1 attachment for libreoffice and ms-office" . PHP_EOL;
			$attachmentPath = '/tmp/' . date('YmdHi') . '_alert_status.csv';
			$attachment = $this->generateMailCSV($attachmentPath, $events);
		}

		$ret = $this->sendMail("NRTRDE status " . date(Billrun_Base::base_dateformat), $msg, Billrun_Factory::config()->getConfigValue('emailAlerts.alerts.recipients', array()), array($attachment));

		if (file_exists($attachmentPath)) {
			@unlink($attachmentPath);
		}

		return $ret;
	}

	/**
	 * send  processing results by email.
	 */
	protected function sendProcessingSummary($logs) {
		Billrun_Log::getInstance()->log("Sending Processing result to email", Zend_Log::DEBUG);

		$msg = "";
		//Billrun_Log::getInstance()->log(print_r($logs), Zend_Log::DEBUG);die();
		foreach ($logs as $type => $val) {
			$name = strtoupper($type);
			if (!isset($val['last_received'])) {
				$msg .= strtoupper($type) . " no files were processed or recevied";
				continue;
			}
			if ($val['warning']) {
				$msg .= "WARNNING! : it seems the server stopped processing $name" . PHP_EOL . PHP_EOL;
			}
			if ($val['alert']) {
				$msg .= "ALERT! : didn't processed $name longer then the configuraed time" . PHP_EOL . PHP_EOL;
			}

			if (isset($val['last_processed'])) {
				$seq = $this->getFileSequenceData($val['last_processed']['file_name'], $type);
				$msg .= strtoupper($type) . " last processed Index : " . $seq['seq'] . " processing date : " . $val['last_processed']['process_time'] . PHP_EOL;
			} else {
				$msg .= strtoupper($type) . " no processed files " . PHP_EOL;
			}
			$seq = $this->getFileSequenceData($val['last_received']['file_name'], $type);
			$msg .= strtoupper($type) . " recevied Index : " . $seq['seq'] . " receving date : " . $val['last_received']['received_time'] . PHP_EOL;

			$msg .= PHP_EOL . PHP_EOL;
		}

		return $this->sendMail("Processing status " . date(Billrun_Base::base_dateformat), $msg, Billrun_Factory::config()->getConfigValue('emailAlerts.processing.recipients', array()));
	}

	/**
	 * Send Email helper
	 * @param type $subject the subject of the message.
	 * @param type $body the body of the message
	 * @param type $attachments (optional)
	 * @return type
	 */
	protected function sendMail($subject, $body, $recipients = array(), $attachments = array()) {
		$recipients = $this->isDryRun ? array('eran', 'ofer') :
			Billrun_Factory::config()->getConfigValue('emailAlerts.recipients', $recipients);

		$mailer = Billrun_Factory::mailer()->
			setSubject($subject)->
			setBodyText($body);

		//add attachments
		foreach ($attachments as $attachment) {
			$mailer->addAttachment($attachment);
		}
		//set recipents
		foreach ($recipients as $recipient) {
			$mailer->addTo($recipient);
		}
		//sen email
		return $mailer->send();
	}

	/**
	 * Generate CSV to represent the 
	 * @param type $filepath
	 * @param type $events
	 * @return \Zend_Mime_Part
	 */
	protected function generateMailCSV($filepath, $events) {
		$fp = fopen($filepath, 'w');
		$header = array('creation_time', 'account_id', 'NDC_SN', 'imsi', 'event_type', 'value', 'subscriber_id', 'deposit_result', 'success');
		fputcsv($fp, $header);
		foreach ($events as $event) {
			$csvEvent = array();
			foreach ($header as $fieldKey) {
				$csvEvent[$fieldKey] = isset($event['returned_value']) && isset($event['returned_value'][$fieldKey]) ?
					$event['returned_value'][$fieldKey] :
					( isset($event[$fieldKey]) ? $event[$fieldKey] : "");
			}

			fputcsv($fp, $csvEvent);
		}

		fclose($fp);
		$mime = new Zend_Mime_Part(file_get_contents($filepath));
		$mime->filename = basename($filepath);
		$mime->disposition = Zend_Mime::DISPOSITION_INLINE;
		$mime->encoding = Zend_Mime::ENCODING_BASE64;
		return $mime;
	}

	/**
	 * An helper function for the Billrun_Common_FileSequenceChecker  ( helper :) ) class.
	 * Retrive the ggsn file date and sequence number
	 * @param type $filename the full file name.
	 * @return boolea|Array false if the file couldn't be parsed or an array containing the file sequence data
	 * 						[seq] => the file sequence number.
	 * 						[date] => the file date.  
	 */
	public function getFileSequenceData($filename, $type) {
		return array(
			'seq' => Billrun_Util::regexFirstValue(Billrun_Factory::config()->getConfigValue($type . ".sequence_regex.seq", "/(\d+)/"), $filename),
			'date' => Billrun_Util::regexFirstValue(Billrun_Factory::config()->getConfigValue($type . ".sequence_regex.date", "/(20\d{6})/"), $filename),
			'time' => Billrun_Util::regexFirstValue(Billrun_Factory::config()->getConfigValue($type . ".sequence_regex.time", "/\D(\d{4,6})\D/"), $filename),
		);
	}

}

?>

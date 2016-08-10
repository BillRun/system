<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Email alerts plugin
 *
 * @package  Application
 * @subpackage Plugins
 * @since    0.5
 */
class emailAlertsPlugin extends Billrun_Plugin_BillrunPluginBase {

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

	/**
	 * The email addresses that should get all the emails
	 * @var arrary
	 */
	protected $commonRecipients = array();

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

		$this->commonRecipients = isset($options['recipients']) ?
			$options['recipients'] :
			Billrun_Factory::config()->getConfigValue('emailAlerts.recipients', array());

		$this->startTime = time();
	}

	/**
	 * Handle Notification that should be done on events that were logged in the system.
	 * @param type $handler the caller handler.
	 * @return type
	 */
	public function handlerNotify($handler, $options) {
		if ($options['type'] != 'notify') {
			return FALSE;
		}
		$ret[] = $this->processingNotify();
		$ret[] = $this->alertsNotify();


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

		$logs = $this->gatherLogs($this->processingTypes);
		foreach ($logs as $key => $log) {
			$logArr[$key] = $log;
		}
		$postMsg = $this->getProcessingSummary($logArr);

		//Aggregate the  events by imsi  taking only the first one.
		$events = $this->gatherEvents($this->alertTypes);

		foreach ($events as $event) {
			$retValue[] = $event;
		}
		$this->sendAlertsResultsSummary($retValue, $postMsg);
		$this->markSentEmailEvents($events);
		return $retValue;
	}

	/**
	 * Handle Roaming events and try to notify the remote server.
	 * @return array return value of each event status
	 */
	protected function processingNotify() {

		if (!Billrun_Factory::config()->getConfigValue('emailAlerts.processing.active', true)) {
			return "";
		}

		//Aggregate the  events by imsi  taking only the first one.
		$retValue = array();
		$logs = $this->gatherLogs($this->processingTypes, true);
		$warningTime = strtotime("-" . Billrun_Factory::config()->getConfigValue('emailAlerts.processing.thresholds.warning', '1 day'));
		$alertTime = strtotime("-" . Billrun_Factory::config()->getConfigValue('emailAlerts.processing.thresholds.alert', '12 hours'));

		foreach ($logs as $key => $log) {
			if (isset($log['last_processed'])) {
				$log['warning'] = strtotime($log['last_processed']['process_time']) - $warningTime < 0;
				$log['alert'] = strtotime($log['last_processed']['process_time']) - $alertTime < 0;
			}
			$retValue[$key] = $log;
		}
		return $this->sendProcessingSummary($retValue);
	}

	/**
	 * get handaled events from the DB.
	 * @param Array $types the type (sources) of the events to gather.
	 * @return Array an array containg the events pulled from the data base.
	 */
	protected function gatherEvents($types) {
                $allowedAlertsDelay = Billrun_Factory::config()->getConfigValue('fraud.alerts.allowed_delay', '-1 months');
		$events = Billrun_Factory::db()->eventsCollection()->query(array(
			'source' => array('$in' => $types),
			'notify_time' => array('$gt' => new MongoDate(strtotime($allowedAlertsDelay))),
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
	protected function gatherLogs($types, $received = FALSE) {
		$aggregateLogs = array();
		foreach ($types as $type) {
			$aggregateLogs[$type]['last_processed'] = Billrun_Factory::db()->logCollection()->
					query(array('source' => $type, 'process_time' => array('$exists' => true)))->cursor()->
					sort(array('process_time' => -1, '_id' => -1))->limit(1)->current();
			if ($received) {
				$aggregateLogs[$type]['last_received'] = Billrun_Factory::db()->logCollection()->
						query(array('source' => $type, 'received_time' => array('$exists' => true)))->cursor()->
						sort(array('received_time' => -1, '_id' => -1))->limit(1)->current();
			}
		}
		return $aggregateLogs;
	}

	/**
	 * Mark an specific event after email sent. 
	 * @param type $event the event to mark as dealt with.
	 */
	protected function markSentEmailEvents($events) {
                $eventIds = array();
		foreach ($events as $event) {
			//$event['email_sent'] = true;
			//$event->save(Billrun_Factory::db()->eventsCollection());
                        $eventIds[]= $event['_id']->getMongoID();
		}
                Billrun_Factory::db()->eventsCollection()->update(
                                                            array('_id'=>array('$in'=>$eventIds)),
                                                            array('$set'=>array('email_sent'=>true)),
                                                            array('multiple'=>true) 
                                                        );
	}

	/**
	 * send  alerts results by email.
	 */
	protected function sendAlertsResultsSummary($events, $postMsg = null) {

		Billrun_Log::getInstance()->log("Sending alerts result to email", Zend_Log::INFO);

		$failed = $successful = 0;
		foreach ($events as $event) {
			if (isset($event['returned_value']) && isset($event['returned_value']['success']) && $event['returned_value']['success']) {
				$successful++;
			} else {
				$failed++;
			}
		}

		$msg = "Count of failed: $failed" . PHP_EOL
			. "Count of success: $successful" . PHP_EOL
			. PHP_EOL . $postMsg . PHP_EOL
		;

		if ($failed || $successful) {
			$msg .= PHP_EOL . "This mail contain 1 attachment for libreoffice and ms-office" . PHP_EOL;
			$attachmentPath = '/tmp/' . date('YmdHi') . '_alert_status.csv';
			$attachment = array($this->generateMailCSV($attachmentPath, $events));
		} else {
			$attachment = array();
		}

		$ret = $this->sendMail("NRTRDE status " . date(Billrun_Base::base_dateformat), $msg, Billrun_Factory::config()->getConfigValue('emailAlerts.alerts.recipients', array()), $attachment);

		if (count($attachment) && file_exists($attachmentPath)) {
			@unlink($attachmentPath);
		}

		return $ret;
	}

	/**
	 * send  processing results by email.
	 */
	protected function getProcessingSummary($logs) {
		Billrun_Log::getInstance()->log("Generate Processing result to email", Zend_Log::INFO);

		$msg = "";
		foreach ($logs as $type => $val) {

			if (isset($val['last_processed'])) {
				$seq = $this->getFileSequenceData($val['last_processed']['file_name'], $type);
				$msg .= strtoupper($type) . " last processed Index: " . $seq['seq']
					. " processing date: " . $val['last_processed']['process_time'] . PHP_EOL;
			} else {
				$msg .= strtoupper($type) . " no processed files " . PHP_EOL;
			}
		}
		return $msg;
	}

	/**
	 * send  processing results by email.
	 */
	protected function sendProcessingSummary($logs) {
		Billrun_Log::getInstance()->log("Generate Processing result to email", Zend_Log::INFO);
		$emailMsg = "";
		$email_noc_recipients = Billrun_Factory::config()->getConfigValue('emailAlerts.alerts.noc.recipients', array());
		$date = date(Billrun_Base::base_dateformat);
		foreach ($logs as $type => $val) {
			$name = strtoupper($type);
			if (!isset($val['last_received'])) {
				$emailMsg .= strtoupper($type) . " no files were processed or recevied";
				continue;
			}
			if ($val['alert']) {
				$smsMsg = "ALERT! : didn't processed $name longer then the configured time";
				$emailMsg .= $smsMsg . PHP_EOL . PHP_EOL;
				$this->sendSmsOnFailure($smsMsg);
				$this->sendMail("NRTRDE ALERT " . $date, $emailMsg, $email_noc_recipients);
			} else if ($val['warning']) {
				$smsMsg = "WARNNING! : it seems the server stopped processing $name";
				$emailMsg .= $smsMsg . PHP_EOL . PHP_EOL;
				$this->sendSmsOnFailure($smsMsg);
				$this->sendMail("NRTRDE WARNING " . $date, $emailMsg, $email_noc_recipients);
			}
			if (Billrun_Factory::config()->getConfigValue('emailAlerts.processing.send_report_regularly', false)) {
				$seq = $this->getFileSequenceData($val['last_received']['file_name'], $type);
				$emailMsg .= strtoupper($type) . " recevied Index : " . $seq['seq'] . " receving date : " . $val['last_received']['received_time'] . PHP_EOL;
			}
		}
		if (!$emailMsg) {
			return false;
		}
		$email_recipients = Billrun_Factory::config()->getConfigValue('emailAlerts.processing.recipients', array());
		return $this->sendMail("Processing status " . $date, $emailMsg, $email_recipients);
	}

	/**
	 * Send Email helper
	 * @param type $subject the subject of the message.
	 * @param type $body the body of the message
	 * @param type $attachments (optional) Zend_Mime that hold the attachment files.
	 * @return type
	 */
	protected function sendMail($subject, $body, $recipients = array(), $attachments = array()) {
		$recipients = $this->isDryRun ? array('eran', 'ofer') : array_merge($this->commonRecipients, $recipients);

		return Billrun_Util::sendMail($subject, $body, $recipients, $attachments);
	}

	/**
	 * Sends warning sms to the recipients
	 * @param string $msg
	 * @return array
	 */
	protected function sendSmsOnFailure($msg) {
		$recipients = Billrun_Factory::config()->getConfigValue('smsAlerts.processing.recipients', array());
		return Billrun_Util::sendSms($msg, $recipients);
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

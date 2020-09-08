<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a prototype for an email sender.
 *
 */
abstract class Billrun_EmailSender_Base {
	
	protected $type = '';
	protected $params = array();
	
	public function __construct($params = array()) {
		$this->params = $params;
		$this->type = $this->params['email_type'];
	}
	
	/**
	 * sends all relevant emails
	 * @param $callback - function to be called after email is successfully sent
	 */
	public function notify($callback = false) {
		Billrun_Factory::log('sendEmail - starting to send email of type ' . $this->type);
		if (!$this->shouldNotify()) {
			Billrun_Factory::log('sendEmail - emails should not be sent');
			return false;
		}
                $invoicesCursor = $this->getData();
		foreach ($invoicesCursor as $data) {
			$this->sendEmail($data->getRawData(), $callback);
		}
		Billrun_Factory::log('sendEmail - done sending email for type ' . $this->type);
	}
	
	/**
	 * whether or not emails should be sent
	 * 
	 * @return boolean
	 */
	public function shouldNotify() {
		return true;
	}
	
	/*
	 * whether or not specific email should be sent
	 * 
	 * @return boolean
	 */
	public function shouldSendEmail($data) {
		return true;
	}

	/**
	 * gets data/emails to send
	 * 
	 * @return cursor to the emails to send
	 */
	public abstract function getData();
	
	/**
	 * validates data to send by email
	 * 
	 * @param array $data
	 * @return boolean
	 */
	public function validateData($data) {
		return !empty($data);
	}
	
	/**
	 * gets email attachments
	 * 
	 * @param array $data
	 * @return array OR single attachment
	 */
	public function getAttachment($data) {
		return array();
	}
	
	/**
	 * gets the email address/s to send the email to
	 */
	protected abstract function getEmailAddress($data);
	
	/**
	 * gets email content (HTML body) to send
	 */
	protected abstract function getEmailBody($data);
	
	/**
	 * gets email subject
	 */
	protected abstract function getEmailSubject($data);
	
	/**
	 * translate email message
	 * 
	 * @param string $msg
	 * @param array $data
	 * @return string
	 */
	public function translateMessage($msg, $data = array()) {
		return $msg;
	}
	
	protected function afterSend($data, $callback = false) {
		if ($callback) {
			call_user_func($callback, array($data));
		}
	}


	/**
	 * send 1 email
	 * 
	 * @param array $data
	 * @param $callback - function to be called after email is successfully sent
	 * @return boolean
	 */
	public function sendEmail($data, $callback = false) {
		if (!$this->validateData($data)) {
			return false;
		}
		$attachment = $this->getAttachment($data);
		if($attachment === FALSE) {
			Billrun_Factory::log('sendEmail - error sending email, No attachment data found.  Data: ' . print_R($data, 1), Billrun_Log::ERR);
			return false;
		}
		$attachments = is_array($attachment) ? $attachment : array($attachment);
		$email = $this->getEmailAddress($data);
		$emails = is_array($email) ? $email : array($email);
		$msg = $this->translateMessage($this->getEmailBody($data), $data);
		$subject = $this->translateMessage($this->getEmailSubject($data), $data);
		$encodedSubject = '=?UTF-8?B?'.base64_encode($subject).'?=';
		try {
			if (!Billrun_Util::sendMail($encodedSubject, $msg, $emails, $attachments, true)) {
				Billrun_Factory::log('sendEmail - error sending email. Data: ' . print_R($data, 1), Billrun_Log::ERR);
			}
			$this->afterSend($data, $callback);
			
		} catch (Exception $ex) {
			Billrun_Factory::log('sendEmail - error sending email. Error: "' . $ex->getMessage() . '". Data: ' . print_R($data, 1), Billrun_Log::ERR);
			return false;
		}
		return true;
	}
}

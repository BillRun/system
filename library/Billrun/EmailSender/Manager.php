<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Helper class to manage the email senders.
 *
 */
class Billrun_EmailSender_Manager {

	public static function getInstance($params) {
		$emailSenderClassName = self::getEmailSenderClassName($params);
		if (!class_exists($emailSenderClassName)) {
			Billrun_Factory::log('Sender class not found: ' . $emailSenderClassName, Zend_Log::WARN);
			return false;
		}

		return new $emailSenderClassName($params);
	}

	protected static function getEmailSenderClassName($params) {
		if (!isset($params['email_type'])) {
			return false;
		}
		$senderName = $params['email_type'];
		return 'Billrun_EmailSender_' . ucfirst($senderName);
	}

}

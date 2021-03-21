<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Class used for writing log messages to smtp email via Zend_Mail.
 *
 * @category   Zend
 * @package    Zend_Log
 * @subpackage Writer
 */
class Zend_Log_Writer_Smtp extends Zend_Log_Writer_Mail {

	/**
	 * Create a new instance of Zend_Log_Writer_Mail
	 * Require to override this method only, because parent use self::_constructMailFromConfig and not static::_constructMailFromConfig
	 * @param  array|Zend_Config $config
	 * @return Zend_Log_Writer_Mail
	 */
	static public function factory($config) {
		$config = self::_parseConfig($config);
		$mail = self::_constructMailFromConfig($config);
		$writer = new self($mail);

		if (isset($config['layout']) || isset($config['layoutOptions'])) {
			$writer->setLayout($config);
		}
		if (isset($config['layoutFormatter'])) {
			$layoutFormatter = new $config['layoutFormatter'];
			$writer->setLayoutFormatter($layoutFormatter);
		}
		if (isset($config['subjectPrependText'])) {
			$writer->setSubjectPrependText($config['subjectPrependText']);
		}

		return $writer;
	}

	/**
	 * Construct a Zend_Mail instance based on a configuration array
	 *
	 * @param array $config
	 * @return Zend_Mail
	 * @throws Zend_Log_Exception
	 */
	protected static function _constructMailFromConfig(array $config) {
		$mail = parent::_constructMailFromConfig($config);
		$smtpSettings = $config['transport'];
		$transport = new Zend_Mail_Transport_Smtp($smtpSettings['host'], $smtpSettings);
		$mail->setDefaultTransport($transport);
		return $mail;
	}

}

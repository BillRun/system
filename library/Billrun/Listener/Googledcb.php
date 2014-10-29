<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing Googledcb listener class
 *
 * @package  Billing
 * @since    0.5
 */
class Billrun_Listener_Googledcb extends Billrun_Listener {

	/**
	 * Type of object
	 *
	 * @var string
	 */
	static protected $type = 'listener';
	
	/**
	 *
	 * @var Billrun_SmppClient
	 */
	protected $smppCLient;

	/**
	 * The tokens API URL
	 * @var string
	 */
	protected $tokensApiUrl;


	public function __construct($options = array()) {
		parent::__construct($options);
		$this->smppCLient = Billrun_Factory::smpp_client($options['listener']['smpp']);
		$this->tokensApiUrl = $options['listener']['tokensApiUrl'];
	}

	/**
	 * general function to listen
	 */
	public function listen() {
		Billrun_Factory::log()->log('Waiting for sms...',  Zend_Log::DEBUG);
		return $this->smppCLient->readSms();
	}

	/**
	 * 
	 */
	public function doAfterListen($data) {
		if (!$data instanceof SmppSms) {
			return FALSE;
		}
		$smsContent = $data->message;
		$ndcSn = $data->source->value;
		Billrun_Factory::log()->log('sms received from ' . $ndcSn . ' with message ' . $smsContent,  Zend_Log::DEBUG);
		$params = array('XDEBUG_SESSION_START' => 'netbeans-xdebug');
		$post = array(
			'sms_content' => $smsContent,
			'ndc_sn' => $ndcSn,
		);
		if (Billrun_Util::forkProcessWeb($this->tokensApiUrl, $params, $post)) {
			return TRUE;
		}
	}
}

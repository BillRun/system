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
	 * The billrun hostname
	 * @var string
	 */
	protected $billrunHost;


	public function __construct($options = array()) {
		parent::__construct($options);
		$this->smppCLient = Billrun_Factory::smpp_client($options['listener']['smpp']);
		$this->billrunHost = $options['listener']['billrunHost'];
	}

	/**
	 * general function to listen
	 */
	public function listen() {
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
//		$smsContent = 'djfgbv:875a978e9f79d9c';
//		$ndcSn = '972547655380';
		Billrun_Factory::log()->log('sms received from ' . $ndcSn . ' with message ' . $smsContent,  Zend_Log::DEBUG);
		$url = 'http://' . $this->billrunHost . '/api/tokens';
		$params = array('XDEBUG_SESSION_START' => 'netbeans-xdebug');
		$post = array(
			'sms_content' => $smsContent,
			'ndc_sn' => $ndcSn,
		);
		if (Billrun_Util::forkProcessWeb($url, $params, $post)) {
//			sleep(500);
			return TRUE;
		}
	}
}

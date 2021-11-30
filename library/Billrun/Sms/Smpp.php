<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing sending Sms alerts
 *
 * @package  Sms
 * @since    5.13
 * 
 */
class Billrun_Sms_Smpp extends Billrun_Sms_Abstract {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'smpp';
	
	/**
	 * host or hosts separated with comma
	 * @var string
	 */
	protected $host;
	
	/**
	 * port or ports separated with comma
	 * @var type
	 */
	protected $port;
	
	/**
	 * the from (number or name) that the sms will be sent on behalf of
	 * @var string
	 */
	protected $from;
	
	/**
	 * authentication user
	 * @var string
	 */
	protected $user;
	
	/**
	 * authentication token or password
	 * @var mixed
	 */
	protected $token;
	
	/**
	 * the transport socket
	 * 
	 * @var resource or object
	 */
	protected $transportSocket;
	
	/**
	 * the smpp client
	 * 
	 * @var smpp\Client
	 */
	protected $smppClient;
	
	/**
	 * running the smpp layer with debugging
	 * 
	 * @var boolean
	 */
	protected $debug = false;
	
	public function getFrom() {
		return $this->from;
	}
	
	public function setFrom($from) {
		$this->setFrom($from);
	}
	
	protected function init($params) {
		parent::init($params);
		if (!class_exists('\smpp\transport\Socket')) {
			Billrun_Factory::log('initialize smpp failed as they smpp layer classes not exists', Zend_Log::ERR);
			return;
		}
		// Construct transport and client
		$hosts = explode(',', $this->host);
		$ports = explode(',', $this->port);
		$this->transportSocket = new \smpp\transport\Socket($hosts, $ports, true);
		$this->transportSocket->setRecvTimeout(10000);
		$this->transportSocket->setSendTimeout(10000);
//		smpp\Client::$csmsMethod = smpp\Client::CSMS_PAYLOAD;
//		smpp\Client::$smsRegisteredDeliveryFlag = \smpp\SMPP::REG_DELIVERY_SMSC_BOTH;
		$this->smppClient = new smpp\Client($this->transportSocket);
		
		// Optional connection specific overrides
		smpp\Client::$smsNullTerminateOctetstrings = false;
		smpp\Client::$csmsMethod = smpp\Client::CSMS_PAYLOAD;
//			smpp\Client::$csmsMethod = smpp\Client::CSMS_8BIT_UDH;
		smpp\Client::$smsRegisteredDeliveryFlag = \smpp\SMPP::REG_DELIVERY_SMSC_BOTH;


		// Activate binary hex-output of server interaction
		 $this->smppClient->debug = $this->debug;
		 $this->transportSocket->debug = $this->debug;

	}

	/**
	 * see parent::send
	 * 
	 * @return mixed msg id if success, false on failure
	 */
	public function send() {
 		Billrun_Factory::log('Sending SMPP SMS to: ' . $this->to . ' content: ' . $this->body, Zend_Log::DEBUG);
		try {
			if (!class_exists('\smpp\transport\Socket')) {
				Billrun_Factory::log('initialize smpp failed as they smpp layer classes not exists', Zend_Log::ERR);
				return;
			}
			
			if (empty($this->body)) {
				Billrun_Factory::log('SMS: need to set sms body', Zend_Log::NOTICE);
				return false;
			}
			
			if (empty($this->to)) {
				Billrun_Factory::log('SMS: need to set sms destination (to)', Zend_Log::NOTICE);
				return false;
			}

			// Open the connection
			$this->transportSocket->open();
			$this->smppClient->bindTransmitter($this->user, $this->token);

			// Encoding message
			$encodedMessage = $this->body;

			// Send
			$from = new smpp\Address($this->from, \smpp\SMPP::TON_INTERNATIONAL);
			$toSmpp = new smpp\Address($this->to, \smpp\SMPP::TON_INTERNATIONAL, \smpp\SMPP::NPI_E164);
			$output = $this->smppClient->sendSMS($from, $toSmpp, $encodedMessage, null, \smpp\SMPP::DATA_CODING_UCS2);

			// Close connection
			$this->smppClient->close();
			
			// reset to to avoid double sending on the upper layer so we are resetting the to field
			$this->to = '';
		} catch (Exception $ex) {
			Billrun_Factory::log('Send SMPP SMS: got exception. code: ' . $ex->getCode() . ', message: ' . $ex->getMessage(), Zend_Log::WARN);
			$output = false;
		}
		
		return $output;
	}

}

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
	 * array of client options
	 * 
	 * @var array
	 */
	protected $clientOptions = array(
//		'smsNullTerminateOctetstrings' => 0,
//		'smsRegisteredDeliveryFlag' => \smpp\SMPP::REG_DELIVERY_SMSC_BOTH,
//		'csmsMethod' => smpp\Client::CSMS_PAYLOAD,
//		'messageEncoding' => \smpp\SMPP::DATA_CODING_UCS2,
//		'addressNPI' => \smpp\SMPP::NPI_E164,
//		'addressTON' => \smpp\SMPP::TON_INTERNATIONAL,
//		'utf8togsm' => 0,
	);

	/**
	 * socket time out in milliseconds
	 * 
	 * @var int
	 */
	protected $socketTimeout = 3000;

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
		try {

			if (!class_exists('\smpp\transport\Socket')) {
				Billrun_Factory::log('initialize smpp failed as they smpp layer classes not exists', Zend_Log::ERR);
				return;
			}
			// Construct transport and client
			$hosts = explode(',', $this->host);
			$ports = explode(',', $this->port);
			if (empty($hosts)) {
				Billrun_Factory::log('initialize smpp failed as host is empty', Zend_Log::ERR);
				return;
			}

			if (empty($ports)) {
				$ports = array('1775');
			}

			$this->transportSocket = new \smpp\transport\Socket($hosts, $ports, true);
			$this->transportSocket->setRecvTimeout($this->socketTimeout);
			$this->transportSocket->setSendTimeout($this->socketTimeout);
			$this->smppClient = new smpp\Client($this->transportSocket);

			// Optional connection specific overrides
			if (!empty($this->clientOptions['smsNullTerminateOctetstrings'])) {
				smpp\Client::$smsNullTerminateOctetstrings = $this->getClassConstant('smpp\Client', $this->clientOptions['clientOptions']['smsNullTerminateOctetstrings']);
			} else {
				smpp\Client::$smsNullTerminateOctetstrings = 0;
			}

			if (!empty($this->clientOptions['csmsMethod'])) {
				smpp\Client::$csmsMethod = $this->getClassConstant('smpp\Client', $this->clientOptions['csmsMethod']);
			} else {
				smpp\Client::$csmsMethod = smpp\Client::CSMS_PAYLOAD;
			}

			if (!empty($this->clientOptions['smsRegisteredDeliveryFlag'])) {
				smpp\Client::$smsRegisteredDeliveryFlag = $this->getClassConstant('\smpp\SMPP', $this->clientOptions['smsRegisteredDeliveryFlag']);
			} else {
				smpp\Client::$smsRegisteredDeliveryFlag = \smpp\SMPP::REG_DELIVERY_SMSC_BOTH;
			}

			if (!empty($this->clientOptions['addressTON'])) {
				$this->clientOptions['addressTON'] = $this->getClassConstant('\smpp\SMPP', $this->clientOptions['addressTON']);
			} else {
				$this->clientOptions['addressTON'] = \smpp\SMPP::TON_INTERNATIONAL;
			}

			if (!empty($this->clientOptions['addressNPI'])) {
				$this->clientOptions['addressNPI'] = $this->getClassConstant('\smpp\SMPP', $this->clientOptions['addressNPI']);
			} else {
				$this->clientOptions['addressNPI'] = \smpp\SMPP::NPI_E164;
			}

			if (!empty($this->clientOptions['messageEncoding'])) {
				$this->clientOptions['messageEncoding'] = $this->getClassConstant('\smpp\SMPP', $this->clientOptions['messageEncoding']);
			} else {
				$this->clientOptions['messageEncoding'] = \smpp\SMPP::DATA_CODING_UCS2;
			}

			if (empty($this->clientOptions['utf8togsm'])) {
				$this->clientOptions['utf8togsm'] = 0;
			}

			// Activate binary hex-output of server interaction
			$this->smppClient->debug = $this->debug;
			$this->transportSocket->debug = $this->debug;
		} catch (Throwable $th) {
			Billrun_Factory::log('Send SMPP SMS: got exception. code: ' . $th->getCode() . ', message: ' . $th->getMessage(), Zend_Log::WARN);
		} catch (Exception $ex) {
			Billrun_Factory::log('initialize smpp failed as they smpp layer classes not exists', Zend_Log::ERR);
		}
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

			if ($this->clientOptions['utf8togsm']) {
				$encodedMsg = smpp\helpers\GsmEncoderHelper::utf8_to_gsm0338($this->body);
			} else {
				$encodedMsg = $this->body;
			}
			// Send
			$from = new smpp\Address($this->from, $this->clientOptions['addressTON']);
			$toSmpp = new smpp\Address($this->to, $this->clientOptions['addressTON'], $this->clientOptions['addressNPI']);
			$output = $this->smppClient->sendSMS($from, $toSmpp, $encodedMsg, null, $this->clientOptions['messageEncoding']);

			// Close connection
			$this->smppClient->close();

			// reset to to avoid double sending on the upper layer so we are resetting the to field
			$this->to = '';
		} catch (Throwable $th) {
			Billrun_Factory::log('Send SMPP SMS: got exception. code: ' . $th->getCode() . ', message: ' . $th->getMessage(), Zend_Log::WARN);
			$output = false;
		} catch (Exception $ex) {
			Billrun_Factory::log('Send SMPP SMS: got exception. code: ' . $ex->getCode() . ', message: ' . $ex->getMessage(), Zend_Log::WARN);
			$output = false;
		}

		return $output;
	}

	/**
	 * method to get variable from class constants; on some case the variable input is the value itself
	 * 
	 * @param string $class
	 * @param mixed $var
	 * 
	 * @return the constant value
	 */
	protected function getClassConstant($class, $var) {
		if (is_numeric($var) || is_bool($var)) {
			return $var;
		}
		return constant($class . '::' . $var);
	}

}

<?php
require_once APPLICATION_PATH . '/library/php-smpp-master/smppclient.class.php';
require_once APPLICATION_PATH . '/library/php-smpp-master/sockettransport.class.php';

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing receiving messages from smpp server(s)
 *
 * @since    2.1
 * 
 */
class Billrun_SmppClient {

	/**
	 *
	 * @var SocketTransport
	 */
	protected $transport;

	/**
	 *
	 * @var SmppClient
	 */
	protected $smpp;

	public function __construct($options = array()) {
		$this->transport = new SocketTransport($options['hosts'], $options['port']);
		$this->transport->setRecvTimeout($options['timeout']);
		$this->smpp = new SmppClient($this->transport);
		$this->transport->open();
		$this->smpp->bindReceiver($options['user'], $options['password']);
	}

	public function __destruct() {
		$this->smpp->close();
	}
	
	public function readSms() {
		return $this->smpp->readSMS();
	}

}
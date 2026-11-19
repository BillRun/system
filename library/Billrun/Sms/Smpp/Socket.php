<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * SMPP socket transport compatible with PHP 8+
 *
 * Since PHP 8.0 socket_create() returns a \Socket object instead of a resource.
 * The upstream library (alexandr-mironov/php-smpp, incl. latest 1.3.0.9) checks
 * is_resource() in isOpen(), so the transport is always considered closed and
 * bind/submit silently do nothing.
 *
 * @package  Sms
 * @since    6.0
 */
class Billrun_Sms_Smpp_Socket extends \smpp\transport\Socket {

	/**
	 * Check if the socket is constructed, and there are no exceptions on it
	 * Returns false if it's closed.
	 * Throws SocketTransportException is state could not be ascertained
	 *
	 * @return boolean
	 * @throws \smpp\exceptions\SocketTransportException
	 */
	public function isOpen() {
		if (!($this->socket instanceof \Socket) && !is_resource($this->socket)) {
			return false;
		}
		$r = null;
		$w = null;
		$e = array($this->socket);
		$res = socket_select($r, $w, $e, 0);
		if ($res === false) {
			throw new \smpp\exceptions\SocketTransportException(
				'Could not examine socket; ' . socket_strerror(socket_last_error()),
				socket_last_error()
			);
		}
		if (!empty($e)) {
			return false; // if there is an exception on our socket it's probably dead
		}
		return true;
	}

}

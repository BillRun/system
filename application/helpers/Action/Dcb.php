<?php

require_once APPLICATION_PATH . '/application/helpers/Dcb/Soap/Handler.php';
require_once APPLICATION_PATH . '/application/helpers/Dcb/Soap/Server.php';

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Direct Carrier Billing action class
 */
class DcbAction extends Action_Base {

	protected $wsdlPath;
	protected $externalCerts;
	protected $billrunPemPath;

	public function init() {
		$this->wsdlPath = Billrun_Factory::config()->getConfigValue('googledcb.wsdl');
		$this->externalCerts = Billrun_Factory::config()->getConfigValue('googledcb.externalCerts');
		$this->billrunPemPath = Billrun_Factory::config()->getConfigValue('googledcb.billrunPemPath');
	}

	/**
	 * @todo maybe use output method instead of dying
	 * @todo is there a way to get rid of the require_once?
	 */
	public function execute() {
		$this->init();
		$secured_soap_server = new Soap_Server_WSSE($this->wsdlPath, array('soap_version' => SOAP_1_1));
		$secured_soap_server->setClass('Dcb_Soap_Handler');
		$secured_soap_server->setReturnResponse(true);
		$options = array(
			'external_certificates_paths' => $this->externalCerts,
			'server_pem_path' => $this->billrunPemPath,
			'verify_body_signature' => TRUE,
		);
		$secured_soap_server->setOptions($options);
		$result = $secured_soap_server->handle();
		die($result);
	}

}

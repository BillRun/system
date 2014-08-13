<?php

require_once APPLICATION_PATH . '/application/helpers/Dcb/Soap/Handler.php';
require_once APPLICATION_PATH . '/library/wse-php/soap-server-wsse.php';

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Direct Carrier Billing action class
 */
class DcbAction extends Action_Base {

	/**
	 * @todo maybe use output method instead of dying
	 * @todo is there a way to get rid of the require_once?
	 */
	public function execute() {
//		$wsdl_path = '/home/shani/projects/billrun/application/helpers/Dcb/Soap/CarrierBilling_3.wsdl';
//		$soap = new Zend_Soap_Server($wsdl_path, array('soap_version' => SOAP_1_1));
//		$soap->setClass('Dcb_Soap_Handler');
//		$soap->setReturnResponse(true);
//		$result = $soap->handle();
		
		$doc = new DOMDocument('1.0');
		$request = file_get_contents('php://input');
		$doc->loadXML($request);
		
		$objWSSEServer = new WSSESoapServer($doc);
		$objWSSEServer->addExternalCertificates(array('/home/shani/keys/clientPemFile.pem'));
		
		$objWSSEServer->process();
		
		$objWSSEServer->addTimestamp(600);

		$SERVER_KEY = '/home/shani/keys/serverPemFile.pem';
		$objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type' => 'private'));
		$objKey->loadKey($SERVER_KEY, TRUE, TRUE);

		$options = array("insertBefore" => TRUE);
		$objWSSEServer->signSoapDoc($objKey, $options);

		die($objWSSEServer->saveXML());
	}

}

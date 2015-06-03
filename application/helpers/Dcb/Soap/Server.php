<?php

require_once APPLICATION_PATH . '/library/wse-php/soap-server-wsse.php';

/**
 * Dcb Soap Handler Class 
 * 
 * 
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero Public License version 3 or later; see LICENSE.txt
 */

/**
 * Dcb_Soap_Handler Class Definition
 */
class Soap_Server_WSSE extends Zend_Soap_Server {

	/**
	 * Server which will handle the security manners
	 * @var WSSESoapServer 
	 */
	protected $WSSEServer;

	public function __construct($wsdl = null, array $options = null) {
		parent::__construct($wsdl, $options);

		$doc = new DOMDocument('1.0');
		$request = file_get_contents('php://input');
		$doc->loadXML($request);
		$this->WSSEServer = new WSSESoapServer($doc);
	}

	/**
	 * 
	 * @param type $request
	 * @return type
	 */
	public function handle($request = null) {
		$result = '';
		try {
			// verify the request
			$this->WSSEServer->process();

			// handle body
			$result = parent::handle($this->WSSEServer->saveXML());

			// sign the response
			if ($serverPemPath = $this->WSSEServer->getServerPem()) {
				$signatureValue = $this->WSSEServer->getSignatureValue();
				$doc = new DOMDocument('1.0');
				$doc->loadXML($result);
				$this->WSSEServer = new WSSESoapServer($doc);
				$this->WSSEServer->addSignatureConfirmation($signatureValue);
				$this->WSSEServer->addTimestamp(600);

				$objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type' => 'private'));
				$objKey->loadKey($serverPemPath, TRUE, TRUE);

				$options = array("insertBefore" => TRUE);
				$this->WSSEServer->signSoapDoc($objKey, $options);
				$result = $this->WSSEServer->saveXML();
			}
		} catch (Exception $ex) {
			$soap = $this->_getSoap();
			$soap->fault('Sender', $ex->getMessage());
		}
		return $result;
	}
	
	public function setOptions($options) {
		parent::setOptions($options);
		if (isset($options['external_certificates_paths'])) {
			$this->WSSEServer->addExternalCertificates($options['external_certificates_paths']);
		}
		if (isset($options['server_pem_path'])) {
			$this->WSSEServer->setServerPem($options['server_pem_path']);
		}
		if (isset($options['verify_body_signature'])) {
			$this->WSSEServer->verifyBodySignature = $options['verify_body_signature'];
		}
		return $this;
	}
}


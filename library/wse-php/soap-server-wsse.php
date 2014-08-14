<?php

/**
 * soap-server-wsse.php 
 * 
 * Copyright (c) 2007, Robert Richards <rrichards@ctindustries.net>. 
 * All rights reserved. 
 * 
 * Redistribution and use in source and binary forms, with or without 
 * modification, are permitted provided that the following conditions 
 * are met: 
 * 
 *   * Redistributions of source code must retain the above copyright 
 *     notice, this list of conditions and the following disclaimer. 
 * 
 *   * Redistributions in binary form must reproduce the above copyright 
 *     notice, this list of conditions and the following disclaimer in 
 *     the documentation and/or other materials provided with the 
 *     distribution. 
 * 
 *   * Neither the name of Robert Richards nor the names of his 
 *     contributors may be used to endorse or promote products derived 
 *     from this software without specific prior written permission. 
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS 
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT 
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS 
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE 
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, 
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, 
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; 
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER 
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT 
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN 
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE 
 * POSSIBILITY OF SUCH DAMAGE. 
 * 
 * @author     Robert Richards <rrichards@ctindustries.net> 
 * @copyright  2007 Robert Richards <rrichards@ctindustries.net> 
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License 
 * @version    1.0.0 
 */
require('xmlseclibs.php');

class WSSESoapServer {

	const WSSENS = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
	const WSSENS_2003 = 'http://schemas.xmlsoap.org/ws/2003/06/secext';
	const WSUNS = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';
	const WSSE11 = 'http://docs.oasis-open.org/wss/oasis-wss-wssecurity-secext-1.1.xsd';
	const WSSEPFX = 'wsse';
	const WSSE11PFX = 'wsse11';
	const WSUPFX = 'wsu';

	private $soapNS, $soapPFX;
	private $soapDoc = NULL;
	private $envelope = NULL;
	private $SOAPXPath = NULL;
	private $secNode = NULL;
	public $signAllHeaders = FALSE;
	
	/**
	 *
	 * @var array
	 */
	protected $externalCertificates;
	
	/**
	 *
	 * @var string
	 */
	protected $signatureValue;
	
	protected $serverPemPath;

	private function locateSecurityHeader($setActor=NULL) { 
        $wsNamespace = NULL; 
        if ($this->secNode == NULL) { 
            $headers = $this->SOAPXPath->query('//wssoap:Envelope/wssoap:Header'); 
            if ($header = $headers->item(0)) { 
                $secnodes = $this->SOAPXPath->query('./*[local-name()="Security"]', $header); 
                $secnode = NULL; 
                foreach ($secnodes AS $node) { 
                    $nsURI = $node->namespaceURI; 
                    if (($nsURI == self::WSSENS) || ($nsURI == self::WSSENS_2003)) { 
                        $actor = $node->getAttributeNS($this->soapNS, 'actor'); 
                        if (empty($actor) || ($actor == $setActor)) { 
                            $secnode = $node; 
                            $wsNamespace = $nsURI; 
                            break; 
                        } 
                    } 
                } 
            } 
            $this->secNode = $secnode; 
        } 
        return $wsNamespace; 
    }  

	public function __construct($doc) {
		$this->soapDoc = $doc;
		$this->envelope = $doc->documentElement;
		$this->soapNS = $this->envelope->namespaceURI;
		$this->soapPFX = $this->envelope->prefix;
		$this->SOAPXPath = new DOMXPath($doc);
		$this->SOAPXPath->registerNamespace('wssoap', $this->soapNS);
		$this->SOAPXPath->registerNamespace('wswsu', WSSESoapServer::WSUNS);
		$wsNamespace = $this->locateSecurityHeader();
		if (!empty($wsNamespace)) {
			$this->SOAPXPath->registerNamespace('wswsse', $wsNamespace);
		}
	}

	public function processSignature($refNode) {
		$objXMLSecDSig = new XMLSecurityDSig();
		$objXMLSecDSig->idKeys[] = 'wswsu:Id';
		$objXMLSecDSig->idNS['wswsu'] = WSSESoapServer::WSUNS;
		$objXMLSecDSig->sigNode = $refNode;

		/* Canonicalize the signed info */
		$objXMLSecDSig->canonicalizeSignedInfo();

		$retVal = $objXMLSecDSig->validateReference();

		if (!$retVal) {
			throw new Exception("Validation Failed");
		}

		$key = NULL;
		$objKey = $objXMLSecDSig->locateKey();

		if ($objKey) {
			if ($objKeyInfo = XMLSecEnc::staticLocateKeyInfo($objKey, $refNode)) {
				/* Handle any additional key processing such as encrypted keys here */
			}
		}

		if (empty($objKey)) {
			throw new Exception("Error loading key to handle Signature");
		}
		do {
			if (empty($objKey->key)) {
				$this->SOAPXPath->registerNamespace('xmlsecdsig', XMLSecurityDSig::XMLDSIGNS);
				$query = "./xmlsecdsig:KeyInfo/wswsse:SecurityTokenReference/wswsse:Reference";
				$nodeset = $this->SOAPXPath->query($query, $refNode);
				if ($encmeth = $nodeset->item(0)) {
					if ($uri = $encmeth->getAttribute("URI")) {
						$arUrl = parse_url($uri);
						if (empty($arUrl['path']) && ($identifier = $arUrl['fragment'])) {
							$query = '//wswsse:BinarySecurityToken[@wswsu:Id="' . $identifier . '"]';
							$nodeset = $this->SOAPXPath->query($query);
							if ($encmeth = $nodeset->item(0)) {
								$x509cert = $encmeth->textContent;
								$x509cert = str_replace(array("\r", "\n"), "", $x509cert);
								$x509cert = "-----BEGIN CERTIFICATE-----\n" . chunk_split($x509cert, 64, "\n") . "-----END CERTIFICATE-----\n";
								$objKey->loadKey($x509cert);
								break;
							}
						}
					}
				} else if ($this->externalCertificates) {
					foreach ($this->externalCertificates as $certPath) {
						if (file_exists($certPath)) {
							openssl_x509_export(openssl_x509_read(file_get_contents($certPath)), $x509cert);
							$objKey->loadKey($x509cert);
							if ($objXMLSecDSig->verify($objKey)) {
								$this->signatureValue = $objXMLSecDSig->getSignatureValue();
								return TRUE;
							}
						}
					}
					throw new Exception("Unable to validate Signature");
				}
				throw new Exception("Error loading key to handle Signature");
			}
		} while (0);

		if (!$objXMLSecDSig->verify($objKey)) {
			throw new Exception("Unable to validate Signature");
		}

		return TRUE;
	}
	
	public function getSignatureValue() {
		return $this->signatureValue;
	}

	public function process() {
		if (empty($this->secNode)) {
			return;
		}
		$node = $this->secNode->firstChild;
		while ($node) {
			$nextNode = $node->nextSibling;
			switch ($node->localName) {
				case "Signature":
					if ($this->processSignature($node)) {
						if ($node->parentNode) {
							$node->parentNode->removeChild($node);
						}
					} else {
						/* throw fault */
						return FALSE;
					}
			}
			$node = $nextNode;
		}
		$this->secNode->parentNode->removeChild($this->secNode);
		$this->secNode = NULL;
		return TRUE;
	}

	public function saveXML() {
		return $this->soapDoc->saveXML();
	}

	public function save($file) {
		return $this->soapDoc->save($file);
	}

	/**
	 * array of paths to external certificates that may be used to verify signatures
	 * @param type $paths
	 */
	public function addExternalCertificates($paths) {
		$this->externalCertificates = $paths;
	}
	
	public function signSoapDoc($objKey, $options = NULL) {
        $objDSig = new XMLSecurityDSig(); 

        $objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N); 

        $arNodes = array(); 
        foreach ($this->envelope->childNodes AS $node) { 
            if ($node->namespaceURI == $this->soapNS && $node->localName == 'Body') { 
                $arNodes[] = $node; 
                break; 
            } 
        }
        foreach ($this->secNode->childNodes AS $node) { 
            if ($node->nodeType == XML_ELEMENT_NODE) { 
                $arNodes[] = $node; 
            } 
        } 

        if ($this->signAllHeaders) { 
            foreach ($this->secNode->parentNode->childNodes AS $node) { 
                if (($node->nodeType == XML_ELEMENT_NODE) &&  
                ($node->namespaceURI != WSSESoap::WSSENS)) { 
                    $arNodes[] = $node; 
                } 
            } 
        } 

        
        $algorithm = XMLSecurityDSig::SHA1;
        if (is_array($options) && isset($options["algorithm"])) {
            $algorithm = $options["algorithm"];
        }
      
        $arOptions = array('prefix'=>  WSSESoapServer::WSUPFX, 'prefix_ns'=>  WSSESoapServer::WSUNS); 
        $objDSig->addReferenceList($arNodes, $algorithm, NULL, $arOptions); 

        $objDSig->sign($objKey); 
		
        $insertTop = TRUE;
        if (is_array($options) && isset($options["insertBefore"])) {
            $insertTop = (bool)$options["insertBefore"];
        }

		$sigNode = $this->secNode->firstChild->nextSibling;
		$objDoc = $sigNode->ownerDocument;
		$keyInfo = $objDoc->createElementNS(XMLSecurityDSig::XMLDSIGNS, 'ds:KeyInfo');

		$x509DataNode = $objDoc->createElementNS(XMLSecurityDSig::XMLDSIGNS, 'ds:X509Data');
		$x509IssuerSerialNode = $objDoc->createElementNS(XMLSecurityDSig::XMLDSIGNS, 'ds:X509IssuerSerial');

		$cert = openssl_x509_parse($objKey->getX509Certificate());
		$issuerName = array();
		foreach ($cert['issuer'] as $key => $value) {
			$issuerName[]=$key . '=' . str_replace(',', '\,', $value);
		}
		$x509IssuerNameNode = $objDoc->createElementNS(XMLSecurityDSig::XMLDSIGNS, 'ds:X509IssuerName');
		$x509SerialNumberNode = $objDoc->createElementNS(XMLSecurityDSig::XMLDSIGNS, 'ds:X509SerialNumber');
		$x509IssuerNameTextNode = new DOMText(implode(',', $issuerName));
		$x509SerialNumberTextNode = new DOMText($cert['serialNumber']);
		$x509IssuerNameNode->appendChild($x509IssuerNameTextNode);
		$x509SerialNumberNode->appendChild($x509SerialNumberTextNode);
		
		$x509IssuerSerialNode->appendChild($x509IssuerNameNode);
		$x509IssuerSerialNode->appendChild($x509SerialNumberNode);

		$x509DataNode->appendChild($x509IssuerSerialNode);

		$tokenRef = $objDoc->createElementNS(WSSESoapServer::WSSENS, WSSESoapServer::WSSEPFX . ':SecurityTokenReference');
		$tokenRef->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . WSSESoapServer::WSUPFX, WSSESoapServer::WSUNS);
		$tokenRef->appendChild($x509DataNode);
		
		$keyInfo->appendChild($tokenRef);
		
		$copiedKey = $objDSig->sigNode->ownerDocument->importNode($keyInfo, true);
		$objDSig->sigNode->appendChild($copiedKey);
        $objDSig->appendSignature($this->secNode, $insertTop);
    }
	
    public function addTimestamp($secondsToExpire=3600) {
        /* Add the WSU timestamps */ 
		if (!$this->secNode) {
			$security = $this->locateSecurityHeader(); 
		}
		else {
			$security = $this->secNode;
		}

        $timestamp = $this->soapDoc->createElementNS(WSSESoapServer::WSUNS, WSSESoapServer::WSUPFX.':Timestamp'); 
        $security->insertBefore($timestamp, $security->firstChild); 
        $currentTime = time(); 
        $created = $this->soapDoc->createElementNS(WSSESoapServer::WSUNS,  WSSESoapServer::WSUPFX.':Created', gmdate("Y-m-d\TH:i:s", $currentTime).'Z'); 
        $timestamp->appendChild($created); 
        if (! is_null($secondsToExpire)) { 
            $expire = $this->soapDoc->createElementNS(WSSESoapServer::WSUNS,  WSSESoapServer::WSUPFX.':Expires', gmdate("Y-m-d\TH:i:s", $currentTime + $secondsToExpire).'Z'); 
            $timestamp->appendChild($expire); 
        } 
    } 
    public function addSignatureConfirmation($signatureValue) {
        /* Add the WSU timestamps */ 
        $security = $this->createSecurityIfNotExists(); 

        $signatureConfirmation = $this->soapDoc->createElementNS(WSSESoapServer::WSSE11, WSSESoapServer::WSSE11PFX.':SignatureConfirmation'); 
		
        $signatureConfirmation->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . WSSESoapServer::WSSE11PFX, WSSESoapServer::WSSE11);
        $signatureConfirmation->setAttribute('value', $signatureValue);
        $security->appendChild($signatureConfirmation);
    } 
	
	private function createSecurityIfNotExists($bMustUnderstand = TRUE, $setActor = NULL) { 
        if ($this->secNode == NULL) { 
            $headers = $this->SOAPXPath->query('//wssoap:Envelope/wssoap:Header'); 
            $header = $headers->item(0); 
            if (! $header) { 
                $header = $this->soapDoc->createElementNS($this->soapNS, $this->soapPFX.':Header'); 
                $this->envelope->insertBefore($header, $this->envelope->firstChild); 
            } 
            $secnodes = $this->SOAPXPath->query('./wswsse:Security', $header); 
            $secnode = NULL; 
            foreach ($secnodes AS $node) { 
                $actor = $node->getAttributeNS($this->soapNS, 'actor'); 
                if ($actor == $setActor) { 
                    $secnode = $node; 
                    break; 
                } 
            } 
            if (! $secnode) { 
                $secnode = $this->soapDoc->createElementNS(WSSESoapServer::WSSENS, WSSESoapServer::WSSEPFX.':Security'); 
                $header->appendChild($secnode); 
                if ($bMustUnderstand) { 
                    $secnode->setAttributeNS($this->soapNS, $this->soapPFX.':mustUnderstand', '1'); 
                } 
                if (! empty($setActor)) { 
                    $ename = 'actor'; 
                    if ($this->soapNS == 'http://www.w3.org/2003/05/soap-envelope') { 
                        $ename = 'role'; 
                    } 
                    $secnode->setAttributeNS($this->soapNS, $this->soapPFX.':'.$ename, $setActor); 
                } 
            } 
            $this->secNode = $secnode; 
        } 
        return $this->secNode;
    }
	
	public function setServerPem($pemPath) {
		$this->serverPemPath = $pemPath;
	}
	
	public function getServerPem() {
		if (isset($this->serverPemPath)) {
			return $this->serverPemPath;
		}
		return null;
	}

}


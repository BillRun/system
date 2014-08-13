<?php
require('soap-wsse.php');

define('PRIVATE_KEY', 'priv_key.pem');
define('CERT_FILE', 'pub_key.pem');
define('SERVICE_CERT', 'sitekey_pub.cer');

class mySoap extends SoapClient {

    function __doRequest($request, $location, $saction, $version) {
        $doc = new DOMDocument('1.0');
        $doc->loadXML($request);
        
        $objWSSE = new WSSESoap($doc);
        
        /* add Timestamp with no expiration timestamp */
        $objWSSE->addTimestamp();
        
        /* create new XMLSec Key using AES256_CBC and type is private key */
        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type'=>'private'));
        
        /* load the private key from file - last arg is bool if key in file (TRUE) or is string (FALSE) */
        $objKey->loadKey(PRIVATE_KEY, TRUE);
        
        /* Sign the message - also signs appropiate WS-Security items */
        $options = array("insertBefore" => FALSE);
        $objWSSE->signSoapDoc($objKey, $options);
        
        /* Add certificate (BinarySecurityToken) to the message */
        $token = $objWSSE->addBinaryToken(file_get_contents(CERT_FILE));
        
        /* Attach pointer to Signature */
        $objWSSE->attachTokentoSig($token);
        
        $objKey = new XMLSecurityKey(XMLSecurityKey::AES256_CBC);
        $objKey->generateSessionKey();
        
        $siteKey = new XMLSecurityKey(XMLSecurityKey::RSA_OAEP_MGF1P, array('type'=>'public'));
        $siteKey->loadKey(SERVICE_CERT, TRUE, TRUE);
        
        $options = array("KeyInfo" => array("X509SubjectKeyIdentifier" => true));
        $objWSSE->encryptSoapDoc($siteKey, $objKey, $options);
        
        $retVal = parent::__doRequest($objWSSE->saveXML(), $location, $saction, $version);
        
        $doc = new DOMDocument();
        $doc->loadXML($retVal);
        
        $options = array("keys" => array("private" => array("key" => PRIVATE_KEY, "isFile" => TRUE, "isCert" => FALSE)));
        $objWSSE->decryptSoapDoc($doc, $options);
        
        return $doc->saveXML();
    }
}

$wsdl = <wsdl location>;

$sc = new mySoap($wsdl);

try {
    $out = $sc->callmethod(1);
    var_dump($out);
} catch (SoapFault $fault) {
    var_dump($fault);
}


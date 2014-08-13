<?php
require('../soap-wsse.php');

define('PRIVATE_KEY', './pk-amazon-private-key.pem');
define('CERT_FILE', './cert-amazon-cert.pem');

class mySoap extends SoapClient {

   function __doRequest($request, $location, $saction, $version) {
    $doc = new DOMDocument('1.0');
    $doc->loadXML($request);

    $objWSSE = new WSSESoap($doc);

    /* add Timestamp with no expiration timestamp */
     $objWSSE->addTimestamp();

    /* create new XMLSec Key using RSA SHA-1 and type is private key */
    $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type'=>'private'));

    /* load the private key from file - last arg is bool if key in file (TRUE) or is string (FALSE) */
    $objKey->loadKey(PRIVATE_KEY, TRUE);

    /* Sign the message - also signs appropraite WS-Security items */
    $objWSSE->signSoapDoc($objKey);

    /* Add certificate (BinarySecurityToken) to the message and attach pointer to Signature */
    $token = $objWSSE->addBinaryToken(file_get_contents(CERT_FILE));
    $objWSSE->attachTokentoSig($token);
    return parent::__doRequest($objWSSE->saveXML(), $location, $saction, $version);
   }
}

class instances {
    public $instancesSet = NULL;
}

$wsdl = 'http://s3.amazonaws.com/ec2-downloads/ec2.wsdl';

try {
    $sClient = new mySoap($wsdl, array('trace'=>1));
    /* Force location path - MUST INCLUDE trailing slash
    BUG in ext/soap that does not automatically add / if URL does not contain path cause POST header to be invalid */
    $sClient->location = 'https://ec2.amazonaws.com/';

    $objInstances = new instances();
     $test = $sClient->DescribeInstances($objInstances);
    
     var_dump($test);
} catch (SoapFault $e) {
    var_dump($e);
}


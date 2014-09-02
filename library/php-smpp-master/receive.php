<?php
require_once 'smppclient.class.php';
require_once 'sockettransport.class.php';

// Construct transport and client
$transport = new SocketTransport(array('10.224.228.212'),9999);
$transport->setRecvTimeout(1000); // for this example wait up to 60 seconds for data
$smpp = new SmppClient($transport);

// Activate binary hex-output of server interaction
$smpp->debug = true;
$transport->debug = true;

// Open the connection
$transport->open();
$smpp->bindReceiver("viatel","viatel");

// Read SMS and output
$sms = $smpp->readSMS();
echo "SMS:\n";
var_dump($sms);

// Close connection
$smpp->close();

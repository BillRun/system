<?php

spl_autoload_register(function($class) {
    $root = dirname(__DIR__);
    $classFile = $root . '/lib/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
    }
});

// $instanceName is a part of the url where you access your payrexx installation.
// https://{$instanceName}.payrexx.com
$instanceName = 'YOUR_INSTANCE_NAME';

// $secret is the payrexx secret for the communication between the applications
// if you think someone got your secret, just regenerate it in the payrexx administration
$secret = 'YOUR_SECRET';

$payrexx = new \Payrexx\Payrexx($instanceName, $secret);

$gateway = new \Payrexx\Models\Request\Gateway();
$gateway->setId(1);

try {
    $response = $payrexx->getOne($gateway);
    var_dump($response);
} catch (\Payrexx\PayrexxException $e) {
    print $e->getMessage();
}

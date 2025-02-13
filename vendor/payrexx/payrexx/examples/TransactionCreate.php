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

$transaction = new \Payrexx\Models\Request\Transaction();

// amount multiplied by 100
$transaction->setAmount(89.25 * 100);

// VAT rate percentage (nullable)
$transaction->setVatRate(7.70);

// currency ISO code
$transaction->setCurrency('CHF');

// optional: add contact information which should be stored along with payment
$transaction->addField($type = 'forename', $value = 'Max');
$transaction->addField($type = 'surname', $value = 'Mustermann');
$transaction->addField($type = 'email', $value = 'max.muster@payrexx.com');

try {
    $response = $payrexx->create($transaction);
    var_dump($response);
} catch (\Payrexx\PayrexxException $e) {
    print $e->getMessage();
}

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

// init empty request object
$invoice = new \Payrexx\Models\Request\Invoice();

// info for payment link (reference id)
$invoice->setReferenceId('Order number of my online shop application');

// info for payment page (title, description)
$invoice->setTitle('Online shop payment');
$invoice->setDescription('Thanks for using Payrexx to pay your order');

// administrative information, which provider to use (psp)
// psp #1 = Payrexx' test mode, see http://developers.payrexx.com/docs/miscellaneous
//$invoice->setPsp([]);
//$invoice->setPm(['mastercard']);

// internal data only displayed to administrator
$invoice->setName('Online-Shop payment #001');

// payment information
$invoice->setPurpose('Shop Order #001');
$amount = 5.90;
// don't forget to multiply by 100
$invoice->setAmount($amount * 100);

// custom button text
//$invoice->setButtonText('Pay me');

// VAT rate percentage (nullable)
$vatRate = 7.70;
$invoice->setVatRate($vatRate);

// Product SKU
$sku = 'P01122000';
$invoice->setSku($sku);

// ISO code of currency, list of alternatives can be found here
// http://developers.payrexx.com/docs/miscellaneous
$invoice->setCurrency('CHF');

// Expiration date in format: Y-m-d
$invoice->setExpirationDate('2020-10-03');

// whether charge payment manually at a later date (type authorization)
$invoice->setPreAuthorization(false);

// whether charge payment manually at a later date (type reservation)
$invoice->setReservation(false);

// subscription information if you want the customer to authorize a recurring payment.
// this does not work in combination with pre-authorization payments.
//$invoice->setSubscriptionState(true);
//$invoice->setSubscriptionInterval('P1M');
//$invoice->setSubscriptionPeriod('P1Y');
//$invoice->setSubscriptionCancellationInterval('P3M');

// add contact information fields which should be filled by customer
// it would be great to provide at least an email address field
$invoice->addField($type = 'email', $mandatory = true, $defaultValue = 'my-customer@example.com');
$invoice->addField($type = 'company', $mandatory = true, $defaultValue = 'Ueli Kramer Firma');
$invoice->addField($type = 'forename', $mandatory = true, $defaultValue = 'Ueli');
$invoice->addField($type = 'surname', $mandatory = true, $defaultValue = 'Kramer');
$invoice->addField($type = 'country', $mandatory = true, $defaultValue = 'AT');
$invoice->addField($type = 'title', $mandatory = true, $defaultValue = 'miss');
$invoice->addField($type = 'terms', $mandatory = true);
$invoice->addField($type = 'privacy_policy', $mandatory = true);
$invoice->addField($type = 'custom_field_1', $mandatory = true, $defaultValue = 'Value 001', $name = 'Das ist ein Feld');

// fire request with created and filled link request-object.
try {
    $response = $payrexx->create($invoice);
    var_dump($response);
} catch (\Payrexx\PayrexxException $e) {
    print $e->getMessage();
}

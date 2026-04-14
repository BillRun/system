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

// amount multiplied by 100
$gateway->setAmount(89.25 * 100);

// VAT rate percentage (nullable)
$gateway->setVatRate(7.70);

//Product SKU
$gateway->setSku('P01122000');

// currency ISO code
$gateway->setCurrency('CHF');

//success and failed url in case that merchant redirects to payment site instead of using the modal view
$gateway->setSuccessRedirectUrl('https://www.merchant-website.com/success');
$gateway->setFailedRedirectUrl('https://www.merchant-website.com/failed');
$gateway->setCancelRedirectUrl('https://www.merchant-website.com/cancel');

// optional: payment service provider(s) to use (see http://developers.payrexx.com/docs/miscellaneous)
// empty array = all available psps
$gateway->setPsp([]);
//$gateway->setPsp(array(4));
//$gateway->setPm(['mastercard']);

// optional: whether charge payment manually at a later date (type authorization)
$gateway->setPreAuthorization(false);
// optional: if you want to do a pre authorization which should be charged on first time
//$gateway->setChargeOnAuthorization(true);

// optional: whether charge payment manually at a later date (type reservation)
$gateway->setReservation(false);

// subscription information if you want the customer to authorize a recurring payment.
// this does not work in combination with pre-authorization payments.
//$gateway->setSubscriptionState(true);
//$gateway->setSubscriptionInterval('P1M');
//$gateway->setSubscriptionPeriod('P1Y');
//$gateway->setSubscriptionCancellationInterval('P3M');

// optional: reference id of merchant (e. g. order number)
$gateway->setReferenceId(975382);
//$gateway->setValidity(5);
//$gateway->setLookAndFeelProfile('144be481');

// optional: parse multiple products
//$gateway->setBasket([
//    [
//        'name' => [
//            1 => 'Dies ist der Produktbeispielname 1 (DE)',
//            2 => 'This is product sample name 1 (EN)',
//            3 => 'Ceci est le nom de l\'échantillon de produit 1 (FR)',
//            4 => 'Questo è il nome del campione del prodotto 1 (IT)'
//        ],
//        'description' => [
//            1 => 'Dies ist die Produktmusterbeschreibung 1 (DE)',
//            2 => 'This is product sample description 1 (EN)',
//            3 => 'Ceci est la description de l\'échantillon de produit 1 (FR)',
//            4 => 'Questa è la descrizione del campione del prodotto 1 (IT)'
//        ],
//        'quantity' => 1,
//        'amount' => 100
//    ],
//    [
//        'name' => [
//            1 => 'Dies ist der Produktbeispielname 2 (DE)',
//            2 => 'This is product sample name 2 (EN)',
//            3 => 'Ceci est le nom de l\'échantillon de produit 2 (FR)',
//            4 => 'Questo è il nome del campione del prodotto 2 (IT)'
//        ],
//        'description' => [
//            1 => 'Dies ist die Produktmusterbeschreibung 2 (DE)',
//            2 => 'This is product sample description 2 (EN)',
//            3 => 'Ceci est la description de l\'échantillon de produit 2 (FR)',
//            4 => 'Questa è la descrizione del campione del prodotto 2 (IT)'
//        ],
//        'quantity' => 2,
//        'amount' => 200
//    ]
//]);

// optional: add contact information which should be stored along with payment
$gateway->addField($type = 'title', $value = 'mister');
$gateway->addField($type = 'forename', $value = 'Max');
$gateway->addField($type = 'surname', $value = 'Mustermann');
$gateway->addField($type = 'company', $value = 'Max Musterfirma');
$gateway->addField($type = 'street', $value = 'Musterweg 1');
$gateway->addField($type = 'postcode', $value = '1234');
$gateway->addField($type = 'place', $value = 'Musterort');
$gateway->addField($type = 'country', $value = 'AT');
$gateway->addField($type = 'phone', $value = '+43123456789');
$gateway->addField($type = 'email', $value = 'max.muster@payrexx.com');
$gateway->addField($type = 'date_of_birth', $value = '03.06.1985');
$gateway->addField($type = 'terms', '');
$gateway->addField($type = 'privacy_policy', '');
$gateway->addField($type = 'custom_field_1', $value = '123456789', $name = array(
    1 => 'Benutzerdefiniertes Feld (DE)',
    2 => 'Benutzerdefiniertes Feld (EN)',
    3 => 'Benutzerdefiniertes Feld (FR)',
    4 => 'Benutzerdefiniertes Feld (IT)',
));
$gateway->addField($type = 'custom_field_2', $value = '123456789', $name = 'Custom Field');

try {
    $response = $payrexx->create($gateway);
    var_dump($response);
} catch (\Payrexx\PayrexxException $e) {
    print $e->getMessage();
}

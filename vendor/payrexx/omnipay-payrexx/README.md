# Omnipay: Payrexx

**Payrexx driver for the Omnipay PHP payment processing library**

Payrexx is a framework agnostic, multi-gateway payment processing library for PHP.
This package implements Payrexx support for Omnipay.

## Installation

Payrexx is installed via [Composer](http://getcomposer.org/). To install, simply require `league/omnipay` and `payrexx/omnipay` with Composer:

```
composer require league/omnipay payrexx/omnipay-payrexx
```


## Basic Usage

The following gateways are provided by this package:

* Payrexx

For general usage instructions, please see the main [Omnipay](https://github.com/thephpleague/omnipay)
repository.

### Basic purchase and refund example

```php
require __DIR__ . '/vendor/autoload.php';

use Omnipay\Omnipay;

$gateway = Omnipay::create('Payrexx');
$gateway->setApiKey('API_KEY'); // You find the API key in your Payrexx merchant backend
$gateway->setInstance('INSTANCE'); // That's your Payrexx instance name (INSTANCE.payrexx.com)

// Let's create a Payrexx gateway
$response = $gateway->purchase([
    'amount' => '100', // CHF 100.00
    'currency' => 'CHF',
    'psp' => 36, // Payrexx Direct
    'skipResultPage' => true,
    'successRedirectUrl' => 'https://www.merchant-website.com/success',
    'failedRedirectUrl' => 'https://www.merchant-website.com/failed',
])->send();

// A Payrexx gateway is always a redirect
if ($response->isRedirect()) {
    // Redirect URL to Payrexx gateway
    var_dump($response->getRedirectUrl());
    $response->redirect();
}

// That will be the Payrexx gateway ID
var_dump($response->getTransactionReference());

// Check if Payrexx gateway has been paid
$response = $gateway->completePurchase([
    'transactionReference' => $response->getTransactionReference(),
])->send();

// If Payrexx gateway has been paid, we will get a transaction reference (Payrexx transaction ID)
if ($response->getTransactionReference()) {
    // Optional: Fetch the corresponding transaction data => $response->getData()
    $response = $gateway->fetchTransaction([
        'transactionReference' => $response->getTransactionReference(),
    ])->send();

    // Let's refund CHF 50.00 (PSP has to support refunds => Payrexx Direct supports refunds)
    $response = $gateway->refund([
        'transactionReference' => $response->getTransactionReference(), // That's the Payrexx transaction ID as well
        'amount' => 50, // CHF 50.00
    ])->send();

    if ($response->isSuccessful()) {
        echo 'Refund was successful';
    }
}
```

## Support

If you are having general issues with Omnipay, we suggest posting on
[Stack Overflow](http://stackoverflow.com/). Be sure to add the
[omnipay tag](http://stackoverflow.com/questions/tagged/omnipay) so it can be easily found.

If you want to keep up to date with release anouncements, discuss ideas for the project,
or ask more detailed questions, there is also a [mailing list](https://groups.google.com/forum/#!forum/omnipay) which
you can subscribe to.

If you believe you have found a bug, please report it to integration@payrexx.com,
or better yet, fork the library and submit a pull request.

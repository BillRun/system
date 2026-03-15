payrexx-php
===========

VERSIONING
----------

This client API library uses the API version 1.0.0 of Payrexx. If you got troubles, make sure you are using the correct library version!

Requirements
------------
We recommend to use PHP version >= 7.4

The following php modules are required: cURL

Getting started with PAYREXX
----------------------------
If you don't already use Composer, then you probably should read the installation guide http://getcomposer.org/download/.

Please include this library via Composer in your composer.json and execute **composer update** to refresh the autoload.php.

For the latest library version you can use the following content of composer.json:

```json
{
    "require": {
        "payrexx/payrexx": "dev-master"
    }
}
```


For the Version 1.0.0 you can use the following content of composer.json:

```json
{
    "require": {
        "payrexx/payrexx": "1.0.0"
    }
}
```


1.  Instantiate the payrexx class with the following parameters:
    $instance: Your Payrexx instance name. (e.g. instance name 'demo' you request your Payrexx instance https://demo.payrexx.com
    $apiSecret: This is your API secret which you can find in your instance's administration.

    ```php
    $payrexx = new \Payrexx\Payrexx($instance, $apiSecret);
    ```
2.  Instantiate the model class with the parameters described in the API-reference:

    ```php
    $subscription = new \Payrexx\Models\Request\Subscription();
    $subscription->setId(1);
    ```
3.  Use your desired function:

    ```php
    $response  = $payrexx->cancel($subscription);
    $subscriptionId = $response->getId();
    ```

    It recommend to wrap it into a "try/catch" to handle exceptions like this:
    ```php
    try{
        $response  = $payrexx->cancel($subscription);
        $subscriptionId = $response->getId();
    }catch(\Payrexx\PayrexxException $e){
        //Do something with the error informations below
        $e->getCode();
        $e->getMessage();
    }
    ```

Platform API
--------------

When working with Platform accounts, you will need to specify your custom domain as the API Base URL when instantiating the client:

```php
$apiBaseDomain = 'your.domain.com';
$payrexx = new \Payrexx\Payrexx(
    $instance, 
    $apiSecret, 
    Communicator::DEFAULT_COMMUNICATION_HANDLER,
    $apiBaseDomain
);
```

The `$instance` is still expected to be the subdomain portion of their unique domain. For example, a Platform account that logs in on `client.platform.yourcompany.com` has `$instance` set to `client`, and `$apiBaseDomain` is set to `platform.yourcompany.com`. 

Documentation
--------------

For further information, please refer to the official REST API reference: https://developers.payrexx.com/v1.0/reference

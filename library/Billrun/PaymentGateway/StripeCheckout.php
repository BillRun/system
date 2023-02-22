<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2023 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

use Stripe\StripeClient;

/**
 * This class represents a payment gateway
 *
 * @since    5.2
 */
class Billrun_PaymentGateway_StripeCheckout extends Billrun_PaymentGateway
{

    const DEFAULT_CURRENCY = 'usd';
    const DEFAULT_AMOUNT = 0.5;
    
    protected $pendingCodes = "/^pending$/";
    protected $completionCodes = "/^succeeded$/";
    protected $rejectionCodes = "/^failed$/";
    
    protected $billrunName = "StripeCheckout";

    protected $billrunToken;

    public function updateSessionTransactionId($result)
    {
        $this->saveDetails['ref'] = $result->id;
    }

    public function pay($gatewayDetails, $addonData)
    {
        $stripeClient = $this->setupStipe();
        $gatewayDetails['amount'] = (int)$this->convertAmountToSend($gatewayDetails['amount']);

        $paymentIntent = $stripeClient->paymentIntents->create([
            'amount' => $gatewayDetails['amount'],
            'currency' => $gatewayDetails['currency'],
            'payment_method' => $gatewayDetails['payment_method_id'],
            'customer' => $gatewayDetails['customer_id'],
            'off_session' => true,
            'confirm' => true,
        ]);

        $this->transactionId = $paymentIntent->id;

        return [
            'status' => $paymentIntent->status,
            'additional_params' => [],
        ];
    }

    /**
     * Sets the API key to be used for requests.
     * @param  string|null  $secretKey
     * @return StripeClient
     */
    public function setupStipe(string $secretKey = null): StripeClient
    {
        if (!$secretKey) {
            $credentials = $this->getGatewayCredentials();
            $secretKey = $credentials['secret_key'];
        }

        return new StripeClient($secretKey);
    }

    protected function convertAmountToSend($amount)
    {
        $amount = round($amount, 2);
        return $amount * 100;
    }

    public function authenticateCredentials($params)
    {
        $this->validatingSecretKey($params['secret_key']);
        $this->validatingPublishableKey($params['publishable_key']);

        return true;
    }

    protected function validatingSecretKey($secretKey)
    {
        $client = $this->setupStipe($secretKey);
        $balance = $client->balance->retrieve();
    }

    protected function validatingPublishableKey($publishableKey)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/tokens");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "card[number]=''&card[exp_month]=''&card[exp_year]=''&card[cvc]=''");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_USERPWD, $publishableKey.":");
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        $errorMessage = isset($response["error"]["message"]) ? $response["error"]["message"] : '';
        if (!empty($errorMessage)) {
            if (substr($errorMessage, 0, 24) == 'Invalid API Key provided') {
                throw new Exception($errorMessage);
            }
        }
    }

    public function verifyPending($txId)
    {
        return '';
    }

    public function hasPendingStatus()
    {
        return false;
    }

    public function getDefaultParameters()
    {
        $params = ["secret_key", "publishable_key"];
        return $this->rearrangeParametres($params);
    }

    public function handleOkPageData($txId)
    {
        return true;
    }

    public function saveTransactionDetails($txId, $additionalParams)
    {
        $aid = $this->getAidFromProxy($txId);

        $paymentColl = Billrun_Factory::db()->creditproxyCollection();
        $query = [
            'name' => $this->billrunName,
            'instance_name' => $this->instanceName,
            'tx' => (string)$txId
        ];
        $paymentRow = $paymentColl->query($query)->cursor()->current();

        $stripeClient = $this->setupStipe();
        $checkoutSession = $stripeClient->checkout->sessions->retrieve($paymentRow['ref']);

        $setupIntent = $stripeClient->setupIntents->retrieve($checkoutSession->setup_intent);

        $paymentMethod = $stripeClient->paymentMethods->retrieve($setupIntent->payment_method);

        $this->saveDetails['customer_id'] = $checkoutSession->customer;
        $this->saveDetails['aid'] = $aid;
        $this->saveDetails['payment_method_id'] = $paymentMethod->id;
        $this->saveDetails['four_digits'] = $paymentMethod->card->last4;
        $expDate = $paymentMethod->card->exp_month.$paymentMethod->card->exp_year;
        $this->saveDetails['exp_date'] = $expDate;
        $this->savePaymentGateway();

        $tenantUrl = $this->getTenantReturnUrl($aid);
        $this->updateReturnUrlOnEror($tenantUrl);
        
        return [
            'tenantUrl' => $tenantUrl,
            'creditCard' => $paymentMethod->card->last4,
            'expirationDate' => $expDate,
        ];
    }

    public function createRecurringBillingProfile($aid, $gatewayDetails, $params = [])
    {
        return '';
    }

    public function getSecretFields()
    {
        return ['secret_key'];
    }

    protected function buildPostArray($aid, $returnUrl, $okPage, $failPage)
    {
        return false;
    }

    protected function updateRedirectUrl($result)
    {
        $this->redirectUrl = $result->url;
        $this->requestParams = [
            'url' => $result->url,
            'session_id' => $result->id,
            'session' => $result->toArray(),
            'txId' => $this->transactionId,
        ];
    }

    protected function buildTransactionPost($txId, $additionalParams)
    {
        return [];
    }

    protected function getResponseDetails($result)
    {
        return true;
    }

    protected function getToken($aid, $returnUrl, $okPage, $failPage, $singlePaymentParams, $options, $maxTries = 10)
    {
        // get account details
        $account = Billrun_Factory::account();
        $account->loadAccountForQuery(array('aid' => (int)$aid));

        $this->transactionId = Billrun_Util::generateRandomNum();

        // setup stripe api key
        $stripeClient = $this->setupStipe();

        // get stripe account or create one
        $stripeCustomer = $this->getStripeCustomer($account);
        $this->saveDetails['customer_id'] = $stripeCustomer->id;

        $failPage = $failPage ?? Billrun_Factory::config()->getConfigValue('payment.fail_page');
        $okPage = $this->adjustRedirectUrl($okPage, $this->transactionId);
        
        $params = [
            'mode' => 'setup',
            'success_url' => $okPage,
            'customer' => $stripeCustomer->id,
            'payment_method_types' => ['card'],
        ];
        
        if (!empty($failPage)) {
            $params['cancel_url'] = $this->adjustRedirectUrl($failPage, $this->transactionId);
        }
        
        $checkout_session = $stripeClient->checkout->sessions->create($params);

        return $checkout_session;
    }

    private function getStripeCustomer($account)
    {
        $email = $account->email;
        $firstName = $account->firstname;
        $lastName = $account->lastname;

        $client = $this->setupStipe();

        $customers = $client->customers->all(['email' => $email]);
        if ($customers->count() > 0) {
            return $customers->first();
        }

        return $client->customers->create([
            'email' => $email,
            'name' => $firstName.' '.$lastName,
        ]);
    }

    protected function adjustRedirectUrl($url, $txId): string
    {
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $origParams);
        $origParams[$this->getTransactionIdName()] = $txId;
        $params = http_build_query($origParams);
        $baseUrl = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);
        $port = parse_url($url, PHP_URL_PORT);
        
        return $baseUrl . ($port ? ':' . $port : '')  . $path. '?' . $params;
    }

    public function getTransactionIdName()
    {
        return "txId";
    }

    protected function signalStartingProcess($aid, $timestamp)
    {
        parent::signalStartingProcess($aid, $timestamp);

        $paymentColl = Billrun_Factory::db()->creditproxyCollection();
        $query = array(
            "name" => $this->billrunName,
            "instance_name" => $this->instanceName,
            "tx" => (string)$this->transactionId,
            "stamp" => md5($timestamp.$this->transactionId),
            "aid" => (int)$aid
        );

        $paymentRow = $paymentColl->query($query)->cursor()->sort(array('t' => -1))->limit(1)->current();
        if ($paymentRow->isEmpty()) {
            return;
        }


        $paymentRow->set('ref', $this->saveDetails['ref']);
        $paymentRow->set('customer_id', $this->saveDetails['customer_id']);

        $paymentColl->save($paymentRow);
    }

    protected function buildSetQuery()
    {
        return array(
            'active' => array(
                'name' => $this->billrunName,
                'instance_name' => $this->instanceName,
                'customer_id' => $this->saveDetails['customer_id'],
                'payment_method_id' => $this->saveDetails['payment_method_id'],
                'card_expiration' => (string)$this->saveDetails['card_expiration'],
                'four_digits' => (string)$this->saveDetails['four_digits'],
                'generate_token_time' => new MongoDate(time()),
            )
        );
    }

    protected function isNeedAdjustingRequest()
    {
        return false;
    }

    protected function isUrlRedirect()
    {
        return true;
    }

    protected function isHtmlRedirect()
    {
        return false;
    }

    protected function needRequestForToken()
    {
        return true;
    }

    protected function isTransactionDetailsNeeded()
    {
        return false;
    }

    protected function validateStructureForCharge($structure)
    {
        return !empty($structure['customer_id']) && !empty($structure['payment_method_id']);
    }

    protected function handleTokenRequestError($response, $params)
    {
        return false;
    }

    protected function buildSinglePaymentArray($params, $options)
    {
        throw new Exception("Single payment not supported in ".$this->billrunName);
    }

}

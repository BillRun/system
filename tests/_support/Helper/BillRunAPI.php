<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I
use Codeception\Module\REST;

class BillRunAPI extends \Codeception\Module{
  

    protected $accessToken = false;


    /**
     *get access token, it run once.
     * 
     * @return String  access_token.
     */
    public function getAccessToken()
    {
        if (!$this->accessToken) {
            $testUser = $_ENV['APP_TEST_USER'];
            $db = $this->getModule('MongoDb');
            // try {
                //$shani = new \Codeception\Step\ConditionalAssertion('seeNumElementsInCollection', func_get_args())
                // $db->tryToSeeInCollection('oauth_clients', ["client_id" => $testUser]);
            // } catch (Throwable $e) {
                $testSecret = $_ENV['APP_TEST_SECRET'];
                $db->haveInCollection('oauth_clients', 
                    [
                        "client_id" => $testUser,
                        "client_secret" => $testSecret,
                        "grant_types" => "client_credentials",
                        "scope" => "global",
                        "user_id" => null
                    ]
                );
            // }
            
            // Get the REST module to send requests
            /** @var REST $rest */
            $rest = $this->getModule('REST');

            // Leading slash is required: PhpBrowser resolves a relative URI
            // against the last visited page, so after e.g. /api/settings a
            // bare 'oauth2/token' becomes /api/oauth2/token (404).
            $rest->sendPOST('/oauth2/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $testUser,
                'client_secret' => $testSecret,
            ]);

            $rest->seeResponseCodeIs(200);
            $rest->seeResponseContainsJson(['token_type' => 'Bearer']);

            $this->accessToken = $rest->grabDataFromResponseByJsonPath('$.access_token')[0];
        }
        return $this->accessToken;
    }
      /**
     * Returns the last API response's 'entity' field, or null if not present.
     * This works only if the REST module is available and has grabResponse().
     */
    public function getLastEntity()
    {
        $rest = $this->getModule('REST');
        if (method_exists($rest, 'grabResponse')) {
            $resp = $rest->grabResponse();
            $data = json_decode($resp, true);
            return $data['entity'] ?? null;
        }
        return null;
    }
    /**
     * send post billapi requset to create entitys.
     * @param Array $data - entity fields 
     * @param String $entity - entity name
     * 
     */
    protected function sendBillapiCreate($data, $entity)
    {
        // Get the REST module to send requests
        /** @var REST $rest */
        $rest = $this->getModule('REST');
        $rest->amBearerAuthenticated($this->getAccessToken());
        $ret = $rest->sendPOST("/billapi/$entity/create", [
            'update' => json_encode($data)
        ]);
        return json_decode($ret, true);
    }

   
 /**
     * Sets plugin settings using the BillRun API.
     *
     * This function sends a POST request to the BillRun API endpoint "/api/settings"
     * with the provided data to set plugin settings. The request is authenticated
     * using the access token obtained from the getAccessToken method.
     *
     * @param array $data An associative array containing the plugin settings to be set.
     *                    The array keys represent the setting names, and the values represent the setting values.
     *                    Default value is an empty array.
     *
     * @return array|null The response from the BillRun API, decoded as an associative array.
     *                    If the response is not valid JSON, the function returns null.
     */
    public function setPluginSettings($data = [])
    {
        $rest = $this->getModule('REST');
        $rest->amBearerAuthenticated($this->getAccessToken());
        $ret = $rest->sendPOST("/api/settings", [
            'category'=> 'plugin',
            'action'=> 'set',
            'data' => json_encode($data)
        ]);
        return json_decode($ret, true);
    }

    /**
     * Sends a request to close a billapi entity.
     *
     * @param string $entity The entity to close.
     * @param array $query The query parameters.
     * @param array $update The update parameters.
     * @return array The response from the API as an associative array.
     */
    public function sendBillapiClose($entity, $query, $update)
    {
        // Get the REST module to send requests
        /** @var REST $rest */
        $rest = $this->getModule('REST');
        $rest->amBearerAuthenticated($this->getAccessToken());
        $params = [
            'query' => json_encode($query),
            'update' => json_encode($update)
        ];
        $ret =  $rest->sendPOST("/billapi/$entity/close", $params);
        return json_decode($ret, true);
    }

    public function sendBillapiCloseandnew($entity, $query, $update)
    {
        // Get the REST module to send requests
        /** @var REST $rest */
        $rest = $this->getModule('REST');
        $rest->amBearerAuthenticated($this->getAccessToken());
        $params = [
            'query' => json_encode($query),
            'update' => json_encode($update)
        ];
        $ret =  $rest->sendPOST("/billapi/$entity/closeandnew", $params);
        return json_decode($ret, true);
    }
    /**
     * Sends a request to reopen a billapi entity.
     *
     * @param string $entity The entity to reopen.
     * @param array $query The query parameters to identify the entity to reopen.
     * @param array $update The update parameters to apply when reopening the entity.
     * @return array The response from the API as an associative array.
     *
     * @throws Exception If the REST module is not available.
     */
    public function sendBillapiReopen($entity, $query, $update)
    {
        // Get the REST module to send requests
        /** @var REST $rest */
        $rest = $this->getModule('REST');
        $rest->amBearerAuthenticated($this->getAccessToken());
        // Prepare the request parameters
        $params = [
            'query' => json_encode($query),
            'update' => json_encode($update)
        ];
        // Send the POST request to reopen the entity
        $ret =  $rest->sendPOST("/billapi/$entity/reopen", $params);
        // Return the response as an associative array
        return json_decode($ret, true);
    }
   
   
    public function sendBillapiUniqueget($query, $entity)
    {
        // Get the REST module to send requests
        /** @var REST $rest */
        $rest = $this->getModule('REST');
        $rest->amBearerAuthenticated($this->getAccessToken());

        $ret = $rest->sendGet("/billapi/$entity/uniqueget", [
            'query' => json_encode($query)
        ]);
        return json_decode($ret, true);
    }
    
    public function sendApibill(array $params)
    {
        $aid = $params['aid'] ?? null;
        $query = $params['query'] ?? null;
        $action = $params['action'] ?? null;
        $aids = $params['aids'] ?? null;
      
        // Get the REST module to send requests
        /** @var REST $rest */
        $rest = $this->getModule('REST');
        $rest->amBearerAuthenticated($this->getAccessToken());
        
        $requestParams = [];
        
        if ($aid !== null) {
            $requestParams['aid'] = $aid;
        }     
        if ($aids !== null) {
            $requestParams['aids'] = $aids;
        } 
        if ($query !== null) {
            $requestParams['query'] = json_encode($query);
        }
            
        if ($action !== null) {
            $requestParams['action'] = $action;
        }
        
        
        // Only send request if we have parameters to send
        if (empty($requestParams)) {
            return null; // Or an appropriate error response
        }
        
        $ret = $rest->sendGet("/api/bill", $requestParams);
        return json_decode($ret, true);
    }
    
    public function sendBillapiGet($query, $entity)
    {
        // Get the REST module to send requests
        /** @var REST $rest */
        $rest = $this->getModule('REST');
        $rest->amBearerAuthenticated($this->getAccessToken());

        $ret = $rest->sendGet("/billapi/$entity/get", [
            'query' => json_encode($query)
        ]);
        return json_decode($ret, true);
    }

     /**
     * send post billapi requset to create entitys.
     * @param Array $data - entity fields 
     * @param String $entity - entity name
     * 
     */
    public function sendBillapiPermanentchange($entity,$query,$update,$options=null )
    {
        // Get the REST module to send requests
        /** @var REST $rest */
        $rest = $this->getModule('REST');
        $rest->amBearerAuthenticated($this->getAccessToken());
        $params = [
            'query' => json_encode($query),
            'update' => json_encode($update)
        ];
        if($options) {
            $params['options'] = json_encode($options);
        }
        $ret =  $rest->sendPOST("/billapi/$entity/permanentchange", $params);
        
        return json_decode($ret, true);
    }
/**
     * Sets settings for a specified category.
     *
     * This function sends a POST request to update settings for a given category.
     * It authenticates the request using a bearer token and sends the data as JSON.
     *
     * @param string $category The category of settings to be updated.
     * @param array $data An associative array containing the settings to be updated.
     * @return array The API response decoded as an associative array.
     */
    public function setSettings($category, $data)
    {
        // Get the REST module to send requests
        /** @var REST $rest */
        $rest = $this->getModule('REST');
        $rest->amBearerAuthenticated($this->getAccessToken());

        $params = [
            'category' => $category,
            'action' => 'set',
            'data' => json_encode($data)
        ];

        $ret = $rest->sendPOST("/api/settings", $params);

        return json_decode($ret, true);
    }

    /**
     * Retrieves settings for a specified category.
     *
     * This function sends a GET request to fetch settings for a given category.
     * It authenticates the request using a bearer token and sends any additional data as query parameters.
     *
     * @param string $category The category of settings to retrieve.
     * @param array $data Optional. Additional data to send with the request. Default is an empty array.
     * @return array The API response decoded as an associative array.
     */
    public function getSettings($category, $data = [])
    {
        // Get the REST module to send requests
        /** @var REST $rest */
        $rest = $this->getModule('REST');
        $rest->amBearerAuthenticated($this->getAccessToken());

        $params = [
            'category' => $category,
            'data' => json_encode($data)
        ];

        // For GET request, we need to add parameters to the URL
        $ret = $rest->sendGET("/api/settings", $params);

        return json_decode($ret, true);
    }

    /**
     * create an account.
     * @param Array $override - fields to override the default values / fields to add
     */
    public function createAccountWithAllMandatorySystemFields(array $override = [])
    {
        $account = array_merge([
            "invoice_shipping_method" => "email",
            "lastname" => "test",
            "invoice_detailed" => false,
            "invoice_language" => "en_GB",
            "from" => "2024-01-31",
            "zip_code" => "123ab",
            "payment_gateway" => "",
            "address" => "hshalom 7",
            "country" => "Israel",
            "salutation" => "",
            "firstname" => "yossi",
            "email" => "test@gmail.com"
        ], $override);

        return $this->generateAccount($account);
    }
    public function getModel($entity) {
        switch ($entity) {
            case 'account':
                $model = new \Models_Accounts(['collection' => 'accounts', 'no_init' => true]);
                break;
            case 'subscriber':
                $model = new \Models_Subscribers(['collection' => 'subscribers', 'no_init' => true]);
                break;
            case 'plan':
                $model = new \Models_Plans(['collection' => 'plans', 'no_init' => true]);
                break;
            case 'service':
                $model = new \Models_Services(['collection' => 'services', 'no_init' => true]);
                break;
            case 'rates':
                $model = new \Models_Rates(['collection' => 'rates', 'no_init' => true]);
                break;
            case 'discount':
                $model = new \Models_Discounts(['collection' => 'discounts', 'no_init' => true]);
                break;
        }
        return $model;
    }

    public function getCustomFields($entity) {
        $model = $this->getModel($entity);
        $mandatoryFields = $model->getMandatoryCustomFields();
        $populatedValues = [];
        foreach ($mandatoryFields as $field) {
            $field['type'] = $field['type'] ?? 'text';
            $value = $this->generateDemoValue($field['type']);
            if (!empty($field['select_options']) && is_string($field['select_options'])) {
                $options = array_filter(array_map('trim', explode(',', $field['select_options'])));
                if (!empty($options)) {
                    $value = reset($options); // prefer explicit option over generated value
                }
            }
            $populatedValues[$field['field_name']] = $value;
        }
        return $populatedValues;
    }


      /**
     * create an account.
     * @param Array $override - fields to override the default values 
     */
    public function generateAccount(array $account = [])
    {
        return $this->sendBillapiCreate($account, 'accounts');
    }

    /**
     * create an subscriber.
     * @param Array $override - fields to override the default values 
     */
    public function generateSubscriber(array $override = [])
    {
        $populatedValues = $this->getCustomFields('subscriber');
        $override = array_merge($populatedValues, $override);
        $subscriber = array_merge([

            "lastname" => "test",
            "plan" => "D",
            "from" => "2024-01-31",
            "play" => "Default",
            "address" => "hshalom 7",
            "country" => "Israel",
            "firstname" => "yossi",
            "aid" => 1,
            "services" => []

        ], $override);
        $this->sendBillapiCreate($subscriber, 'subscribers');
    }
    
    public function sendBillapiUpdate($entity,$query,$update )
    {
        // Get the REST module to send requests
        /** @var REST $rest */
        $rest = $this->getModule('REST');
        $rest->amBearerAuthenticated($this->getAccessToken());
        $params = [
            'query' => json_encode($query),
            'update' => json_encode($update)
        ];
        $ret =  $rest->sendPOST("/billapi/$entity/update", $params);
        
        return json_decode($ret, true);
    }
    /**
     * create an plan.
     * @param Array $override - fields to override the default values 
     */
    public function generatePlan(array $override = [])
    {
        $populatedValues = $this->getCustomFields('plan');
        $override = array_merge($populatedValues, $override);
        $plan = array_merge([

            "price" => [
                [
                    "price" => 100,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ],
            "from" => "2024-02-02",
            "name" => "PLAN".time().rand()*2000000,
            "tax" => [
                [
                    "type" => "vat",
                    "taxation" => "global"
                ]
            ],
            "upfront" => false,
            // "recurrence" => [
            //     "frequency" => 1,
            //     "start" => 1
            // ],
            "recurrence" => [
                "periodicity" => "month"
             ],
            "prorated_end" => true,
            "rates" => [],
            "prorated_start" => true,
            "connection_type" => "postpaid",
            "prorated_termination" => true,
            "description" => $override['name'] ?? "plan"

        ], $override);
        $this->sendBillapiCreate($plan, 'plans');
    }
    /**
     * create an service.
     * @param Array $override - fields to override the default values 
     */
    public function generateService(array $override = [], $byApi= false)
    {
        $customeFields = $this->getCustomFields('service');
        $override = array_merge($customeFields, $override);
        $service = array_merge([
            "description" => "service_",
            "name" => "SERVICE_",
            "price" => [
                [
                    "from" => 0,
                    "to" => "UNLIMITED",
                    "price" => 10
                ]
            ],
            "tax" => [
                [
                    "type" => "vat",
                    "taxation" => "global"
                ]
            ],
            "from" => "2024-02-02",
            "prorated" => true,
            "recurrence" => [
                "frequency" => 1,
                "start" => 1
            ],
        ], $override);
        if($byApi){
            $this->sendBillapiCreate($service, 'services');
            return $service;

        }else{
            if(isset($service["from"]) && !($service["from"] instanceof \Mongodloid_Date)){
                $service["from"] = new \Mongodloid_Date(strtotime($service['from']));
            }
            if(isset($service["to"]) && !($service["to"] instanceof \Mongodloid_Date)){
                $service["to"] = new \Mongodloid_Date(strtotime($service['to']));
            }
            $model = $this->getModel('service');
            $model->setUpdate($service);
            $model->create();
            return $model->getUpdate();
        }

    }

        /**
     * create an rate.
     * @param Array $override - fields to override the default values 
     */
    public function generateRate(array $override = [])
    {
        $populatedValues = $this->getCustomFields('rates');
        $override = array_merge($populatedValues, $override);
        $rate = array_merge([
                "key"=> "CALL",
                "description"=> "call",
                "pricing_method"=> "tiered",
                "add_to_retail"=> true,
                "tariff_category"=> "retail",
                "rates"=> [
                    "call"=> [
                        "BASE"=> [
                            "rate"=> [
                                [
                                    "from"=> 0,
                                    "to"=> "UNLIMITED",
                                    "interval"=> 11,
                                    "price"=> 11,
                                    "uom_display"=> [
                                        "range"=> "seconds",
                                        "interval"=> "seconds"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                "from"=> "2025-02-24",
                "tax"=> [
                    [
                        "type"=> "vat",
                        "taxation"=> "global"
                    ]
                ]
        ], $override);
        $this->sendBillapiCreate($rate, 'rates');
    }

    /**
    * @depends apiSanityCest:oauthLogin
    */
    public function sendAuthenticatedGET($url) {
        if (!$this->accessToken) {
            $this->accessToken = $this->getAccessToken();
        }
        $rest = $this->getModule('REST');
        $rest->amBearerAuthenticated($this->accessToken);
        $rest->sendGet($url);
    }

    /**
     * create an account.
     * @param Array $override - fields to override the default values / fields to add
     */
    public function createAccountWithAllMandatoryCustomFields(array $override = []) {
        $populatedValues = $this->getCustomFields('account');
        $populatedValues = array_merge($populatedValues, $override);
        return $this->createAccountWithAllMandatorySystemFields($populatedValues);
    }
    /**
     * Sends a payment request to the pay API.
     *
     * @param array $data The payment data.
     * @return array The response from the pay API.
     */
    protected function sendpayApi($data)
    {
        // Get the REST module to send requests
        /** @var REST $rest */
        $rest = $this->getModule('REST');
        $rest->amBearerAuthenticated($this->getAccessToken());
        $ret = $rest->sendPOST("/api/pay", [
             'method' => 'cash',
            'payments' => json_encode([$data])
        ]);
        return json_decode($ret, true);
    }
    /**
     * Rejects payments through the reject API.
     *
     * @param array $rejections List of rejections, each ['id' => <payment txid>, 'rejection' => ['code' => <code>]].
     * @return array The response from the reject API.
     */
    public function rejectPaymentApi($rejections)
    {
        // Get the REST module to send requests
        /** @var REST $rest */
        $rest = $this->getModule('REST');
        $rest->amBearerAuthenticated($this->getAccessToken());
        $ret = $rest->sendPOST("/api/reject", [
            'rejections' => json_encode($rejections)
        ]);
        return json_decode($ret, true);
    }

    /**
     * Sends a GET request to the specified PG endpoint with the provided data.
     *
     * @param array $data The data to be sent with the request.
     * @return array The response from the API as an associative array.
     */
    protected function sendGetRequset($data)
    {
        // Get the REST module to send requests
        /** @var REST $rest */
        $rest = $this->getModule('REST');
        $rest->amBearerAuthenticated($this->getAccessToken());
        $ret = $rest->sendPOST("/paymentgateways/getRequest", [
            'data' => json_encode($data)
        ]);
        return json_decode($ret, true);
    }

   
    public function payApi($params = []){

        $payment = array_merge([
        "amount"=>10,
        "aid"=>1,
        "payer_name"=>"yossi test",
        "dir"=>"fc",
        "deposit_slip"=>"",
        "deposit_slip_bank"=>"",
        "source"=>"web"
        ], $params);
        return  $this->sendpayApi($payment);
    }

    /**
     * Sends getRequest API request with the specified parameters.
     *
     * @param array $params Optional. An associative array of query parameters to include in the request.
     * @return mixed The response from the GET request.
     */
    public function getRequest($params = []){
        $iframe=true;
        $aid=1;
        $name ="CreditGuard";
        $amount=5;
        $action="single_payment";
        $return_url="http://web/paymentgateways/success";
        $ok_page ="http://web/paymentgateways/okpage?name=CreditGuard";
        $fail_page="http://web/paymentgateways/okpage";
        if($params['type']=='subscriber'){
            //J5 only
            $body = array_merge([
                "aid"=>$aid,
                "type"=>"subscriber",
                "name"=>$name,
                "return_url"=>$return_url,
                "_t_"=>time()
            ], $params);
        }else{
            $body = array_merge([
                "iframe"=>$iframe,
                "aid"=>$aid,
                "name"=>$name,
                "type"=>"account",
                "amount"=>$amount,
                "action"=>$action,
                "return_url"=>$return_url,
                "ok_page"=> $ok_page,
                "fail_page"=>$fail_page,
                "_t_"=>time()
            ], $params);
        }
        
        return  $this->sendGetRequset($body);
    }



    public function chargeAccountApi($params = []){

        // Get the REST module to send requests
        /** @var REST $rest */
        $rest = $this->getModule('REST');
        $rest->amBearerAuthenticated($this->getAccessToken());
        $ret = $rest->sendPOST("/billrun/chargeAccount",   $params);
        return json_decode($ret, true);
    }

    // ----------------------------------------------------------------------
    //  Thin, single-purpose payment helpers (used by tests/all/payment/*Test)
    //  Each is ONE authenticated request; array params are JSON-encoded as the
    //  API endpoints expect. No business logic lives here.
    // ----------------------------------------------------------------------

    /**
     * Authenticated request to an API path. Array values are JSON-encoded.
     * @return array decoded JSON response
     */
    protected function sendApiRequest($path, array $request, $method = 'POST')
    {
        $encoded = [];
        foreach ($request as $key => $value) {
            $encoded[$key] = is_array($value) ? json_encode($value) : $value;
        }
        /** @var REST $rest */
        $rest = $this->getModule('REST');
        $rest->amBearerAuthenticated($this->getAccessToken());
        if (strtoupper($method) === 'GET') {
            $rest->sendGET($path, $encoded);
        } else {
            $rest->sendPOST($path, $encoded);
        }
        return json_decode($rest->grabResponse(), true);
    }

    /** /api/pay — one offline payment (cash/cheque/credit/write_off). */
    public function payOffline($aid, $amount, $dir = 'fc', $method = 'cash', array $paymentFields = [])
    {
        $payment = array_merge(['aid' => (int) $aid, 'amount' => $amount, 'dir' => $dir], $paymentFields);
        return $this->sendApiRequest('/api/pay', ['method' => $method, 'payments' => [$payment]]);
    }

    /** /api/pay — a custom payments array (e.g. with a `pays` link to a bill). */
    public function payPayments(array $payments, $method = 'cash', array $topExtra = [])
    {
        return $this->sendApiRequest('/api/pay', array_merge(['method' => $method, 'payments' => $payments], $topExtra));
    }

    /** /api/pay — split a debt into installments (action=split_bill). $amount=null omits it. */
    public function splitBill($aid, $amount, array $opts = [])
    {
        $base = ['method' => 'installment_agreement', 'action' => 'split_bill', 'aid' => (int) $aid];
        if (!is_null($amount)) {
            $base['amount'] = $amount;
        }
        return $this->sendApiRequest('/api/pay', array_merge($base, $opts));
    }

    /** /api/pay — merge installments of an agreement (action=merge_installments). */
    public function mergeInstallments($aid, $splitBillId, array $opts = [])
    {
        return $this->sendApiRequest('/api/pay', array_merge([
            'method'        => 'merge_installments',
            'action'        => 'merge_installments',
            'aid'           => (int) $aid,
            'split_bill_id' => (string) $splitBillId,
        ], $opts));
    }

    /** /api/pay — unfreeze/use deposit bills (action=use_deposit). */
    public function useDeposit($aid, array $txids, array $extra = [])
    {
        return $this->sendApiRequest('/api/pay', array_merge([
            'action' => 'use_deposit',
            'aid'    => (int) $aid,
            'txid'   => $txids,
        ], $extra));
    }

    /** /api/pay — cancel payments (action=cancel_payments). */
    public function cancelPayments($aid, array $cancellations, array $extra = [])
    {
        return $this->sendApiRequest('/api/pay', array_merge([
            'action'        => 'cancel_payments',
            'aid'           => (int) $aid,
            'cancellations' => $cancellations,
        ], $extra));
    }

    /** /api/adjustpayments — adjust existing payments. */
    public function adjustPayments(array $adjustments, array $extra = [])
    {
        return $this->sendApiRequest('/api/adjustpayments', array_merge(['adjustments' => $adjustments], $extra));
    }

    /** /api/bill — query bills/invoices/balances/collection-debt by action. */
    public function billQuery($action, array $params = [])
    {
        return $this->sendApiRequest('/api/bill', array_merge(['action' => $action], $params), 'GET');
    }

    /** /api/onetimeinvoice — create an immediate invoice from CDRs. */
    public function onetimeInvoice($aid, array $cdrs, array $extra = [])
    {
        if (isset($extra['invoice_unixtime'])) {
            if ($extra['invoice_unixtime'] === 'past') {
                $extra['invoice_unixtime'] = time() - 2592000;
            } elseif ($extra['invoice_unixtime'] === 'future') {
                $extra['invoice_unixtime'] = time() + 2592000;
            }
        }
        return $this->sendApiRequest('/api/onetimeinvoice', array_merge(['aid' => (int) $aid, 'cdrs' => $cdrs], $extra), 'GET');
    }

    /** Create an account whose active payment gateway is the mockup CreditGuard with $cardToken. */
    public function createAccountWithCard($cardToken, array $override = [])
    {
        $active = array_merge([
            'name'                  => 'CreditGuard',
            'instance_name'         => 'CreditGuard',
            'card_token'            => $cardToken,
            'card_expiration'       => '1225',
            'four_digits'           => '5606',
            'personal_id'           => '890108566',
            'card_acquirer'         => '1',
            'card_brand'            => '1',
            'credit_company'        => '1',
            'card_type'             => '00',
            'transaction_exhausted' => true,
            'auth_number'           => '9433084',
            'generate_token_time'   => '2023-11-29T08:55:32Z',
            'keepCCDetails'         => null,
        ], $override['payment_gateway']['active'] ?? []);
        unset($override['payment_gateway']);
        $resp = $this->createAccountWithAllMandatoryCustomFields(
            array_merge(['payment_gateway' => ['active' => $active]], $override)
        );
        return (is_array($resp) && isset($resp['entity'])) ? $resp['entity'] : $this->getLastEntity();
    }

    /**
     * Switch an account's active payment-gateway card token directly in the
     * subscribers collection (accounts live there with type='account'). Used to
     * flip a rejecting card to a good one between charges (e.g. retry-after-rejection).
     */
    public function setAccountCardToken($aid, $cardToken)
    {
        \Billrun_Factory::db()->subscribersCollection()->update(
            ['aid' => (int) $aid, 'type' => 'account'],
            ['$set' => ['payment_gateway.active.card_token' => $cardToken]],
            ['multiple' => true]
        );
    }

    /**
     * Seed the 3-bill "pending gateway coverage" fixture used by the switch_links
     * tests, directly into the bills collection (the legacy SimpleTest did the same
     * from bills.json). It represents a tc debt of 10 that is covered 5 by an
     * APPROVED cash payment and 5 by a PENDING automatic gateway bill that is still
     * awaiting confirmation (cpg_* file-charge artifacts). The online CreditGuard
     * gateway is designed never to emit this awaiting-confirmation state
     * (hasPendingStatus()=false), so it cannot be produced through the payment API
     * and must be seeded. txids are derived from the (dynamic) aid so they are unique
     * and never collide with auto-generated payment txids. Returns the txid base; the
     * three bills are base.'005' (debt invoice), base.'006' (approved), base.'007' (pending).
     */
    public function seedSwitchLinksFixture($aid)
    {
        // 13-char txids (matches Billrun_Bill_Payment::txIdLength) so padTxId is a no-op
        // and the bill lookups by id (getInstanceByid) resolve the seeded links.
        $base = '9' . str_pad((string) (int) $aid, 9, '0', STR_PAD_LEFT);
        $past = new \Mongodloid_Date(strtotime('-30 days'));
        $bills = \Billrun_Factory::db()->billsCollection();

        $invoice = [
            'dir' => 'tc', 'method' => 'cash', 'aid' => (int) $aid, 'type' => 'rec',
            'amount' => 10, 'due' => 10, 'due_date' => $past, 'urt' => $past,
            'payer_name' => 'yossi test', 'source' => 'web', 'left_to_pay' => 0, 'total_paid' => 10,
            'vatable_left_to_pay' => 0, 'paid' => '2', 'pending_covering_amount' => 5,
            'txid' => $base . '005', 'process_time' => $past,
            'paid_by' => [
                ['type' => 'rec', 'id' => $base . '006', 'amount' => 5, 'total_amount' => 5, 'date' => $past],
                ['type' => 'rec', 'id' => $base . '007', 'amount' => 5, 'total_amount' => 5, 'date' => $past, 'pending' => true],
            ],
            'waiting_payments' => [$base . '007'],
        ];
        $approved = [
            'dir' => 'fc', 'method' => 'cash', 'aid' => (int) $aid, 'type' => 'rec',
            'amount' => 5, 'due' => -5, 'due_date' => $past, 'urt' => $past,
            'payer_name' => 'yossi test', 'source' => 'web',
            'pays' => [['type' => 'rec', 'id' => $base . '005', 'amount' => 5, 'total_amount' => 10, 'date' => $past]],
            'left' => 0, 'pending_covering_amount' => 0, 'txid' => $base . '006', 'process_time' => $past,
        ];
        $pending = [
            'dir' => 'fc', 'method' => 'automatic', 'aid' => (int) $aid, 'type' => 'rec',
            'amount' => 5, 'due' => -5, 'due_date' => $past, 'urt' => $past, 'source' => 'web',
            'generated_pg_file_log' => 'seedswitchlinks',
            'pays' => [['type' => 'rec', 'id' => $base . '005', 'amount' => 5, 'pending' => true]],
            'left' => 0, 'pending_covering_amount' => 0, 'waiting_for_confirmation' => true,
            'txid' => $base . '007', 'pending' => true,
            'cpg_name' => ['Credit_Card'], 'cpg_type' => ['transactions_request'],
            'cpg_file_type' => ['transactions1'], 'pg_request' => [],
        ];
        $bills->insert($invoice);  // insert() takes its argument by reference
        $bills->insert($approved);
        $bills->insert($pending);
        return $base;
    }

    /**
     * Wipe the bills collection (cleanDB intentionally leaves it intact). Needed by
     * tests that issue a GLOBAL charge sweep (chargeAccount without an aids filter,
     * e.g. exclude_accounts) so the forked worker only processes this test's debt
     * and is not slowed/contended by every prior test's leftover unpaid invoices.
     */
    public function clearBills()
    {
        \Billrun_Factory::db()->billsCollection()->remove(['_id' => ['$exists' => true]]);
    }

    /** Read one bill (raw array) matching $criteria, or null — used to chain txid/invoice_id. */
    public function grabBill(array $criteria)
    {
        $cursor = \Billrun_Factory::db()->billsCollection()->query($criteria)->cursor()
            ->setReadPreference('RP_PRIMARY');
        foreach ($cursor as $bill) {
            return $bill->getRawData();
        }
        return null;
    }

    /**
     * Seed the CALL rate that onetimeinvoice CDRs reference, directly into Mongo
     * (cleanDB wipes rates; the billapi rate-create path is config-state fragile).
     * Pre-priced credit CDRs don't use the tariff, so a minimal rate suffices.
     */
    public function seedCallRate()
    {
        $rates = \Billrun_Factory::db()->ratesCollection();
        $rates->remove(['key' => 'CALL']);
        $rate = [
            'key' => 'CALL',
            'description' => 'call',
            'from' => new \Mongodloid_Date(strtotime('2018-01-01')),
            'to' => new \Mongodloid_Date(strtotime('2099-01-01')),
            'tariff_category' => 'retail',
            'add_to_retail' => true,
            'pricing_method' => 'tiered',
            'rates' => ['call' => ['BASE' => ['rate' => [[
                'from' => 0, 'to' => 'UNLIMITED', 'interval' => 1, 'price' => 0,
                'uom_display' => ['range' => 'seconds', 'interval' => 'seconds'],
            ]]]]],
            'tax' => [['type' => 'vat', 'taxation' => 'global']],
        ];
        $rates->insert($rate); // insert() takes its argument by reference
    }

    /**
     * Enable/disable named plugins in a fresh config revision (test-scoped).
     * Some customer plugins (e.g. epicPlugin) hook generic payment events
     * (beforeSplitDebt) and must be turned off to test core billing behavior.
     */
    public function setPluginsEnabled(array $names, $enabled)
    {
        $config = \Billrun_Factory::db()->configCollection();
        $data = $config->query()->cursor()->sort(['urt' => -1, '_id' => -1])->limit(1)->current()->getRawData();
        unset($data['_id']);
        foreach ($data['plugins'] as &$plugin) {
            if (in_array($plugin['name'] ?? '', $names, true)) {
                $plugin['enabled'] = (bool) $enabled;
            }
        }
        unset($plugin);
        $data['urt'] = new \MongoDB\BSON\UTCDateTime();
        $config->insert($data);
        \Billrun_Config::getInstance()->loadDbConfig();
    }
  
    public function sendRealTimeRequest($fileType, $request)
    {
        // Get the REST module to send requests
        /** @var REST $rest */
        $rest = $this->getModule('REST');
        $rest->amBearerAuthenticated($this->getAccessToken());
        $params = [
            'request' => json_encode($request),
            'file_type' => $fileType
        ];
        $ret =  $rest->sendPOST("/realtime", $params);
        return json_decode($ret, true);
    }

    function generateDemoValue($type = 'text') {
        switch ($type) {
            case 'boolean':
                return (bool) rand(0, 1);
                
            case 'date':
                // Generate a random date within the next year
                $days = rand(-365, 365);
                $date = new \DateTime();
                $date->modify("$days days");
                return $date->format('Y-m-d\TH:i:s+0000');
                
            case 'text':
                $length =  rand(5, 10);
                $characters = 'abcdefghijklmnopqrstuvwxyz';
                $result = '';
                for ($i = 0; $i < $length; $i++) {
                    $result .= $characters[rand(0, strlen($characters) - 1)];
                }
                return $result;
                
            case 'daterange':
                $start = new \DateTime();
                $start->modify(rand(-30, 0) . ' days'); // Start within last 30 days
                
                $end = clone $start;
                $end->modify('+' . rand(1, 30) . ' days'); // End within 1-30 days after start
                
                return [[
                    'from' => $start->format('Y-m-d\TH:i:s+0000'),
                    'to' => $end->format('Y-m-d\TH:i:s+0000')
                ]];
                
            case 'percentage':
                return round(rand(0, 100) / 100, 2);
                
            default:
                return $this->generateDemoValue('text');
        }
    }


     /**
     * create an ConditaionlCharge.
     * @param Array $override - fields to override the default values 
     */
    public function generateConditaionlCharge(array $override = [])
    {
        $charge = array_merge([
            "description"=> "a",
            "key"=> microtime(true)*10000,
            "proration"=> "inherited",
            "priority"=> "",
            "params"=> [
                "min_subscribers"=> "",
                "max_subscribers"=> "",
                "conditions"=> [
                    [
                        "subscriber"=> [
                            [
                                "fields"=> [
                                    [
                                        "field"=> "sid",
                                        "op"=> "nin",
                                        "value"=> [
                                            25555525515811
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "from"=> "2020-02-26",
            "type"=> "monetary",
            "subject"=> [
                "general"=> [
                    "value"=> 30
                ]
            ]
        ], $override);
        $this->sendBillapiCreate($charge, 'charges');
    }
    

    public function generateDiscount($override = [], $byApi= false)
  {
    $customeFields = $this->getCustomFields('discount');
    $override = array_merge($customeFields, $override);
    //http://billrun/billapi/discounts/create
    $discount = array_merge([
      
        "description" => "nn",
        "key" => '20240111134913715',
        "proration" => "inherited",
        "priority" => "",
        "params" => [
          "min_subscribers" => "",
          "max_subscribers" => "",
          "conditions" => [[]]
        ],
        "from" => "2023-05-12",
        "type" => "monetary"
      
    ], $override);
    if($byApi){
        $this->sendBillapiCreate($discount, 'discounts');

    }else{
        if(isset($discount["from"]) && !($discount["from"] instanceof \Mongodloid_Date)){
            $discount["from"] = new \Mongodloid_Date(strtotime($discount['from']));
        }
        if(isset($discount["to"]) && !($discount["to"] instanceof \Mongodloid_Date)){
            $discount["to"] = new \Mongodloid_Date(strtotime($discount['to']));
        }
        $model = $this->getModel('discount');
	    $model->setUpdate($discount);
        $model->create();
    }
  }

    public function sendOnetimeInvoiceApi(array $cdrs, $aid, $extra_params = []) {
        // Get the REST module to send requests
        /** @var REST $rest */
        $rest = $this->getModule('REST');
        $rest->amBearerAuthenticated($this->getAccessToken());

        $params = [
            'cdrs' => json_encode($cdrs),
            'aid' => $aid
        ];
        $params = array_merge($params, $extra_params);

        // For GET request, we need to add parameters to the URL
        $ret = $rest->sendGET("/api/onetimeinvoice", $params);

        return json_decode($ret, true);
    }

    /**
     * Clear Billrun_Base's process-wide singleton cache. Callers that change
     * file_types / queue.calculators / similar config mid-test should call
     * this before getInstance() so the new config is picked up rather than a
     * cached calc/processor built against the previous config.
     */
    public function resetBillrunSingletons()
    {
        $instances = new \ReflectionProperty('Billrun_Base', 'instance');
        $instances->setAccessible(true);
        $instances->setValue(null, []);
    }

    public static function cleanDB(){


        $collections = [
            'subscribersCollection',
            'linesCollection',
            'queueCollection',
            'servicesCollection',
            'plansCollection',
            'discountsCollection',
            'billrunCollection',
            'billing_cycleCollection',
            'ratesCollection',
            'billsCollection',
            'operationsCollection',
            'balancesCollection',
	        'logCollection',
	        'archiveCollection',
            'chargesCollection',
            'collection_stepsCollection',
        ];

        foreach ($collections as $collectionMethod) {
            $collection = \Billrun_Factory::db()->$collectionMethod();
            if ($collection) {
                $collection->remove(['_id' => ['$exists' => true]]);
            }
        }
        $events = \Billrun_Factory::db()->eventsCollection();
        $events->remove(['_id'=>['$exists' => true]]);
    }
    
}
//billapi/accounts/permanentchange

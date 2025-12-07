<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I
use Codeception\Module\REST;

class BillRunAPI extends \Codeception\Module
{
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

            $rest->sendPOST('oauth2/token', [
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
    public function getCustomFields($entity) {
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
            case 'rates';
                $model = new \Models_Rates(['collection' => 'rates', 'no_init' => true]);
                break;
        }

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
            "recurrence" => [
                "frequency" => 1,
                "start" => 1
            ],
        
            "prorated_end" => true,
            "rates" => [],
            "prorated_start" => true,
            "connection_type" => "postpaid",
            "prorated_termination" => true,
            "description" => "plan"

        ], $override);
        $this->sendBillapiCreate($plan, 'plans');
    }
    /**
     * create an service.
     * @param Array $override - fields to override the default values 
     */
    public function generateService(array $override = [])
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

        $this->sendBillapiCreate($service, 'services');
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
    

    public function generateDiscount($override = [])
  {
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

    $this->sendBillapiCreate($discount, 'discounts');
  }
    
}
//billapi/accounts/permanentchange

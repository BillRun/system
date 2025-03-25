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
            "name" => "PLAN",
            "tax" => [
                [
                    "type" => "vat",
                    "taxation" => "global"
                ]
            ],
            "upfront" => false,
            "recurrence" => ["periodicity" => "month"],
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
            "recurrence" =>[
                "periodicity" => "month"
            ]
        ], $override);

        $this->sendBillapiCreate($service, 'services');
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
            $populatedValues[$field['field_name']] = $value;
        }
        return $populatedValues;
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
    
    
}
//billapi/accounts/permanentchange
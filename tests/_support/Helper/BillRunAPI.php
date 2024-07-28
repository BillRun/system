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
            "prorated" => true
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
        $model = new \Models_Accounts(['collection' => 'accounts', 'no_init' => true]);
        $mandatoryFields = $model->getMandatoryCustomFields();
        $populatedValues = [];
        foreach ($mandatoryFields as $field) {
            $populatedValues[$field['field_name']] = '1';
        }
        $populatedValues = array_merge($populatedValues, $override);
        return $this->createAccountWithAllMandatorySystemFields($populatedValues);
    }

}

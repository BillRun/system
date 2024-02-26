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
        $testUser = $_ENV['APP_TEST_USER'];
        $testSecret = $_ENV['APP_TEST_SECRET'];

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

        return $rest->grabDataFromResponseByJsonPath('$.access_token')[0];
    }
    /**
     * send post billapi requset to create entitys.
     * @param Array $data - entity fields 
     * @param String $entity - entity name
     * 
     */
    protected function sendBillapiCreate($data, $entity)
    {
        if (!$this->accessToken) {
            $this->accessToken = $this->getAccessToken();
        }
        // Get the REST module to send requests
        /** @var REST $rest */
        $rest = $this->getModule('REST');
        $rest->amBearerAuthenticated($this->accessToken);
        $rest->sendPOST("/billapi/$entity/create", [
            'update' => json_encode($data)
        ]);
    }
   

    /**
     * create an account.
     * @param Array $override - fields to override the default values 
     */
    public function generateAccount(array $override = [])
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

        $this->sendBillapiCreate($account, 'accounts');
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


}

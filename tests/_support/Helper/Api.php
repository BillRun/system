<?php
namespace Helper;
// here you can define custom actions
// all public methods declared in helper class will be available in $I

use Codeception\Module;
use Codeception\Module\REST;

class Api extends Module
{
    public function getO2Token() {
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

    public function generateAccount(array $override=[]) {
        $account = array_merge([
            "invoice_shipping_method"=>"email",
            "lastname"=>"test",
            "invoice_detailed"=>false,
            "invoice_language"=>"en_GB",
            "from"=>"2024-01-31",
            "zip_code"=>"123ab",
            "payment_gateway"=>"",
            "address"=>"hshalom 7",
            "country"=>"Israel",
            "salutation"=>"",
            "firstname"=>"yossi",
            "email"=>"test@gmail.com"
          ], $override);
        
        // Get the REST module to send requests
        /** @var REST $rest */
        $rest = $this->getModule('REST');
        $rest->amBearerAuthenticated($this->getO2Token());
        $rest->sendPOST('/billapi/accounts/create', [
           'update' => json_encode($account)

        ]);
    }

}

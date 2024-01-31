<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

class Api extends \Codeception\Module
{
    
        public function getToken(ApiTester $I) {
            $testUser = $_ENV['APP_TEST_USER'];
            $testSecret = $_ENV['APP_TEST_SECRET'];
            $I->haveInCollection('oauth_clients', 
                [
                    "client_id" => $testUser,
                    "client_secret" => $testSecret,
                    "grant_types" => "client_credentials",
                    "scope" => "global",
                    "user_id" => null
                ]
            );

            $I->sendPOST('oauth2/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $testUser,
                'client_secret' => $testSecret,
            ]);
            
            $I->seeResponseCodeIs(200);
            $I->seeResponseContainsJson(['token_type' => 'Bearer']);
            
            return $I->grabDataFromResponseByJsonPath('$.access_token')[0];
            
        }
    
}

<?php

class apiSanityCest
{

    protected $accessToken;

    public function _before(ApiTester $I)
    {
        // echo 'test ';
    }   

  

    public function nonAuthAPISanity(ApiTester $I)
    {
        $I->sendGet('/api');
        $I->seeResponseIsJson();

        $I->seeResponseContains('{"status":0');
        
        $I->seeResponseContainsJson([
            'status' => 0
          ]);
    }

    public function badAuthSanity(ApiTester $I)
    {
        $I->amBearerAuthenticated('0fc3a40757836b20baed344cdd1d63ca3741db77a');
        $I->sendGet('/api');
        $I->seeResponseCodeIs(401);
        $I->seeResponseIsJson();

        $I->seeResponseContains('{"status":0');
        
        $I->seeResponseContainsJson([
            'status' => 0
          ]);
    }

    /**
    * @depends oauthLogin
    */
    public function goodOauthSanity(ApiTester $I)
    {
        
        $I->amBearerAuthenticated($this->accessToken);
        $I->sendGet('/api');
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $I->seeResponseContains('{"status":1');
        
        $I->seeResponseContainsJson([
            'status' => 1
          ]);
    }

    public function oauthLogin(ApiTester $I) {
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
            
            $this->accessToken = $I->grabDataFromResponseByJsonPath('$.access_token')[0];
            $I->assertGreaterThan(0,strlen($this->accessToken));
            $I->assertTrue(ctype_xdigit($this->accessToken));
        }

}

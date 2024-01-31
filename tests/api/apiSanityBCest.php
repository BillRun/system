<?php

class apiSanityBCest
{

    protected $accessToken;

    public function _before(ApiTester $I)
    {
        $I->amBearerAuthenticated($I->getToken());
    }   

  

    /**
    * @depends oauthLogin
    */
    public function goodOauthSanity(ApiTester $I)
    {
        
        $I->sendGet('/api');
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $I->seeResponseContains('{"status":1');
        
        $I->seeResponseContainsJson([
            'status' => 1
          ]);
    }

    
}

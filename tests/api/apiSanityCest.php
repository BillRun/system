<?php

class apiSanityCest
{
    public function _before(ApiTester $I)
    {
    }   

    // tests
    public function tryToTest(ApiTester $I)
    {
    }

    public function apiSanity(ApiTester $I)
    {
        // $I->amHttpAuthenticated('service_user', '123456');
        // $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendGet('/api');
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $I->seeResponseContains('{"status":0');
        
        $I->seeResponseContainsJson([
            'status' => 0
          ]);
    }
}

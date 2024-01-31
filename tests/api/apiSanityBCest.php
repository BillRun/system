<?php

class apiSanityBCest
{

    public function _before(ApiTester $I)
    {
        $I->amBearerAuthenticated($I->getO2Token());
    }   

  
    
    public function testCreateAccount(ApiTester $I)
    {
        
        $I->generateAccount(['firstname'=>'yossi_test']);
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();

        $I->seeResponseContains('{"status":1');
        
        $I->seeResponseContainsJson([
            'firstname' => 'yossi_test'
          ]);
    }

}

<?php

class apiSanityBCest
{

    public $accountDetails;
    public $planDetails;
    public $serviceDetails;
    public function _before(ApiTester $I)
    {
        $I->amBearerAuthenticated($I->getO2Token());
    }



    public function testCreateAccount(ApiTester $I)
    {

        $I->generateAccount(['firstname' => 'yossi_test']);
        $I->seeResponseIsJson();
        $I->seeResponseContains('{"status":1');
        $I->seeResponseContainsJson([
            'firstname' => 'yossi_test'
        ]);
        $this->accountDetails = json_decode($I->grabResponse(), true)['entity'];
    }

    public function testCreatePlan(ApiTester $I)
    {

        $I->generatePlan(['name' => 'TEST_PLAN_2']);
        $I->seeResponseIsJson();
        $I->seeResponseContains('{"status":1');
        $I->seeResponseContainsJson([
            'name' => 'TEST_PLAN_2'
        ]);
        $this->planDetails = json_decode($I->grabResponse(), true)['entity'];
    }

    public function testCreateService(ApiTester $I)
    {

        $I->generateService(['name' => 'TEST_SERVICE']);
        $I->seeResponseIsJson();
        $I->seeResponseContains('{"status":1');
        $I->seeResponseContainsJson([
            'name' => 'TEST_SERVICE'
        ]);
        $this->serviceDetails = json_decode($I->grabResponse(), true)['entity'];
    }

    /**
     * @depends testCreateAccount
     * @depends testCreatePlan
     * @depends testCreateService
     */
    public function testCreateSubscriber(ApiTester $I)
    {

        $I->generateSubscriber(
            [
                'firstname' => 'yossi_test',
                'aid' => $this->accountDetails['aid'],
                'plan' => $this->planDetails['name'],
                'services'=>[
                       [
                        'name'=>$this->serviceDetails['name'],
                        'from'=>'2024-02-05',
                        'to'=>'2124-02-05',
                       ]
                ]
            ]
        );
        $I->seeResponseCodeIsSuccessful();
        $I->seeResponseIsJson();
        $I->seeResponseContains('{"status":1');
        $I->seeResponseContainsJson([
            'firstname' => 'yossi_test',
            'aid' => $this->accountDetails['aid'],
            'plan' => $this->planDetails['name']
        ]);
    }

}

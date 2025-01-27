<?php

class apiSanityBCest
{

    public $accountDetails;
    public $planDetails;
    public $serviceDetails;
    public function _before(ApiTester $I)
    {
    }



    public function testCreateAccount(ApiTester $I)
    {
        $I->createAccountWithAllMandatoryCustomFields(['firstname' => 'yossi_test']);
        $I->seeResponseIsJson();
        $I->seeResponseContains('{"status":1');
        $I->seeResponseContainsJson([
            'firstname' => 'yossi_test'
        ]);
        $this->accountDetails = json_decode($I->grabResponse(), true)['entity'];
    }

    public function testCreatePlan(ApiTester $I)
    {

        $I->generatePlan(['name' => 'TEST_PLAN_2'.time()]);
        $this->planDetails = json_decode($I->grabResponse(), true)['entity'];
        $I->seeResponseIsJson();
        $I->seeResponseContains('{"status":1');
        $I->seeResponseContainsJson(['name' => $this->planDetails['name']]);
    }

    public function testCreateService(ApiTester $I)
    {

        $I->generateService(['name' => 'TEST_SERVICE'.time()]);
        $this->serviceDetails = json_decode($I->grabResponse(), true)['entity'];
        $I->seeResponseIsJson();
        $I->seeResponseContains('{"status":1');
        $I->seeResponseContainsJson([
            'name' => $this->serviceDetails['name']
        ]);
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

<?php


class billapiPlanCest
{

    public $planDetails;

    public function _before(ApiTester $I)
    {
    }
    


    protected function createData(ApiTester $I , $accountDetails = [], $planDetails = [], $serviceDetails = [])
    {
        $I->generatePlan(array_merge(['name' => 'TEST_PLAN_2'.microtime(true)*10000],$planDetails));
        $this->planDetails = json_decode($I->grabResponse(), true)['entity'];
    }


    public function testUniquegetPlan(ApiTester $I)
    {
        $this->createData($I);
        $I->sendBillapiUniqueget(['name'=>$this->planDetails['name']],'plans');
        $I->seeResponseIsJson();
        $I->seeResponseContains('{"status":1');
        $I->seeResponseContainsJson([
                'name' => $this->planDetails['name'],
       ]);
        
    }



}

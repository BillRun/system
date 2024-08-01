<?php


class expectedInvoiceCest
{

    protected $accessToken;
    protected $accountDetails;
    protected $planDetails;
    public function _before(ApiTester $I)
    {
        // echo 'test ';
    }



    protected function CreateData(ApiTester $I)
    {
        $I->generateAccount(['firstname' => 'yossi_test']);
        $this->accountDetails = json_decode($I->grabResponse(), true)['entity'];
        $I->generatePlan(['name' => 'TEST_PLAN_2' . time()]);
        $this->planDetails = json_decode($I->grabResponse(), true)['entity'];
        $I->generateSubscriber(
            [
                'firstname' => 'yossi_test',
                'aid' => $this->accountDetails['aid'],
                'plan' => $this->planDetails['name']
            ]
        );
    }
    public function testExpected_invoice(ApiTester $I)
    {
        $this->CreateData($I);
        $aid = $this->accountDetails['aid'];
        $activeBillRun = Billrun_Billrun::getActiveBillrun();
        $I->sendAuthenticatedGET('/api/accountinvoices?action=expected_invoice&aid=' . $aid . '&billrun_key=' . $activeBillRun);
        $I->seeHttpHeader('Content-Type', 'application/pdf');
    }

}

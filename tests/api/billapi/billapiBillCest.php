<?php

use function PHPUnit\Framework\assertEquals;

class billapiBillCest
{

    public $accountDetails;
   
    public function _before(ApiTester $I)
    {
    }
    


    protected function createData(ApiTester $I , $accountDetails = [])
    {
        $I->createAccountWithAllMandatoryCustomFields(array_merge(['firstname' => 'yossi_test'],$accountDetails));
        return json_decode($I->grabResponse(), true)['entity'];
        
    }

   

   

    /**
     * Test the functionality of retrieving bills for a specific account.
     *
     * This function creates two accounts, makes payments for both, and then
     * verifies that the bills API correctly returns bills for only the specified account.
     *
     * @param ApiTester $I The API tester object used for making API calls and assertions.
     *
     * @return void This function doesn't return a value, but performs assertions on the API response.
     */
    public function testGetBills(ApiTester $I)
    {
        $account1 = $this->createData($I);
        $account2 = $this->createData($I);

        $I->payApi(['aid' =>$account1['aid'], 'amount' => 300, 'dir' => 'tc']);
        $I->payApi(['aid' =>$account2['aid'], 'amount' => 300, 'dir' => 'tc']);
        $I->sendBillapiGet(['aid' => $account1['aid']],'bills');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'aid' => $account1['aid']
        ]);
        $I->dontSeeResponseContainsJson([
            'aid' => $account2['aid']
        ]);


    }



     
    /**
     * TEMP IN COMMENT BECOSE URT IS SET ATUOMATICLY
     * Test the functionality of retrieving bills within a specific URT (Universal Reference Time) range.
     *
     * This function creates an account, makes two payments with different URTs,
     * and then verifies that the bills API correctly returns bills only within
     * the specified URT range.
     *
     * @param ApiTester $I The API tester object used for making API calls and assertions.
     *
     * @return void This function doesn't return a value, but performs assertions on the API response.
     */
    // public function testGetBillsUrtRange(ApiTester $I)
    // {
    //     $account1 = $this->createData($I);

    //     $I->payApi(['aid' =>$account1['aid'], 'amount' => 300, 'dir' => 'tc','urt'=> '2025-01-01 11:46:30']);
    //     $I->payApi(['aid' =>$account1['aid'], 'amount' => 400, 'dir' => 'tc','urt'=> '2025-02-01 11:46:30']);

    //     $I->sendBillapiGet(['aid' => $account1['aid'],"urt"=>['$gte'=>"2000-01-01 11:46:30",'$lte'=>"2025-01-20 11:46:30"]],'bills');
    //     $I->seeResponseCodeIs(200);
    //     $I->seeResponseIsJson();
    //     $I->seeResponseContainsJson([
    //         'aid' => $account1['aid'],
    //         'amount' => 300
    //     ]);
    //     $I->dontSeeResponseContainsJson([
    //         'amount' => 400
    //     ]);
    // }


}

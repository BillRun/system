<?php

use function PHPUnit\Framework\assertEquals;

class apiBillCest
{

    public $accountDetails;
   
    public function _before(ApiTester $I)
    {
        $I->cleanDB();
        \Billrun_Factory::db()->billsCollection()->remove(['_id' => ['$exists' => true]]);
        // The aggregator/etc. test fixtures insert partial config docs (unit_test_config: true)
        // that overwrite the baseline. Remove them so loadDbConfig picks up the original config.
        // \Billrun_Factory::db()->configCollection()->remove(['unit_test_config' => true]);
        // Billrun_Config::getInstance()->loadDbConfig();
    }
    


    protected function createData(ApiTester $I , $accountDetails = [])
    {
        $I->createAccountWithAllMandatoryCustomFields(array_merge(['firstname' => 'yossi_test'],$accountDetails));
        return json_decode($I->grabResponse(), true)['entity'];
        
    }

   

   

   
    public function testGetBalanceByAid(ApiTester $I)
    {
        $account = $this->createData($I);

        $I->payApi(['aid' =>$account['aid'], 'amount' => 300, 'dir' => 'tc']);
        $I->payApi(['aid' =>$account['aid'], 'amount' => 300, 'dir' => 'tc']);
        $I->sendApibill(['aid' => $account['aid']]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'details' => [
                'balance' => [
                    'total' => '600.00',
                    'without_waiting' => '600.00'
                ]
            ]
        ]);
    }



    public function testActionGetBalances(ApiTester $I)
    {
        $account = $this->createData($I);

        $I->payApi(['aid' =>$account['aid'], 'amount' => 300, 'dir' => 'tc']);
        $I->sendApibill(['aids' => $account['aid'],'action' => 'get_balances']);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            "status"=>1,
            "desc"=> "success",
            "input"=> [],
            "details"=> [
                $account['aid'] => [
                    "total"=> "300.00",
                    "without_waiting"=> "300.00",
                    "total_pending_amount"=>0
                ]
        ]
        ]);

        
    

    }

public function testGetCollectionDebtWithInvalidJsonAids(ApiTester $I)
{
    $account = $this->createData($I);
    $invalidJsonAids = '{invalid_json}';
    $I->sendApibill(['aids' => $invalidJsonAids, 'action' => 'collection_debt']);
    $I->seeResponseCodeIs(200);
    $I->seeResponseIsJson();
    $I->seeResponseContainsJson([
        'status' => 0,
        'message' => 'Illegal account ids'
    ]);
}
// $configB = Billrun_Factory::config()->getConfigValue('collection.settings.rejection_required.conditions.customers');

// Billrun_Factory::config()->setConfigValue('collection.settings.rejection_required.conditions.customers', [
//     [
//         'field' => 'aid',
//         'op' => 'exists',
//         'value' => false
//     ]
// ]);
// $configA = Billrun_Factory::config()->getConfigValue('collection.settings.rejection_required.conditions.customers');


public function testGetCollectionDebtWithAids(ApiTester $I)
{
    $account = $this->createData($I);
    $I->payApi(['aid' =>$account['aid'], 'amount' => 300, 'dir' => 'tc']);
    $result = Billrun_Bill::getBalanceByAids([$account['aid']], false, false, true);
    $result = (array)$result[$account['aid']]->getRawData();
    $I->assertEquals('300.00', $result['total']);
    $I->assertEquals($account['aid'], $result['aid']);
}

public function testGetCollectionDebtWithOnlyDebtFalse(ApiTester $I)
{
    $account1 = $this->createData($I, ['firstname' => 'John']);
    $account2 = $this->createData($I, ['firstname' => 'Jane']);
    $I->payApi(['aid' => $account1['aid'], 'amount' => 300, 'dir' => 'fc']);
    $I->payApi(['aid' => $account2['aid'], 'amount' => 200, 'dir' => 'tc']);
    $aids = [$account1['aid'], $account2['aid']];

    $result = Billrun_Bill::getBalanceByAids($aids, false, true, true);
    //check if the response not contain the account with credit balance
    $I->assertArrayNotHasKey($account1['aid'],  $result);
    //check the response contain the account with debt 
    $result = (array)$result[$account2['aid']]->getRawData();
    $I->assertEquals('200.00', $result['total']);
    $I->assertEquals($account2['aid'], $result['aid']);
}

public function testGetCollectionDebtWithDebtAndCredit(ApiTester $I)
{
    $account1 = $this->createData($I, ['firstname' => 'John']);
    $account2 = $this->createData($I, ['firstname' => 'Jane']);
    $I->payApi(['aid' => $account1['aid'], 'amount' => 300, 'dir' => 'fc']);
    $I->payApi(['aid' => $account2['aid'], 'amount' => 200, 'dir' => 'tc']);
    $aids = [$account1['aid'], $account2['aid']];

    $result = Billrun_Bill::getBalanceByAids($aids, false, false, true);
    //check if the response  contain the account with credit/debit balance
    $I->assertArrayHasKey($account1['aid'],  $result);
    $I->assertArrayHasKey($account2['aid'],  $result);

    //check the response contain the account with debt 
    $result2 = (array)$result[$account2['aid']]->getRawData();
    $I->assertEquals('200.00', $result2['total']);
    $I->assertEquals($account2['aid'], $result2['aid']);

     //check the response contain the account with credit 
     $result1 = (array)$result[$account1['aid']]->getRawData();
     $I->assertEquals('-300.00', $result1['total']);
     $I->assertEquals($account1['aid'], $result1['aid']);
}




public function testGetCollectionDebtWithLargeDebtValues(ApiTester $I)
{
    $accountWithLargeDebt = $this->createData($I, ['firstname' => 'large_debt_account']);
    $largeDebtAmount = 999999999999.99; 

    $I->payApi(['aid' => $accountWithLargeDebt['aid'], 'amount' => $largeDebtAmount, 'dir' => 'tc']);
    $aids = [$accountWithLargeDebt['aid']];
    $result = Billrun_Bill::getBalanceByAids($aids, false, true, true);

    $result = (array)$result[$accountWithLargeDebt['aid']]->getRawData();
    $I->assertEquals($largeDebtAmount, $result['total']);
    $I->assertEquals($accountWithLargeDebt['aid'], $result['aid']);
   

}





public function testGetCollectionDebtNoDebt(ApiTester $I)
{
    $account = $this->createData($I);
    $aids = [$account['aid']];
    $result = Billrun_Bill::getBalanceByAids($aids, false, false, true);
    $I->assertEquals([], $result);
}

public function testGetCollectionDebtWithMultipleAids(ApiTester $I)
{
    $account1 = $this->createData($I, ['firstname' => 'John']);
    $account2 = $this->createData($I, ['firstname' => 'Jane']);
    $I->payApi(['aid' => $account1['aid'], 'amount' => 300, 'dir' => 'tc']);
    $I->payApi(['aid' => $account2['aid'], 'amount' => 200, 'dir' => 'tc']);
    $aids = [$account1['aid'], $account2['aid']];

    $result = Billrun_Bill::getBalanceByAids($aids, false, false, true);
    //check if the response  contain the account with credit/debit balance
    $I->assertArrayHasKey($account1['aid'],  $result);
    $I->assertArrayHasKey($account2['aid'],  $result);

    //check the response contain the account2 with debt 
    $result2 = (array)$result[$account2['aid']]->getRawData();
    $I->assertEquals('200.00', $result2['total']);
    $I->assertEquals($account2['aid'], $result2['aid']);

     //check the response contain the account1 with debt 
     $result1 = (array)$result[$account1['aid']]->getRawData();
     $I->assertEquals('300.00', $result1['total']);
     $I->assertEquals($account1['aid'], $result1['aid']);
}
}

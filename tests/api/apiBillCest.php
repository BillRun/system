<?php

use function PHPUnit\Framework\assertEquals;

class apiBillCest
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


public function testGetCollectionDebtWithEmptyAids(ApiTester $I)
{
    $configB = Billrun_Factory::config()->getConfigValue('collection.settings.rejection_required.conditions.customers');

    $account = $this->createData($I);
    $I->payApi(['aid' =>$account['aid'], 'amount' => 300, 'dir' => 'tc']);
    Billrun_Factory::config()->setConfigValue('collection.settings.rejection_required.conditions.customers', [
        [
            'field' => 'aid',
            'op' => 'exists',
            'value' => false
        ]
    ]);
    $configA = Billrun_Factory::config()->getConfigValue('collection.settings.rejection_required.conditions.customers');

    $contractors= Billrun_Bill::getBalanceByAids([$account['aid']], false, false, true);
    $I->assertFalse($result);
    $I->seeResponseCodeIs(400);
    $I->seeResponseContainsJson([
        'status' => 0,
        'desc' => 'Must supply at least one aid'
    ]);
}

public function testGetCollectionDebtWithOnlyDebtFalse(ApiTester $I)
{
    $account1 = $this->createData($I, ['firstname' => 'John']);
    $account2 = $this->createData($I, ['firstname' => 'Jane']);

    $I->payApi(['aid' => $account1['aid'], 'amount' => 300, 'dir' => 'fc']);
    $I->payApi(['aid' => $account2['aid'], 'amount' => 200, 'dir' => 'tc']);

    $aids = json_encode([$account1['aid'], $account2['aid']]);

    $I->sendApibill(['aids' => $aids, 'action' => 'collection_debt', 'only_debt' => false]);
    $I->seeResponseCodeIs(200);
    $I->seeResponseIsJson();
    $I->seeResponseContainsJson([
        'status' => 1,
        'desc' => 'success',
        'details' => [
            $account1['aid'] => [
                'total' => '-300.00',
                'without_waiting' => '-300.00',
            ],
            $account2['aid'] => [
                'total' => '200.00',
                'without_waiting' => '200.00',
            ],
        ]
    ]);
}

public function testGetCollectionDebtWithDebtAndCredit(ApiTester $I)
{
    $account1 = $this->createData($I, ['aid' => 1]);
    $account2 = $this->createData($I, ['aid' => 2]);

    // Add debt to account1
    $I->payApi(['aid' => $account1['aid'], 'amount' => -100, 'dir' => 'fc']);
    
    // Add credit to account2
    $I->payApi(['aid' => $account2['aid'], 'amount' => 50, 'dir' => 'tc']);

    $jsonAids = json_encode([$account1['aid'], $account2['aid']]);
    $I->sendApibill(['action' => 'collection_debt', 'aids' => $jsonAids]);
    
    $I->seeResponseCodeIs(200);
    $I->seeResponseIsJson();
    $I->seeResponseContainsJson([
        'status' => 1,
        'desc' => 'success',
        'details' => [
            $account1['aid'] => [
                'total' => '-100.00',
                'without_waiting' => '-100.00',
                'total_pending_amount' => 0
            ],
            $account2['aid'] => [
                'total' => '50.00',
                'without_waiting' => '50.00',
                'total_pending_amount' => 0
            ]
        ]
    ]);
}


public function testGetCollectionDebtWithEmptyBalances(ApiTester $I)
{
    $mockRequest = $this->getMockBuilder('Yaf_Request_Abstract')
        ->disableOriginalConstructor()
        ->getMock();

    $mockRequest->expects($this->once())
        ->method('get')
        ->with('aids', '[]')
        ->willReturn('["123", "456"]');

    $mockBillrun = $this->getMockBuilder('Billrun_Bill')
        ->disableOriginalConstructor()
        ->getMock();

    $mockBillrun::staticExpects($this->once())
        ->method('getBalanceByAids')
        ->with(['123', '456'], false, true, true)
        ->willReturn([]);

    $billAction = new BillAction();
    $result = $this->invokeMethod($billAction, 'getCollectionDebt', [$mockRequest]);

    $I->assertEquals([], $result, 'Result should be an empty array when no balances are returned');
}

public function testGetCollectionDebtWithLargeDebtValues(ApiTester $I)
{
    $accountWithLargeDebt = $this->createData($I, ['firstname' => 'large_debt_account']);
    $largeDebtAmount = 999999999999.99; // A very large debt value

    // Simulate a large debt for the account
    $I->payApi(['aid' => $accountWithLargeDebt['aid'], 'amount' => $largeDebtAmount, 'dir' => 'fc']);

    $requestData = [
        'aids' => json_encode([$accountWithLargeDebt['aid']]),
        'action' => 'collection_debt'
    ];

    $I->sendApibill($requestData);
    $I->seeResponseCodeIs(200);
    $I->seeResponseIsJson();

    $response = json_decode($I->grabResponse(), true);
    
    $I->assertEquals(1, $response['status']);
    $I->assertEquals('success', $response['desc']);
    $I->assertArrayHasKey('details', $response);
    $I->assertArrayHasKey($accountWithLargeDebt['aid'], $response['details']);
    
    $accountDebt = $response['details'][$accountWithLargeDebt['aid']];
    $I->assertEquals($largeDebtAmount, floatval($accountDebt['total']));
    $I->assertEquals($largeDebtAmount, floatval($accountDebt['without_waiting']));
    $I->assertEquals(0, $accountDebt['total_pending_amount']);
}

public function testGetCollectionDebtWithUnicodeAccountIds(ApiTester $I)
{
    $account1 = $this->createData($I, ['firstname' => 'José']);
    $account2 = $this->createData($I, ['firstname' => 'Россия']);

    $I->payApi(['aid' => $account1['aid'], 'amount' => 100, 'dir' => 'fc']);
    $I->payApi(['aid' => $account2['aid'], 'amount' => 200, 'dir' => 'fc']);

    $aids = json_encode([$account1['aid'], $account2['aid']]);
    $I->sendApibill(['aids' => $aids, 'action' => 'collection_debt']);

    $I->seeResponseCodeIs(200);
    $I->seeResponseIsJson();
    $I->seeResponseContainsJson([
        'status' => 1,
        'desc' => 'success',
        'details' => [
            $account1['aid'] => [
                'total' => '100.00',
                'without_waiting' => '100.00',
            ],
            $account2['aid'] => [
                'total' => '200.00',
                'without_waiting' => '200.00',
            ],
        ],
    ]);
}

public function testGetCollectionDebtWithLargeNumberOfAids(ApiTester $I)
{
    $numberOfAids = 10000;
    $aids = range(1, $numberOfAids);
    $jsonAids = json_encode($aids);

    $mockRequest = $this->getMockBuilder('Yaf_Request_Abstract')
        ->disableOriginalConstructor()
        ->getMock();
    $mockRequest->expects($this->once())
        ->method('get')
        ->with('aids', '[]')
        ->willReturn($jsonAids);

    $billAction = new BillAction();
    
    $startTime = microtime(true);
    $result = $billAction->getCollectionDebt($mockRequest);
    $endTime = microtime(true);

    $executionTime = $endTime - $startTime;
    
    $I->assertLessThan(5, $executionTime, 'Execution time should be less than 5 seconds');
    $I->assertCount($numberOfAids, $result, 'Result should contain all account IDs');
    $I->assertArrayHasKey('1', $result, 'Result should contain the first account ID');
    $I->assertArrayHasKey(strval($numberOfAids), $result, 'Result should contain the last account ID');
}

public function testGetCollectionDebtNoDebt(ApiTester $I)
{
    $account1 = $this->createData($I);
    $account2 = $this->createData($I);

    // Ensure accounts have no debt
    $I->payApi(['aid' => $account1['aid'], 'amount' => 100, 'dir' => 'tc']);
    $I->payApi(['aid' => $account2['aid'], 'amount' => 100, 'dir' => 'tc']);

    $I->sendApibill(['aids' => json_encode([$account1['aid'], $account2['aid']]), 'action' => 'collection_debt']);
    $I->seeResponseCodeIs(200);
    $I->seeResponseIsJson();
    $I->seeResponseContainsJson([
        'status' => 1,
        'desc' => 'success',
        'details' => []
    ]);
}

public function testGetCollectionDebtWithMultipleAids(ApiTester $I)
{
    $account1 = $this->createData($I);
    $account2 = $this->createData($I);
    
    $I->payApi(['aid' => $account1['aid'], 'amount' => 100, 'dir' => 'fc']);
    $I->payApi(['aid' => $account2['aid'], 'amount' => 200, 'dir' => 'fc']);
    
    $aids = json_encode([$account1['aid'], $account2['aid']]);
    $I->sendApibill(['aids' => $aids, 'action' => 'collection_debt']);
    
    $I->seeResponseCodeIs(200);
    $I->seeResponseIsJson();
    $I->seeResponseContainsJson([
        'status' => 1,
        'desc' => 'success',
        'details' => [
            $account1['aid'] => [
                'total' => '100.00',
                'without_waiting' => '100.00',
                'total_pending_amount' => 0
            ],
            $account2['aid'] => [
                'total' => '200.00',
                'without_waiting' => '200.00',
                'total_pending_amount' => 0
            ]
        ]
    ]);
}
}

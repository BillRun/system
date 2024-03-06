<?php

class bcCest
{

    public function updateRowT(ApiTester $I)
    {
        $I->sendAuthenticatedGET('/test/updaterowt?rebalance=1');
        $response = $I->grabResponse();
        $I->assertStringContainsString('<strong>0</strong> fails and <strong>0</strong> exceptions', $response);
    }

}

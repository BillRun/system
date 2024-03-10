<?php

class bcCest
{

    public function updateRowT(ApiTester $I)
    {
        $I->sendAuthenticatedGET('/test/updaterowt?rebalance=1');
        $response = $I->grabResponse();
        $I->assertRegExp('/<strong>[1-9]\d*<\/strong> passes, <strong>0<\/strong> fails/', $response);
    }

    public function Aggregatortest(ApiTester $I)
    {
        $I->sendAuthenticatedGET('/test/Aggregatortest?skip=28,29,30,32,48,50,52,54,56,57,58,60,61,62,63,64,65,71,68,69,72');
        $response = $I->grabResponse();
        $I->assertRegExp('/<strong>[1-9]\d*<\/strong> passes, <strong>0<\/strong> fails/', $response);
    }

    public function RateTest(ApiTester $I)
    {
        $I->sendAuthenticatedGET('/test/RateTest?skip=d21,e21');
        $response = $I->grabResponse();
        $I->assertRegExp('/<strong>[1-9]\d*<\/strong> passes, <strong>0<\/strong> fails/', $response);
    }

    public function monthsdifftest(ApiTester $I)
    {
        $I->sendAuthenticatedGET('/test/monthsdifftest');
        $response = $I->grabResponse();
        $I->assertRegExp('/<strong>[1-9]\d*<\/strong> passes, <strong>0<\/strong> fails/', $response);
    }

    public function CustomerCalculatorTest(ApiTester $I)
    {
        $I->sendAuthenticatedGET('/test/CustomerCalculatorTest');
        $response = $I->grabResponse();
        $I->assertRegExp('/<strong>[1-9]\d*<\/strong> passes, <strong>0<\/strong> fails/', $response);
    }

    public function Taxmappingtest(ApiTester $I)
    {
        $I->sendAuthenticatedGET('/test/Taxmappingtest');
        $response = $I->grabResponse();
        $I->assertRegExp('/<strong>[1-9]\d*<\/strong> passes, <strong>0<\/strong> fails/', $response);
    }

    public function discounttest(ApiTester $I)
    {
        $I->sendAuthenticatedGET('/test/discounttest?skip=13,27,28,55');
        $response = $I->grabResponse();
        $I->assertRegExp('/<strong>[1-9]\d*<\/strong> passes, <strong>0<\/strong> fails/', $response);
    }

}

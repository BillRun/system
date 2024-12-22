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
        $I->sendAuthenticatedGET('/test/Aggregatortest?skip=30,61,62,63,65,71,73,74,76,76,185-1,185-2,185-3,185-5,185-5,185-4,185-6,185-7,185-7,303439,333439,563439,573439,613439,623439,633439,653439,693439,703439,713439,103439,10,753439');
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

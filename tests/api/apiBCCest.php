<?php

class bcCest
{

    public function updateRowT(ApiTester $I)
    {
        $I->sendAuthenticatedGET('/test/updaterowt?rebalance=1');
        $response = substr($I->grabResponse(), -110);
        $I->assertStringContainsString('<strong>0</strong> fails and <strong>0</strong> exceptions', $response);
    }

    public function Aggregatortest(ApiTester $I)
    {
        $I->sendAuthenticatedGET('/test/Aggregatortest?skip=1,2,40');
        $response = $I->grabResponse();
        $I->assertStringContainsString('<strong>0</strong> fails and <strong>0</strong> exceptions', $response);
    }

    public function RateTest(ApiTester $I)
    {
        $I->sendAuthenticatedGET('/test/RateTest');
        $response = $I->grabResponse();
        $I->assertStringContainsString('<strong>0</strong> fails and <strong>0</strong> exceptions', $response);
    }

    public function monthsdifftest(ApiTester $I)
    {
        $I->sendAuthenticatedGET('/test/monthsdifftest');
        $response = $I->grabResponse();
        $I->assertStringContainsString('<strong>0</strong> fails and <strong>0</strong> exceptions', $response);
    }

    public function CustomerCalculatorTest(ApiTester $I)
    {
        $I->sendAuthenticatedGET('/test/CustomerCalculatorTest');
        $response = $I->grabResponse();
        $I->assertStringContainsString('<strong>0</strong> fails and <strong>0</strong> exceptions', $response);
    }

    public function Taxmappingtest(ApiTester $I)
    {
        $I->sendAuthenticatedGET('/test/Taxmappingtest');
        $response = $I->grabResponse();
        $I->assertStringContainsString('<strong>0</strong> fails and <strong>0</strong> exceptions', $response);
    }

    public function discounttest(ApiTester $I)
    {
        $I->sendAuthenticatedGET('/test/discounttest');
        $response = $I->grabResponse();
        $I->assertStringContainsString('<strong>0</strong> fails and <strong>0</strong> exceptions', $response);
    }

}

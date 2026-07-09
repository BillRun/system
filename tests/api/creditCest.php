<?php

/**
 * API test for the Credit action (/api/credit).
 *
 * Regression guard for the Yaf 3.3 reserved-property bug (BRCD-3318):
 * CreditAction stored the request params in $this->request, which is a reserved
 * internal property of Yaf_Controller_Abstract on yaf >= 3.2 (PHP 8.5 image).
 * Writing it is silently blocked and reading it returns the Yaf_Request_Http
 * object, so on PHP 8.5 the call died with:
 *   "Cannot use object of type Yaf_Request_Http as array"
 * On PHP 7.4 (yaf 3.1.4) the same property was a normal property, so it worked.
 * After renaming the property to $this->requestData this test passes on both.
 */
class creditCest
{
    public function _before(ApiTester $I)
    {
        $I->cleanDB();
    }

    public function creditApiCreatesCredit(ApiTester $I)
    {
        // a rate must exist for the credit to resolve (Credit::parse -> getRateByName)
        $I->generateRate(['key' => 'TEST_CREDIT_RATE']);

        $I->amBearerAuthenticated($I->getAccessToken());
        $I->sendGet('/api/credit', [
            'sid'         => 1,
            'aid'         => 1,
            'rate'        => 'TEST_CREDIT_RATE',
            'aprice'      => 24,
            'credit_time' => '2025-01-20T10:00:00Z',
            'usagev'      => 1,
        ]);

        $I->seeResponseIsJson();
        // pre-fix on yaf >= 3.2 this returned:
        //   {"status":0,"code":500,"data":{"message":"Cannot use object of type Yaf_Request_Http as array\n"}}
        $I->dontSeeResponseContains('Cannot use object of type Yaf_Request_Http as array');
        $I->seeResponseContainsJson(['status' => 1]);
    }
}

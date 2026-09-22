<?php

/**
 * API test for the Reports action (/api/reports).
 *
 * Regression guard for the Yaf 3.3 reserved-property bug (BRCD-3318):
 * ReportsAction used BOTH reserved Yaf controller properties as its own storage -
 * $this->request (params array) and $this->response (result array). On yaf >= 3.2
 * (PHP 8.5) these are internal, write-blocked properties that read back as the Yaf
 * request/response objects, so the call died with:
 *   "Cannot use object of type Yaf_Request_Http as array"  (at $this->request['action'])
 * It worked on PHP 7.4 (yaf 3.1.4). After renaming to $this->requestData /
 * $this->responseData this test passes on both.
 */
class reportsCest
{
    public function _before(ApiTester $I)
    {
        $I->cleanDB();
    }

    public function reportsApiReturnsData(ApiTester $I)
    {
        $I->amBearerAuthenticated($I->getAccessToken());
        $I->sendGet('/api/reports', [
            'action' => 'totalNumOfCustomers',
        ]);

        $I->seeResponseIsJson();
        // pre-fix on yaf >= 3.2 this returned:
        //   {"status":0,"code":500,"data":{"message":"Cannot use object of type Yaf_Request_Http as array\n"}}
        $I->dontSeeResponseContains('Cannot use object of type Yaf_Request_Http as array');
        $I->dontSeeResponseContains('"code":500');
        $I->seeResponseContainsJson(['status' => true]);
    }
}

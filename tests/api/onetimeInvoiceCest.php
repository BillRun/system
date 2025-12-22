<?php


class onetimeInvoiceCest
{  
    protected $configModel;
    protected $accessToken;
    protected $accountDetails;
    protected $planDetails;
    protected $subscriberDetails;

    public function _before(ApiTester $I)
    {
        $this->configModel = new ConfigModel();
        $this->cleanDB();
    }

    protected function cleanDB() {
        $plans = Billrun_Factory::db()->plansCollection();
        $plans->remove(['name' => "ONETIME_INVOICE_TEST_PLAN"]);
        $rates = Billrun_Factory::db()->ratesCollection();
        $rates->remove(['key' => "ONETIME_INVOICE_TEST_RATE"]);

        Billrun_Config::getInstance()->loadDbConfig();
        $current_conf = $this->configModel->getConfig();
        if (isset($current_conf['billrun']['immediate_invoice'])) {
            if (isset($current_conf['billrun']['immediate_invoice']['min_backdate'])) {
                unset($current_conf['billrun']['immediate_invoice']['min_backdate']);
            }
        }
        $this->configModel->setConfig($current_conf);
        Billrun_Config::getInstance()->loadDbConfig();
    }

    protected function CreateData(ApiTester $I)
    {
        $I->createAccountWithAllMandatorySystemFields([]);
        $this->accountDetails = json_decode($I->grabResponse(), true)['entity'];
        $I->generatePlan(['name' => 'ONETIME_INVOICE_TEST_PLAN']);
        $this->planDetails = json_decode($I->grabResponse(), true)['entity'];
        $I->generateSubscriber(
            [
                'firstname' => 'onetime_invoice_test',
                'lastname' => 'onetime_invoice_test',
                'aid' => $this->accountDetails['aid'],
                'plan' => $this->planDetails['name']
            ]
        );
        $this->subscriberDetails = json_decode($I->grabResponse(), true)['entity'];
        $BaseRateDetails = [
            'key' => 'ONETIME_INVOICE_TEST_RATE',
            "rates" => [
                "call" => [
                    "BASE" => [
                        "rate" => [
                            [
                                "from" => 0,
                                "to" => "UNLIMITED",
                                "interval" => 1,
                                "price" => 1,
                                "uom_display" => [
                                    "range" => "seconds",
                                    "interval" => "seconds"
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $I->generateRate(array_merge(['tariff_category' => 'retail', 'key' => microtime(true) * 10000], $BaseRateDetails));
    }

    /**
     * Function to set min backdate configuration
     */
    public function setMinBackdateConfiguration() {
        $min_backdate = [
            "anchor_field" => "now",
		    "relative_time" => ["-1 days"]
        ];
        Billrun_Config::getInstance()->loadDbConfig();
        $current_conf = $this->configModel->getConfig();
        Billrun_Util::setIn($current_conf , 'billrun.immediate_invoice.min_backdate', $min_backdate);
        $this->configModel->setConfig($current_conf);
        Billrun_Config::getInstance()->loadDbConfig();
    }

    public function testOnetime_invoice_min_backdate(ApiTester $I)
    {
        $this->CreateData($I);
        //BC
        $credit_time = date('Y-m-d\TH:i:s.v\Z', strtotime("-4 days"));
        $I->sendAuthenticatedGET('/api/onetimeinvoice?cdrs=[{"aid":' . $this->accountDetails['aid'] . ',"sid":' . $this->subscriberDetails['sid'] . ',"rate":"ONETIME_INVOICE_TEST_RATE","credit_time":"' . $credit_time . '","usagev":1,"type":"credit","aprice":10}]&aid=' . $this->accountDetails['aid'] . '&send_email=0&step=0&allow_bill=1&expected=0&invoice_unixtime=' . time());
        $I->dontSeeResponseContainsJson([
            'status' => 0
        ]);
        //Set new configuration
        $this->setMinBackdateConfiguration();

        //Check api with old credit time of 1 CDR out of 2
        $allowed_credit_time = date('Y-m-d\TH:i:s.v\Z', strtotime("now"));
        $I->sendAuthenticatedGET('/api/onetimeinvoice?cdrs=[{"aid":' . $this->accountDetails['aid'] . ',"sid":' . $this->subscriberDetails['sid'] . ',"rate":"ONETIME_INVOICE_TEST_RATE","credit_time":"' . $credit_time . '","usagev":1,"type":"credit","aprice":10},{"aid":' . $this->accountDetails['aid'] . ',"sid":' . $this->subscriberDetails['sid'] . ',"rate":"ONETIME_INVOICE_TEST_RATE","credit_time":"' . $allowed_credit_time . '","usagev":1,"type":"credit","aprice":11}]&aid=' . $this->accountDetails['aid'] . '&send_email=0&step=0&allow_bill=1&expected=0&invoice_unixtime=' . time());
        $I->seeResponseContainsJson([
            'code' => 17579
        ]);

        //Old invoice unixtime
        $allowed_credit_time = date('Y-m-d\TH:i:s.v\Z', strtotime("now"));
        $I->sendAuthenticatedGET('/api/onetimeinvoice?cdrs=[{"aid":' . $this->accountDetails['aid'] . ',"sid":' . $this->subscriberDetails['sid'] . ',"rate":"ONETIME_INVOICE_TEST_RATE","credit_time":"' . $allowed_credit_time . '","usagev":1,"type":"credit","aprice":11}]&aid=' . $this->accountDetails['aid'] . '&send_email=0&step=0&allow_bill=1&expected=0&invoice_unixtime=' . strtotime("-5 days"));
        $I->seeResponseContainsJson([
            'code' => 17579
        ]);
    }

}
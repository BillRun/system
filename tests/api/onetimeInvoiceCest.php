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
        $I->cleanDB();
        $this->resetBackdateConfig();
    }

    protected function resetBackdateConfig() {
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

    protected function createData(ApiTester $I)
    {
        $I->createAccountWithAllMandatorySystemFields([]);
        $this->accountDetails = json_decode($I->grabResponse(), true)['entity'];
        $I->generatePlan(['name' => 'ONETIME_INVOICE_TEST_PLAN']);
        $this->planDetails = json_decode($I->grabResponse(), true)['entity'];
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
        $this->createData($I);
        $aid = $this->accountDetails['aid'];
        $rate = "ONETIME_INVOICE_TEST_RATE";
        $aprice = 10;
        //BC
        $credit_time = date('Y-m-d\TH:i:s.v\Z', strtotime("-4 days"));
        $cdr = ["aid" => $aid, "rate" => $rate, "credit_time" => $credit_time, "aprice" => $aprice];
        $I->sendOnetimeInvoiceApi($this->getCdrs([$cdr]), $aid, ['send_email' => 0, 'step' => 0]);
        $I->dontSeeResponseContainsJson([
            'status' => 0
        ]);
        //Set new configuration
        $this->setMinBackdateConfiguration();

        //Check api with old credit time of 1 CDR out of 2
        $cdr_allowed_credit_time = $cdr;
        unset($cdr_allowed_credit_time['credit_time']);
        $I->sendOnetimeInvoiceApi($this->getCdrs([$cdr, $cdr_allowed_credit_time]), $aid, ['send_email' => 0, 'step' => 0]);
        $I->seeResponseContainsJson([
            'code' => 17579
        ]);

        //Old invoice unixtime
        $I->sendOnetimeInvoiceApi($this->getCdrs([$cdr_allowed_credit_time]), $aid, ['send_email' => 0, 'step' => 0, 'invoice_unixtime' => strtotime("-5 days")]);
        $I->seeResponseContainsJson([
            'code' => 17579
        ]);
    }

    public function getCdrs($cdrs) {
        foreach ($cdrs as &$cdr) {
            $cdr['sid'] = isset($cdr['sid']) ? $cdr['sid'] : 0;
            $cdr['usagev'] = isset($cdr['usagev']) ? $cdr['usagev'] : 1;
            $cdr['type'] = isset($cdr['type']) ? $cdr['type'] : 'credit';
            $cdr['credit_time'] = isset($cdr['credit_time']) ? $cdr['credit_time'] : date('Y-m-d\TH:i:s.v\Z', strtotime("now"));
        }
        return $cdrs;
    }

}
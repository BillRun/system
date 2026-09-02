<?php


class onetimeInvoiceCest
{
    protected $configModel;
    protected $accessToken;
    protected $accountDetails;
    protected $planDetails;
    protected $subscriberDetails;
    protected $originalLinesFields = null;

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

    public function _after(ApiTester $I)
    {
        $this->restoreLinesFieldsConfiguration($I);
    }

    /**
     * Add foreign account & subscriber fields to lines configuration,
     * keeping a snapshot of the original configuration for restore
     */
    protected function setForeignFieldsConfiguration(ApiTester $I) {
        $test_field_names = ['foreign.account.firstname', 'foreign.subscriber.plan'];
        $this->originalLinesFields = $I->getSettings('lines', [])['details']['fields'];
        // work on a filtered copy so the test fields are not added twice if they already exist
        $fields = array_values(array_filter($this->originalLinesFields, function ($field) use ($test_field_names) {
            return !in_array($field['field_name'] ?? '', $test_field_names);
        }));
        $fields[] = [
            'field_name' => 'foreign.account.firstname',
            'foreign' => ['entity' => 'account', 'field' => 'firstname']
        ];
        $fields[] = [
            'field_name' => 'foreign.subscriber.plan',
            'foreign' => ['entity' => 'subscriber', 'field' => 'plan']
        ];
        $I->setSettings('lines.fields', $fields);
    }

    /**
     * Restore lines configuration to its original state
     */
    protected function restoreLinesFieldsConfiguration(ApiTester $I) {
        if (!is_null($this->originalLinesFields)) {
            $I->setSettings('lines.fields', $this->originalLinesFields);
            $this->originalLinesFields = null;
        }
    }

    public function testOnetime_invoice_foreign_fields(ApiTester $I)
    {
        $this->setForeignFieldsConfiguration($I);
        $this->createData($I);
        $aid = $this->accountDetails['aid'];
        $I->generateSubscriber(['aid' => $aid, 'plan' => 'ONETIME_INVOICE_TEST_PLAN']);
        $sid = json_decode($I->grabResponse(), true)['entity']['sid'];

        $accountCdr = ["aid" => $aid, "sid" => 0, "rate" => "ONETIME_INVOICE_TEST_RATE", "aprice" => 10];
        $subscriberCdr = ["aid" => $aid, "sid" => $sid, "rate" => "ONETIME_INVOICE_TEST_RATE", "aprice" => 20];
        $I->sendOnetimeInvoiceApi($this->getCdrs([$accountCdr, $subscriberCdr]), $aid, ['send_email' => 0, 'step' => 0]);
        $I->dontSeeResponseContainsJson([
            'status' => 0
        ]);

        // account level line (sid=0) - customer calculator should run and save foreign account fields
        $accountLine = $I->grabFromCollection('lines', ['type' => 'credit', 'aid' => $aid, 'sid' => 0]);
        $I->assertEquals('yossi', Billrun_Util::getIn($accountLine, 'foreign.account.firstname'));
        $I->assertEquals(10, $accountLine['aprice']);

        // subscriber level line - both account & subscriber foreign fields should be saved
        $subscriberLine = $I->grabFromCollection('lines', ['type' => 'credit', 'aid' => $aid, 'sid' => $sid]);
        $I->assertEquals('yossi', Billrun_Util::getIn($subscriberLine, 'foreign.account.firstname'));
        $I->assertEquals('ONETIME_INVOICE_TEST_PLAN', Billrun_Util::getIn($subscriberLine, 'foreign.subscriber.plan'));
        $I->assertEquals(20, $subscriberLine['aprice']);
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
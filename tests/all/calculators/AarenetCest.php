<?php

use function PHPUnit\Framework\assertCount;

class unifyCest
{

    public $accountDetails;
    public $planDetails;
    public $serviceDetails;
    public $subscriberDetails;
    public $rateDetails;

    public static $isIPSet = false;
    public function _before(ApiTester $I)
    {
        if (!self::$isIPSet) {
            $this->setUP($I);
            self::$isIPSet = true;
            Billrun_Config::getInstance()->loadDbConfig();
            $this->createServices($I);
        }
    }
    protected function setUP(ApiTester $I, $inputProcessor = null)
    {
        $inputProcessor = $inputProcessor ?: $this->inputProcessor;
        $I->setSettings('file_types', $inputProcessor);
        $type = [

            [
                "usage_type" => "call",
                "label" => "call",
                "property_type" => "time",
                "invoice_uom" => "seconds",
                "input_uom" => "seconds"
            ]
        ];
        $I->setSettings('usage_types', $type);
    }

    //workaround for the issue with service instence (not update the service list in the 2nd process on the same run)
    protected function createServices(ApiTester $I)
    {
        //create all the services for all tests once , before the tests
        $services = [
            [
                'from' => '2025-01-01',
                "include" => [
                    "groups" => [
                        "LOCAL_CALLS_5000" => [
                            "account_shared" => false,
                            "account_pool" => false,
                            "rates" => [
                                "CALL"
                            ],
                            "value" => 300000,
                            "usage_types" => [
                                "call" => [
                                    "unit" => "minutes"
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                'from' => '2025-01-01',
                "include" => [
                    "groups" => [
                        "2LOCAL_CALLS_5000" => [
                            "account_shared" => false,
                            "account_pool" => false,
                            "rates" => [
                                "CALL2"
                            ],
                            "value" => 300000,
                            "usage_types" => [
                                "call" => [
                                    "unit" => "minutes"
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        foreach ($services as $service) {
            $I->generateService(array_merge(
                ['name' => 'TEST_SERVICE' . microtime(true) * 10000],
                $service
            ));
            $this->serviceDetails[] = json_decode($I->grabResponse(), true)['entity'];
        }
    }
    protected function createData(ApiTester $I, $accountDetails = [], $planDetails = [], $serviceDetails = [], $rateDetails = [])
    {
        if ($accountDetails != []) {
            $I->createAccountWithAllMandatoryCustomFields(array_merge(['firstname' => 'yossi_test'], $accountDetails));
            $this->accountDetails = json_decode($I->grabResponse(), true)['entity'];
        }
        if ($planDetails != []) {
            $I->generatePlan(array_merge(['name' => 'TEST_PLAN_2' . microtime(true) * 10000], $planDetails));
            $this->planDetails = json_decode($I->grabResponse(), true)['entity'];
        }
        // if ($serviceDetails != []) {
        //     $I->generateService(array_merge(
        //         ['name' => 'TEST_SERVICE' . microtime(true) * 10000],
        //         $serviceDetails
        //     ));
        //     $this->serviceDetails = json_decode($I->grabResponse(), true)['entity'];
        // }
        if ($rateDetails != []) {
            $I->generateRate(array_merge(['tariff_category' => 'retail', 'key' => microtime(true) * 10000], $rateDetails));
            $this->rateDetails = json_decode($I->grabResponse(), true)['entity'];
        }
    }
    public $inputProcessor =  [
        "file_type"=> "Aarenet",
        "parser"=> [
            "type"=> "separator",
            "line_types"=> [
                "H"=> "\/^none$\/",
                "D"=> "\/\/",
                "T"=> "\/^none$\/"
            ],
            "separator"=> ",",
            "structure"=> [
                [
                    "name"=> "Call_Start_ms",
                    "checked"=> true
                ],
                [
                    "name"=> "Call_Start",
                    "checked"=> true
                ],
                [
                    "name"=> "Call_End_ms",
                    "checked"=> true
                ],
                [
                    "name"=> "Call_End",
                    "checked"=> true
                ],
                [
                    "name"=> "Duration",
                    "checked"=> true
                ],
                [
                    "name"=> "Acc_Account_ID",
                    "checked"=> true
                ],
                [
                    "name"=> "Acc_Address_ID",
                    "checked"=> true
                ],
                [
                    "name"=> "Acc_Tenant_ID",
                    "checked"=> true
                ],
                [
                    "name"=> "Acc_Number",
                    "checked"=> true
                ],
                [
                    "name"=> "Acc_Tenant",
                    "checked"=> true
                ],
                [
                    "name"=> "Acc_Name",
                    "checked"=> true
                ],
                [
                    "name"=> "Acc_Address",
                    "checked"=> true
                ],
                [
                    "name"=> "Acc_Address_Public",
                    "checked"=> true
                ],
                [
                    "name"=> "Acc_Address_Combined",
                    "checked"=> true
                ],
                [
                    "name"=> "Orig_Number",
                    "checked"=> true
                ],
                [
                    "name"=> "Dest_Name",
                    "checked"=> true
                ],
                [
                    "name"=> "Dest_Number",
                    "checked"=> true
                ],
                [
                    "name"=> "Dest_Type",
                    "checked"=> true
                ],
                [
                    "name"=> "Dest_Tenant",
                    "checked"=> true
                ],
                [
                    "name"=> "Dest_Tenant_ID",
                    "checked"=> true
                ],
                [
                    "name"=> "Pricelist_ID",
                    "checked"=> true
                ],
                [
                    "name"=> "Pricelist_Table",
                    "checked"=> true
                ],
                [
                    "name"=> "Tariff",
                    "checked"=> true
                ],
                [
                    "name"=> "PostRating",
                    "checked"=> true
                ],
                [
                    "name"=> "Charge_Account",
                    "checked"=> true
                ],
                [
                    "name"=> "Charge_Tenant",
                    "checked"=> true
                ],
                [
                    "name"=> "Charge_System",
                    "checked"=> true
                ],
                [
                    "name"=> "Call_Leg",
                    "checked"=> true
                ],
                [
                    "name"=> "Orig_IP",
                    "checked"=> true
                ],
                [
                    "name"=> "Dest_IP",
                    "checked"=> true
                ],
                [
                    "name"=> "Cdr_ID",
                    "checked"=> true
                ],
                [
                    "name"=> "Call_ID",
                    "checked"=> true
                ],
                [
                    "name"=> "Alert_ms",
                    "checked"=> true
                ],
                [
                    "name"=> "Alert_seconds",
                    "checked"=> true
                ],
                [
                    "name"=> "Orig_Gateway",
                    "checked"=> true
                ],
                [
                    "name"=> "Dest_Gateway",
                    "checked"=> true
                ],
                [
                    "name"=> "Pres_Preferred",
                    "checked"=> true
                ],
                [
                    "name"=> "Pres_Asserted",
                    "checked"=> true
                ],
                [
                    "name"=> "Cause",
                    "checked"=> false
                ],
                [
                    "name"=> "Flags",
                    "checked"=> false
                ],
                [
                    "name"=> "Scope",
                    "checked"=> true
                ],
                [
                    "name"=> "Acc_Number_Private",
                    "checked"=> false
                ],
                [
                    "name"=> "Call_Type",
                    "checked"=> false
                ],
                [
                    "name"=> "Billing_Info",
                    "checked"=> false
                ],
                [
                    "name"=> "SIP_Call_ID",
                    "checked"=> true
                ],
                [
                    "name"=> "Q_850_Cause",
                    "checked"=> false
                ],
                [
                    "name"=> "Dest_Acc_ID",
                    "checked"=> false
                ],
                [
                    "name"=> "Dest_Acc_Name",
                    "checked"=> true
                ],
                [
                    "name"=> "Dest_Addr_ID",
                    "checked"=> false
                ],
                [
                    "name"=> "Dest_Addr_Number",
                    "checked"=> false
                ],
                [
                    "name"=> "Outbound_Dest",
                    "checked"=> false
                ]
            ],
            "csv_has_header"=> true,
            "csv_has_footer"=> false,
            "custom_keys"=> [
                "Call_Start_ms",
                "Call_Start",
                "Call_End_ms",
                "Call_End",
                "Duration",
                "Acc_Account_ID",
                "Acc_Address_ID",
                "Acc_Tenant_ID",
                "Acc_Number",
                "Acc_Tenant",
                "Acc_Name",
                "Acc_Address",
                "Acc_Address_Public",
                "Acc_Address_Combined",
                "Orig_Number",
                "Dest_Name",
                "Dest_Number",
                "Dest_Type",
                "Dest_Tenant",
                "Dest_Tenant_ID",
                "Pricelist_ID",
                "Pricelist_Table",
                "Tariff",
                "PostRating",
                "Charge_Account",
                "Charge_Tenant",
                "Charge_System",
                "Call_Leg",
                "Orig_IP",
                "Dest_IP",
                "Cdr_ID",
                "Call_ID",
                "Alert_ms",
                "Alert_seconds",
                "Orig_Gateway",
                "Dest_Gateway",
                "Pres_Preferred",
                "Pres_Asserted",
                "Scope",
                "SIP_Call_ID",
                "Dest_Acc_Name"
            ]
        ],
        "processor"=> [
            "type"=> "Usage",
            "date_field"=> "Call_Start",
            "usaget_mapping"=> [
                [
                    "src_field"=> "Tariff",
                    "conditions"=> [
                        [
                            "src_field"=> "Tariff",
                            "pattern"=> "^INA_([1][0-9][4])$",
                            "op"=> "$regex",
                            "op_label"=> "Regex"
                        ]
                    ],
                    "pattern"=> "^INA_([1][0-9][4])$",
                    "usaget"=> "ina_vas_call",
                    "unit"=> "seconds",
                    "volume_type"=> "field",
                    "volume_src"=> [
                        "Duration"
                    ]
                ],
                [
                    "src_field"=> "Scope",
                    "conditions"=> [
                        [
                            "src_field"=> "Scope",
                            "pattern"=> "IN",
                            "op"=> "$eq",
                            "op_label"=> "Equals"
                        ]
                    ],
                    "pattern"=> "IN",
                    "usaget"=> "voip_incoming",
                    "unit"=> "seconds",
                    "volume_type"=> "field",
                    "volume_src"=> [
                        "Duration"
                    ]
                ],
                [
                    "src_field"=> "Scope",
                    "conditions"=> [
                        [
                            "src_field"=> "Scope",
                            "pattern"=> [
                                "LS",
                                "OP",
                                "LO"
                            ],
                            "op"=> "$in",
                            "op_label"=> "In"
                        ]
                    ],
                    "pattern"=> "LS,OP,LO",
                    "usaget"=> "voip",
                    "unit"=> "seconds",
                    "volume_type"=> "field",
                    "volume_src"=> [
                        "Duration"
                    ]
                ]
            ],
            "date_format"=> "Y-m-d-H-i-s",
            "orphan_files_time"=> "6 hours"
        ],
        "customer_identification_fields"=> [
            "voip_incoming"=> [
                [
                    "target_key"=> "account_name",
                    "src_key"=> "Dest_Acc_Name",
                    "conditions"=> [
                        [
                            "field"=> "usaget",
                            "regex"=> "\/.*\/"
                        ]
                    ],
                    "clear_regex"=> "\/\/"
                ]
            ],
            "ina_vas_call"=> [
                [
                    "target_key"=> "account_name",
                    "src_key"=> "Acc_Name",
                    "conditions"=> [
                        [
                            "field"=> "usaget",
                            "regex"=> "\/.*\/"
                        ]
                    ],
                    "clear_regex"=> "\/\/"
                ]
            ],
            "voip"=> [
                [
                    "target_key"=> "account_name",
                    "src_key"=> "Acc_Name",
                    "conditions"=> [
                        [
                            "field"=> "usaget",
                            "regex"=> "\/.*\/"
                        ]
                    ],
                    "clear_regex"=> "\/\/"
                ]
            ]
        ],
        "rate_calculators"=> [
            "retail"=> [
                "voip_incoming"=> [
                    [
                        [
                            "type"=> "match",
                            "rate_key"=> "usaget",
                            "line_key"=> "usaget"
                        ]
                    ]
                ],
                "ina_vas_call"=> [
                    [
                        [
                            "type"=> "match",
                            "rate_key"=> "key",
                            "line_key"=> "computed",
                            "computed"=> [
                                "line_keys"=> [
                                    [
                                        "key"=> "Tariff"
                                    ]
                                ],
                                "operator"=> "$exists",
                                "type"=> "condition",
                                "must_met"=> true,
                                "projection"=> [
                                    "on_true"=> [
                                        "key"=> "hard_coded",
                                        "regex"=> "",
                                        "value"=> "VOIP_BIZ_NAT_INA"
                                    ],
                                    "on_false"=> [
                                        "key"=> "condition_result",
                                        "regex"=> "",
                                        "value"=> ""
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                "voip"=> [
                    [
                        [
                            "type"=> "match",
                            "rate_key"=> "params.call_to_same_account",
                            "line_key"=> "computed",
                            "computed"=> [
                                "line_keys"=> [
                                    [
                                        "key"=> "Dest_Number",
                                        "regex"=> "\/^0+\/"
                                    ],
                                    [
                                        "key"=> "foreign.account_subscribers"
                                    ]
                                ],
                                "operator"=> "$in",
                                "type"=> "condition",
                                "must_met"=> true,
                                "projection"=> [
                                    "on_true"=> [
                                        "key"=> "condition_result",
                                        "regex"=> "",
                                        "value"=> ""
                                    ],
                                    "on_false"=> [
                                        "key"=> "condition_result",
                                        "regex"=> "",
                                        "value"=> ""
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        [
                            "type"=> "match",
                            "rate_key"=> "key",
                            "line_key"=> "computed",
                            "computed"=> [
                                "line_keys"=> [
                                    [
                                        "key"=> "Acc_Tenant"
                                    ],
                                    [
                                        "key"=> "\/^(?!System$)\/"
                                    ]
                                ],
                                "operator"=> "$regex",
                                "type"=> "condition",
                                "must_met"=> true,
                                "projection"=> [
                                    "on_true"=> [
                                        "key"=> "hard_coded",
                                        "regex"=> "",
                                        "value"=> "VOIP_BIZ_NAT_FIX"
                                    ],
                                    "on_false"=> [
                                        "key"=> "condition_result",
                                        "regex"=> "",
                                        "value"=> ""
                                    ]
                                ]
                            ]
                        ],
                        [
                            "type"=> "match",
                            "rate_key"=> "key",
                            "line_key"=> "computed",
                            "computed"=> [
                                "line_keys"=> [
                                    [
                                        "key"=> "Dest_Tenant"
                                    ],
                                    [
                                        "key"=> "\/MLS\/"
                                    ]
                                ],
                                "operator"=> "$regex",
                                "type"=> "condition",
                                "must_met"=> true,
                                "projection"=> [
                                    "on_true"=> [
                                        "key"=> "hard_coded",
                                        "regex"=> "",
                                        "value"=> "VOIP_BIZ_NAT_FIX"
                                    ],
                                    "on_false"=> []
                                ]
                            ]
                        ]
                    ],
                    [
                        [
                            "type"=> "longestPrefix",
                            "rate_key"=> "params.voip_prefix",
                            "line_key"=> "Dest_Number"
                        ]
                    ]
                ]
            ]
        ],
        "pricing"=> [
            "voip_incoming"=> [],
            "ina_vas_call"=> [
                "aprice_field"=> "Charge_Account",
                "tax_included"=> true
            ],
            "voip"=> []
        ],
        "receiver"=> [
            "type"=> "ftp",
            "connections"=> [
                [
                    "passive"=> false,
                    "host"=> "127.0.0.1",
                    "receiver_type"=> "ssh",
                    "name"=> "Some server",
                    "user"=> "netplus-user",
                    "filename_regex"=> "\/(?<!\\.sleep)\\d[13]$\/",
                    "remote_directory"=> "\/tmp\/dir",
                    "delete_received"=> true,
                    "password"=> ""
                ]
            ],
            "limit"=> 3
        ],
        "unify"=> [],
        "filters"=> [
            [
                "conditions"=> [
                    [
                        "field_name"=> "uf.Tariff",
                        "op"=> "$regex",
                        "value"=> "^INA_([1][0-9][5,]|[2-9][0-9][4,]|[0-9][1,4])$"
                    ]
                ],
                "skip_calc"=> [
                    "customer",
                    "rate",
                    "pricing",
                    "tax"
                ]
            ],
            [
                "conditions"=> [
                    [
                        "field_name"=> "uf.Dest_Tenant",
                        "op"=> "$eq",
                        "value"=> "MLS"
                    ],
                    [
                        "field_name"=> "uf.Acc_Tenant",
                        "op"=> "$eq",
                        "value"=> "System"
                    ]
                ],
                "skip_calc"=> [
                    "customer",
                    "rate",
                    "pricing",
                    "tax"
                ]
            ],
            [
                "conditions"=> [
                    [
                        "field_name"=> "usaget",
                        "op"=> "$eq",
                        "value"=> "voip_incoming"
                    ]
                ],
                "skip_calc"=> [
                    "pricing",
                    "tax"
                ]
            ],
            [
                "conditions"=> [
                    [
                        "field_name"=> "uf.Dest_Name",
                        "op"=> "$regex",
                        "value"=> "^vPBXinternal$"
                    ]
                ],
                "skip_calc"=> [
                    "customer",
                    "rate",
                    "pricing",
                    "tax"
                ]
            ],
            [
                "conditions"=> [
                    [
                        "field_name"=> "uf.Scope",
                        "op"=> "$in",
                        "value"=> [
                            "IO",
                            "LC",
                            "PLC",
                            "POP",
                            "LG",
                            "PLG",
                            "PLS",
                            "PLO"
                        ]
                    ]
                ],
                "skip_calc"=> [
                    "customer",
                    "rate",
                    "pricing",
                    "tax"
                ]
            ],
            [
                "condition"=> [
                    [
                        "field_name"=> "uf.Scope",
                        "op"=> "$eq",
                        "value"=> "IN"
                    ],
                    [
                        "field_name"=> "uf.Dest_Acc_Name",
                        "op"=> "$eq",
                        "value"=> ""
                    ]
                ],
                "skip_calc"=> [
                    "customer",
                    "rate",
                    "pricing",
                    "tax"
                ]
            ],
            [
                "conditions"=> [
                    [
                        "field_name"=> "uf.Dest_Name",
                        "op"=> "$eq",
                        "value"=> "Pro Juventute"
                    ]
                ],
                "skip_calc"=> [
                    "customer",
                    "rate",
                    "pricing",
                    "tax"
                ]
            ],
            [
                "conditions"=> [
                    [
                        "field_name"=> "usaget",
                        "op"=> "$eq",
                        "value"=> "voip"
                    ],
                    [
                        "field_name"=> "uf.Acc_Name",
                        "op"=> "$regex",
                        "value"=> "^GW =>"
                    ]
                ],
                "skip_calc"=> [
                    "customer",
                    "rate",
                    "pricing",
                    "tax"
                ]
            ]
        ],
        "enabled"=> true
    ];
    protected function process($options)
    {
        $processor = Billrun_Processor::getInstance($options);
        // if (!$processor->createLogForProcessWithPath($options)) {
        //     return;
        // }
        // $linesProcessedCount = $processor->process_files(Billrun_Util::getBillRunPath($options['path']));


        $processor = Billrun_Processor::getInstance($options);
        $linesProcessedCount = $processor->processorByPath($options);
    }
    //$processor = Billrun_Processor::getInstance($options);


    public function testUnifyShouldUnifyAllCdrs(ApiTester $I): void
    {
        $this->createData($I, ['firstname' => 'aaa'], ['from' => '2025-01-01'], [
            'from' => '2025-01-01',
            "include" => [
                "groups" => [
                    "LOCAL_CALLS_5000" => [
                        "account_shared" => false,
                        "account_pool" => false,
                        "rates" => [
                            "CALL"
                        ],
                        "value" => 300000,
                        "usage_types" => [
                            "call" => [
                                "unit" => "minutes"
                            ]
                        ]
                    ]
                ]
            ]
        ], [
            'key' => 'CALL',
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
            ],
        ]);
        $I->generateSubscriber(
            [
                'from' => '2025-01-01',
                'firstname' => '0531234567',
                'aid' => $this->accountDetails['aid'],
                'plan' => $this->planDetails['name'],
                'services' => [['from' => '2025-02-01', 'name' => $this->serviceDetails[0]['name']]]
            ]
        );
        $this->subscriberDetails = json_decode($I->grabResponse(), true)['entity'];
        Billrun_Factory::config()->setConfigValue('queue.calculators', ["customer", "rate", "pricing", "tax", "unify"]);
        $this->process(
            [
                'type' => 'abc',
                'path' => 'tests/all/calculators/test_files/test1.csv'
            ]
        );

        $I->assertEquals(1, $I->grabCollectionCount('lines', [
            'aid' => $this->accountDetails['aid'],
            'sid' => $this->subscriberDetails['sid']
        ]));

        $I->verifyCollectionRecord('lines', [
            'aid' => $this->accountDetails['aid'],
            'sid' => $this->subscriberDetails['sid'],
            'usaget' => 'call',
            'usagev' => 112,
            'aprice' => 0,
            'arategroups.0.left' => 299888
        ]);

        $I->assertEquals(3, $I->grabCollectionCount('archive', [
            'aid' => $this->accountDetails['aid'],
            'sid' => $this->subscriberDetails['sid']
        ]));
    }
    public function testUnifyShouldUnifyPerSubscriber(ApiTester $I): void
    {

        $this->createData($I, ['firstname' => 'aaa'], ['from' => '2025-01-01'], [
            'from' => '2025-01-01',
            "include" => [
                "groups" => [
                    "2LOCAL_CALLS_5000" => [
                        "account_shared" => false,
                        "account_pool" => false,
                        "rates" => [
                            "CALL2"
                        ],
                        "value" => 300000,
                        "usage_types" => [
                            "call" => [
                                "unit" => "minutes"
                            ]
                        ]
                    ]
                ]
            ]
        ], [
            'key' => 'CALL2',
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
            ],
        ]);
        $I->generateSubscriber(
            [
                'from' => '2025-01-01',
                'firstname' => '0531234561',
                'aid' => $this->accountDetails['aid'],
                'plan' => $this->planDetails['name'],
                'services' => [['from' => '2025-02-01', 'name' => $this->serviceDetails[1]['name']]]
            ]
        );
        $this->subscriberDetails = json_decode($I->grabResponse(), true)['entity'];
        $I->generateSubscriber(
            [
                'from' => '2025-01-01',
                'firstname' => '0531234562',
                'aid' => $this->accountDetails['aid'],
                'plan' => $this->planDetails['name'],
                'services' => [['from' => '2025-02-01', 'name' => $this->serviceDetails[1]['name']]]
            ]
        );
        $subscriber2 = json_decode($I->grabResponse(), true)['entity'];
        Billrun_Factory::config()->setConfigValue('queue.calculators', ["customer", "rate", "pricing", "tax", "unify"]);
        //die();
        $this->process(
            [
                'type' => 'abc',
                'path' => 'tests/all/calculators/test_files/test2.csv'
            ]
        );

        $I->assertEquals(1, $I->grabCollectionCount('lines', [
            'aid' => $this->accountDetails['aid'],
            'sid' => $this->subscriberDetails['sid']
        ]));

        $I->assertEquals(1, $I->grabCollectionCount('lines', [
            'aid' => $this->accountDetails['aid'],
            'sid' => $subscriber2['sid']
        ]));

        $I->verifyCollectionRecord('lines', [
            'aid' => $this->accountDetails['aid'],
            'sid' => $this->subscriberDetails['sid'],
            'usaget' => 'call',
            'usagev' => 3,
            'aprice' => 0,
            'arategroups.0.left' => 299997
        ]);


        $I->verifyCollectionRecord('lines', [
            'aid' => $this->accountDetails['aid'],
            'sid' => $subscriber2['sid'],
            'usaget' => 'call',
            'usagev' => 7,
            'aprice' => 0,
            'arategroups.0.left' => 299993
        ]);

        $I->assertEquals(2, $I->grabCollectionCount('archive', [
            'aid' => $this->accountDetails['aid'],
            'sid' => $this->subscriberDetails['sid']
        ]));


        $I->assertEquals(2, $I->grabCollectionCount('archive', [
            'aid' => $this->accountDetails['aid'],
            'sid' => $subscriber2['sid']
        ]));
    }



}

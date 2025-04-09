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
        if ($serviceDetails != []) {
            $I->generateService(array_merge(
                ['name' => 'TEST_SERVICE' . microtime(true) * 10000],
                $serviceDetails
            ));
            $this->serviceDetails = json_decode($I->grabResponse(), true)['entity'];
        }
        if ($rateDetails != []) {
            $I->generateRate(array_merge(['tariff_category' => 'retail', 'key' => microtime(true) * 10000], $rateDetails));
            $this->rateDetails = json_decode($I->grabResponse(), true)['entity'];
        }
    }
    public $inputProcessor = [
        "file_type" => "abc",
        "parser" =>
            [
                "type" =>
                    "separator",
                "line_types" =>
                    [
                        "H" => "/^none$/",
                        "D" => "//",
                        "T" => "/^none$/"
                    ],
                "separator" => ",",
                "structure" => [["name" => "firstname", "checked" => true], ["name" => "date", "checked" => true], ["name" => "rate", "checked" => true], ["name" => "volume", "checked" => true]],
                "csv_has_header" => true,
                "csv_has_footer" => false
            ],
        "processor" => ["type" => "Usage", "date_field" => "date", "default_usaget" => "call", "default_unit" => "seconds", "default_volume_src" => ["volume"]],
        "customer_identification_fields" => ["call" => [["target_key" => "firstname", "src_key" => "firstname", "conditions" => [["field" => "usaget", "regex" => "/.*/"]], "clear_regex" => "//"]]],
        "rate_calculators" => ["retail" => ["call" => [[["type" => "match", "rate_key" => "key", "line_key" => "rate"]]]]],
        "pricing" => ["call" => []],
        "unify" => [
            "unification_fields" => [
                "required" => [
                    "fields" => [
                        "urt",
                        "type",
                        "aid",
                    ],
                    "match" => [],
                ],
                "date_seperation" => "Ymd",
                "stamp" => [
                    "value" => [
                        "usaget",
                        "aid",
                        "sid",
                        "plan",
                        "arate_key",
                        "services",
                        "services_data",
                        "billrun",
                        "tax_data.taxes.0.key",
                        "tax_data.taxes.0.description",
                        "tax_data.taxes.0.tax",
                        "tax_data.taxes.0.type",
                        "tax_data.taxes.0.pass_to_customer",
                    ],
                    "field" => []
                ],
                "fields" => [
                    [
                        "match" => [
                            "type" => "/^abc$/",
                        ],
                        "update" => [
                            [
                                "operation" => '$setOnInsert',
                                "data" => [
                                    "arate",
                                    "arate_key",
                                    "usaget",
                                    "urt",
                                    "plan",
                                    "connection_type",
                                    "aid",
                                    "sid",
                                    "subscriber",
                                    "services",
                                    "services_data",
                                    "foreign",
                                    "arategroups",
                                    "billrun",
                                    "tax_data",
                                    "usagev",
                                    "aprice",
                                    "final_charge"
                                ]
                            ],
                            [
                                "operation" => '$set',
                                "data" => [
                                    "process_time",
                                    "arategroups.0.left",
                                    "arategroups.0.usagesb"
                                ]
                            ],
                            [
                                "operation" => '$inc',
                                "data" => [
                                    "usagev",
                                    "aprice",
                                    "final_charge",
                                    "tax_data.total_amount",
                                    "tax_data.taxes.0.amount",
                                    "arategroups.0.usagev",
                                ]
                            ],
                        ]
                    ]
                ],
            ]
        ],
        "enabled" => true,
        "filters" => [],
        "receiver" => ["type" => "ftp", "connections" => [["receiver_type" => "ftp", "passive" => false, "delete_received" => false, "user" => "admin", "password" => "12345678", "host" => "127.0.0.1", "name" => "a", "remote_directory" => "/home"]]]
    ];
    protected function process($options)
    {
        $processor = Billrun_Processor::getInstance($options);
        if (!$processor->createLogForProcessWithPath($options)) {
            return;
        }
        $linesProcessedCount = $processor->process_files(Billrun_Util::getBillRunPath($options['path']));
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
                'services' => [['from' => '2025-02-01', 'name' => $this->serviceDetails['name']]]
            ]
        );
        $this->subscriberDetails = json_decode($I->grabResponse(), true)['entity'];
        Billrun_Factory::config()->setConfigValue('queue.calculators', ["customer","rate","pricing","tax","unify"]);
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
                'services' => [['from' => '2025-02-01', 'name' => $this->serviceDetails['name']]]
            ]
        );
        $this->subscriberDetails = json_decode($I->grabResponse(), true)['entity'];
        $I->generateSubscriber(
            [
                'from' => '2025-01-01',
                'firstname' => '0531234562',
                'aid' => $this->accountDetails['aid'],
                'plan' => $this->planDetails['name'],
                'services' => [['from' => '2025-02-01', 'name' => $this->serviceDetails['name']]]
            ]
        );
        $subscriber2 = json_decode($I->grabResponse(), true)['entity'];
        Billrun_Factory::config()->setConfigValue('queue.calculators', ["customer","rate","pricing","tax","unify"]);
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

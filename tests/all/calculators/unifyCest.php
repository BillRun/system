<?php

use function PHPUnit\Framework\assertCount;

class unifyCest
{
    public static $isIPSet = false;
    public function _before(ApiTester $I)
    {
        if (!self::$isIPSet) {
            $this->setUP($I);
            self::$isIPSet = true;
            Billrun_Config::getInstance()->loadDbConfig();
            // $this->createServices($I);
        }
        $I->cleanDB();
        $I->resetBillrunInstances();

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
        Billrun_Factory::config()->setConfigValue('queue.calculators', ["customer", "rate", "pricing", "tax", "unify"]);
    }

    /**
     * Apply a custom file_type config from within a test.
     *
     * Billrun_Helpers_QueueCalculators fetches the Unify calculator via
     * Billrun_Calculator::getInstance(['type' => 'unify', 'autoload' => false]),
     * which Billrun_Base caches in a process-wide singleton keyed only by
     * those args. The calc snapshots file_types at construction, so adding a
     * file_type after the first process() call leaves the cached calc stale —
     * loadDbConfig alone is not enough. Clearing Billrun_Base::$instance
     * forces the next process() to rebuild the calc with the fresh config.
     */
    protected function applyCustomFileType(ApiTester $I, array $customProcessor)
    {
        $I->setSettings('file_types', $customProcessor);
        Billrun_Config::getInstance()->loadDbConfig();
        $instances = new ReflectionProperty('Billrun_Base', 'instance');
        $instances->setAccessible(true);
        $instances->setValue(null, []);
    }

    //workaround for the issue with service instence (not update the service list in the 2nd process on the same run)
    // protected function createServices(ApiTester $I)
    // {
    //     //create all the services for all tests once , before the tests
    //     $services = [
    //         [
    //             'from' => '2025-01-01',
    //             "include" => [
    //                 "groups" => [
    //                     "LOCAL_CALLS_5000" => [
    //                         "account_shared" => false,
    //                         "account_pool" => false,
    //                         "rates" => [
    //                             "CALL"
    //                         ],
    //                         "value" => 300000,
    //                         "usage_types" => [
    //                             "call" => [
    //                                 "unit" => "minutes"
    //                             ]
    //                         ]
    //                     ]
    //                 ]
    //             ]
    //         ],
    //         [
    //             'from' => '2025-01-01',
    //             "include" => [
    //                 "groups" => [
    //                     "2LOCAL_CALLS_5000" => [
    //                         "account_shared" => false,
    //                         "account_pool" => false,
    //                         "rates" => [
    //                             "CALL2"
    //                         ],
    //                         "value" => 300000,
    //                         "usage_types" => [
    //                             "call" => [
    //                                 "unit" => "minutes"
    //                             ]
    //                         ]
    //                     ]
    //                 ]
    //             ]
    //         ]
    //     ];
    //     foreach ($services as $service) {
    //         $I->generateService(array_merge(
    //             ['name' => 'TEST_SERVICE' . microtime(true) * 10000],
    //             $service
    //         ));
    //         $this->serviceDetails[] = json_decode($I->grabResponse(), true)['entity'];
    //     }
    // }
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
            $this->serviceDetails = array_merge(
                ['name' => 'TEST_SERVICE' . microtime(true) * 10000],
                $serviceDetails
            );
            $I->generateService($this->serviceDetails);
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
                            "type" => "/^abc/",
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
        // $processor = Billrun_Processor::getInstance($options);
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
                'services' => [['from' => '2025-02-01', 'name' => $this->serviceDetails['name']]]
            ]
        );
        $this->subscriberDetails = json_decode($I->grabResponse(), true)['entity'];
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

    public function testUnifyWithRequiredMatch(ApiTester $I): void
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

        $customProcessor = $this->inputProcessor;
        $customProcessor['file_type'] = 'abc3';
        $customProcessor['unify']['unification_fields']['required']['match'] = ["sid" => "/\\d+/"];
        $this->applyCustomFileType($I, $customProcessor);

        $this->process(
            [
                'type' => 'abc3',
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

    public function testUnifyWithRequiredMatchNestedField(ApiTester $I): void
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

        $customProcessor = $this->inputProcessor;
        $customProcessor['file_type'] = 'abc2';
        $customProcessor['unify']['unification_fields']['required']['match'] = ["uf.firstname" => "/^053\\d+/"];
        $this->applyCustomFileType($I, $customProcessor);

        $this->process(
            [
                'type' => 'abc2',
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

    public function testUnifyWithRequiredNotMatchNestedField(ApiTester $I): void
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
        $subscriberDetails1 = json_decode($I->grabResponse(), true)['entity'];

        $I->generateSubscriber(
            [
                'from' => '2025-01-01',
                'firstname' => '0541234567',
                'aid' => $this->accountDetails['aid'],
                'plan' => $this->planDetails['name'],
                'services' => [['from' => '2025-02-01', 'name' => $this->serviceDetails['name']]]
            ]
        );
        $subscriberDetails2 = json_decode($I->grabResponse(), true)['entity'];

        $customProcessor = $this->inputProcessor;
        $customProcessor['file_type'] = 'abc1';
        $customProcessor['unify']['unification_fields']['required']['match'] = ["uf.firstname" => "/^054\\d+/"];
        $customProcessor['unify']['unification_fields']['fields'][0]['match'] = ["uf.firstname" => "/^054\\d+/"];
        $this->applyCustomFileType($I, $customProcessor);

        $this->process(
            [
                'type' => 'abc1',
                'path' => 'tests/all/calculators/test_files/test3.csv'
            ]
        );

        $I->assertEquals(3, $I->grabCollectionCount('lines', [
            'aid' => $this->accountDetails['aid'],
            'sid' => $subscriberDetails1['sid']
        ]));
         $I->assertEquals(1, $I->grabCollectionCount('lines', [
            'aid' => $this->accountDetails['aid'],
            'sid' => $subscriberDetails2['sid'],
            "lcount" => 3
        ]));

        $I->assertEquals(0, $I->grabCollectionCount('archive', [
            'aid' => $this->accountDetails['aid'],
            'sid' => $subscriberDetails1['sid']
        ]));
        $I->assertEquals(3, $I->grabCollectionCount('archive', [
            'aid' => $this->accountDetails['aid'],
            'sid' => $subscriberDetails2['sid']
        ]));
    }

    /**
     * Normalize a mongo array value (BSONArray or plain array) to a sorted,
     * re-indexed PHP array so sets can be compared exactly but order-insensitively.
     */
    protected function toSortedArray($value): array
    {
        $arr = is_array($value) ? $value : iterator_to_array($value);
        $arr = array_values($arr);
        sort($arr);
        return $arr;
    }

    // BRCD-5486: '$addToSet' operation in unify.unification_fields.fields[].update
    public function testUnifyWithAddToSetOperation(ApiTester $I): void
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

        $customProcessor = $this->inputProcessor;
        $customProcessor['file_type'] = 'abc_addtoset';
        // two extra CSV columns; parsed checked columns land under uf.<name> on the line
        $customProcessor['parser']['structure'][] = ["name" => "cell", "checked" => true];
        $customProcessor['parser']['structure'][] = ["name" => "net", "checked" => true];
        // collect them as sets on the unified line
        $customProcessor['unify']['unification_fields']['fields'][0]['update'][] = [
            "operation" => '$addToSet',
            "data" => [
                "uf.cell",
                "uf.net"
            ]
        ];
        $this->applyCustomFileType($I, $customProcessor);

        // 4 same-day rows for one subscriber: cell = CELL_A, CELL_B, CELL_B (dup), '' (empty)
        $this->process(
            [
                'type' => 'abc_addtoset',
                'path' => 'tests/all/calculators/test_files/addtoset1.csv'
            ]
        );

        // the regular aggregation is intact: 1 unified line, summed usagev, all sources archived
        $I->assertEquals(1, $I->grabCollectionCount('lines', [
            'aid' => $this->accountDetails['aid'],
            'sid' => $this->subscriberDetails['sid']
        ]));

        $I->verifyCollectionRecord('lines', [
            'aid' => $this->accountDetails['aid'],
            'sid' => $this->subscriberDetails['sid'],
            'usaget' => 'call',
            'usagev' => 117,
            'aprice' => 0,
            'arategroups.0.left' => 299883
        ]);

        $I->assertEquals(4, $I->grabCollectionCount('archive', [
            'aid' => $this->accountDetails['aid'],
            'sid' => $this->subscriberDetails['sid']
        ]));

        $unifiedLine = $I->grabFromCollection('lines', [
            'aid' => $this->accountDetails['aid'],
            'sid' => $this->subscriberDetails['sid'],
            'source' => 'unify'
        ]);
        // uf.cell is a set: CELL_B appears once despite 2 source rows; the empty
        // CSV cell parses to '' (not null), so it is collected as well
        $I->assertFalse(is_scalar($unifiedLine['uf']['cell']), 'uf.cell on the unified line should be an array');
        $I->assertSame(['', 'CELL_A', 'CELL_B'], $this->toSortedArray($unifiedLine['uf']['cell']));
        // constant value across all rows collapses to a single-element set
        $I->assertSame(['NET1'], $this->toSortedArray($unifiedLine['uf']['net']));

        // archived source rows keep their original scalar values (unify must not mutate them)
        $archivedLine = $I->grabFromCollection('archive', [
            'sid' => $this->subscriberDetails['sid'],
            'uf.cell' => 'CELL_A'
        ]);
        $I->assertIsString($archivedLine['uf']['cell']);
        $I->assertSame('CELL_A', $archivedLine['uf']['cell']);

        // second file for the same subscriber/day (same unified line): one already-seen
        // value (CELL_B) and one new value (CELL_C) - exercises the mongo-side
        // '$addToSet' with '$each' against the persisted unified line
        $this->process(
            [
                'type' => 'abc_addtoset',
                'path' => 'tests/all/calculators/test_files/addtoset2.csv'
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
            'usagev' => 122,
            'arategroups.0.left' => 299878
        ]);

        $I->assertEquals(6, $I->grabCollectionCount('archive', [
            'aid' => $this->accountDetails['aid'],
            'sid' => $this->subscriberDetails['sid']
        ]));

        $unifiedLine = $I->grabFromCollection('lines', [
            'aid' => $this->accountDetails['aid'],
            'sid' => $this->subscriberDetails['sid'],
            'source' => 'unify'
        ]);
        // the set gained only the new value
        $I->assertSame(['', 'CELL_A', 'CELL_B', 'CELL_C'], $this->toSortedArray($unifiedLine['uf']['cell']));
        $I->assertSame(['NET1'], $this->toSortedArray($unifiedLine['uf']['net']));
    }
}

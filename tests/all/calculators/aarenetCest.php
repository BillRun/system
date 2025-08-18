<?php

use function PHPUnit\Framework\assertCount;

class aarenetCest
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
            // $this->createServices($I);
        }
    }
    protected function setUP(ApiTester $I, $inputProcessor = null)
    {

        $feilds = $I->getSettings('lines', [])['details']['fields'];
        $feilds[]=[
	"field_name"=> "foreign.account_subscribers",
    "title"=> "NDCSN of all customer's subscribers",
	"foreign"=> [
		"entity"=> "account_subscribers",
		"field"=> "sid"
	],
	"available_from"=> "rate",
	"conditions"=> [
		[
			"field_name"=> "type",
			"op"=> '$eq',
			"value"=> "account_subscribers"
		],
		[
			"field_name"=> "uf.Dest_Number",
			"op"=> '$regex',
			"value"=> "^0*((41)?7[56789])"
		]
	]
];



 $I->setSettings('lines.fields', $feilds);



        $feilds = $I->getSettings('rates', [])['details']['fields'];
        $feilds[]=[
        
            "select_list"=> false,
            "display"=> true,
            "searchable"=> false,
            "editable"=> true,
            "multiple"=> false,
            "field_name"=> "params.call_to_same_account",
            "unique"=> false,
            "default_value"=> false,
            "title"=> "Call to same account",
            "mandatory"=> false,
            "type"=> "boolean",
            "select_options"=> ""
        ];
         $I->setSettings('rates.fields', $feilds);

        
        // $inputProcessor = $inputProcessor ?: $this->inputProcessor;
        // $I->setSettings('file_types', $inputProcessor);
        // $type = [

        //     [
        //         "usage_type" => "call",
        //         "label" => "call",
        //         "property_type" => "time",
        //         "invoice_uom" => "seconds",
        //         "input_uom" => "seconds"
        //     ]
        // ];
        // $I->setSettings('usage_types', $type);
    }
    public function test(ApiTester $a){
        $a->assertEquels(1,1);
    }

    // //workaround for the issue with service instence (not update the service list in the 2nd process on the same run)
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
    // protected function createData(ApiTester $I, $accountDetails = [], $planDetails = [], $serviceDetails = [], $rateDetails = [])
    // {
    //     if ($accountDetails != []) {
    //         $I->createAccountWithAllMandatoryCustomFields(array_merge(['firstname' => 'yossi_test'], $accountDetails));
    //         $this->accountDetails = json_decode($I->grabResponse(), true)['entity'];
    //     }
    //     if ($planDetails != []) {
    //         $I->generatePlan(array_merge(['name' => 'TEST_PLAN_2' . microtime(true) * 10000], $planDetails));
    //         $this->planDetails = json_decode($I->grabResponse(), true)['entity'];
    //     }
    //     // if ($serviceDetails != []) {
    //     //     $I->generateService(array_merge(
    //     //         ['name' => 'TEST_SERVICE' . microtime(true) * 10000],
    //     //         $serviceDetails
    //     //     ));
    //     //     $this->serviceDetails = json_decode($I->grabResponse(), true)['entity'];
    //     // }
    //     if ($rateDetails != []) {
    //         $I->generateRate(array_merge(['tariff_category' => 'retail', 'key' => microtime(true) * 10000], $rateDetails));
    //         $this->rateDetails = json_decode($I->grabResponse(), true)['entity'];
    //     }
    // }
    public $inputProcessor =   [
            "file_type"=> "a",
            "type"=> "realtime",
            "parser"=> [
                "type"=> "json",
                "separator"=> "",
                "structure"=> [
                    [
                        "name"=> "sid",
                        "checked"=> true
                    ],
                    [
                        "name"=> "date",
                        "checked"=> true
                    ],
                    [
                        "name"=> "volume",
                        "checked"=> true
                    ],
                    [
                        "name"=> "rate",
                        "checked"=> true
                    ]
                ],
                "csv_has_header"=> false,
                "csv_has_footer"=> false,
                "custom_keys"=> [
                    "sid",
                    "date",
                    "volume",
                    "rate"
                ],
                "line_types"=> [
                    "H"=> "\/^none$\/",
                    "D"=> "\/\/",
                    "T"=> "\/^none$\/"
                ]
            ],
            "processor"=> [
                "type"=> "Realtime",
                "date_field"=> "date",
                "default_usaget"=> "call",
                "default_unit"=> "seconds",
                "default_volume_src"=> [
                    "volume"
                ],
                "orphan_files_time"=> "6 hours"
            ],
            "customer_identification_fields"=> [
                "call"=> [
                    [
                        "target_key"=> "sid",
                        "src_key"=> "sid",
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
                    "call"=> [
                        [
                            [
                                "type"=> "match",
                                "rate_key"=> "params.call_to_same_account",
                                "line_key"=> "computed",
                                "computed"=> [
                                    "line_keys"=> [
                                        [
                                            "key"=> "sid"
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
                                        "on_false"=> []
                                    ]
                                ]
                            ]
                        ],
                        [
                            [
                                "type"=> "match",
                                "rate_key"=> "key",
                                "line_key"=> "rate"
                            ]
                        ]
                    ]
                ]
            ],
            "pricing"=> [
                "call"=> []
            ],
            "realtime"=> [
                "postpay_charge"=> true
            ],
            "response"=> [
                "encode"=> "json",
                "fields"=> [
                    [
                        "response_field_name"=> "requestNum",
                        "row_field_name"=> "request_num"
                    ],
                    [
                        "response_field_name"=> "requestType",
                        "row_field_name"=> "request_type"
                    ],
                    [
                        "response_field_name"=> "sessionId",
                        "row_field_name"=> "session_id"
                    ],
                    [
                        "response_field_name"=> "returnCode",
                        "row_field_name"=> "granted_return_code"
                    ],
                    [
                        "response_field_name"=> "sid",
                        "row_field_name"=> "sid"
                    ],
                    [
                        "response_field_name"=> "grantedVolume",
                        "row_field_name"=> "usagev"
                    ]
                ]
            ],
            "unify"=> [],
            "enabled"=> true
        ];
    // protected function process($options)
    // {
    //     $processor = Billrun_Processor::getInstance($options);
    //     // if (!$processor->createLogForProcessWithPath($options)) {
    //     //     return;
    //     // }
    //     // $linesProcessedCount = $processor->process_files(Billrun_Util::getBillRunPath($options['path']));


    //     $processor = Billrun_Processor::getInstance($options);
    //     $linesProcessedCount = $processor->processorByPath($options);
    // }
    // //$processor = Billrun_Processor::getInstance($options);


    // public function testUnifyShouldUnifyAllCdrs(ApiTester $I): void
    // {
    //     $this->createData($I, ['firstname' => 'aaa'], ['from' => '2025-01-01'], [
    //         'from' => '2025-01-01',
    //         "include" => [
    //             "groups" => [
    //                 "LOCAL_CALLS_5000" => [
    //                     "account_shared" => false,
    //                     "account_pool" => false,
    //                     "rates" => [
    //                         "CALL"
    //                     ],
    //                     "value" => 300000,
    //                     "usage_types" => [
    //                         "call" => [
    //                             "unit" => "minutes"
    //                         ]
    //                     ]
    //                 ]
    //             ]
    //         ]
    //     ], [
    //         'key' => 'CALL',
    //         "rates" => [
    //             "call" => [
    //                 "BASE" => [
    //                     "rate" => [
    //                         [
    //                             "from" => 0,
    //                             "to" => "UNLIMITED",
    //                             "interval" => 1,
    //                             "price" => 1,
    //                             "uom_display" => [
    //                                 "range" => "seconds",
    //                                 "interval" => "seconds"
    //                             ]
    //                         ]
    //                     ]
    //                 ]
    //             ]
    //         ],
    //     ]);
    //     $I->generateSubscriber(
    //         [
    //             'from' => '2025-01-01',
    //             'firstname' => '0531234567',
    //             'aid' => $this->accountDetails['aid'],
    //             'plan' => $this->planDetails['name'],
    //             'services' => [['from' => '2025-02-01', 'name' => $this->serviceDetails[0]['name']]]
    //         ]
    //     );
    //     $this->subscriberDetails = json_decode($I->grabResponse(), true)['entity'];
    //     Billrun_Factory::config()->setConfigValue('queue.calculators', ["customer", "rate", "pricing", "tax", "unify"]);
    //     $this->process(
    //         [
    //             'type' => 'abc',
    //             'path' => 'tests/all/calculators/test_files/test1.csv'
    //         ]
    //     );

    //     $I->assertEquals(1, $I->grabCollectionCount('lines', [
    //         'aid' => $this->accountDetails['aid'],
    //         'sid' => $this->subscriberDetails['sid']
    //     ]));

    //     $I->verifyCollectionRecord('lines', [
    //         'aid' => $this->accountDetails['aid'],
    //         'sid' => $this->subscriberDetails['sid'],
    //         'usaget' => 'call',
    //         'usagev' => 112,
    //         'aprice' => 0,
    //         'arategroups.0.left' => 299888
    //     ]);

    //     $I->assertEquals(3, $I->grabCollectionCount('archive', [
    //         'aid' => $this->accountDetails['aid'],
    //         'sid' => $this->subscriberDetails['sid']
    //     ]));
    // }
    // public function testUnifyShouldUnifyPerSubscriber(ApiTester $I): void
    // {

    //     $this->createData($I, ['firstname' => 'aaa'], ['from' => '2025-01-01'], [
    //         'from' => '2025-01-01',
    //         "include" => [
    //             "groups" => [
    //                 "2LOCAL_CALLS_5000" => [
    //                     "account_shared" => false,
    //                     "account_pool" => false,
    //                     "rates" => [
    //                         "CALL2"
    //                     ],
    //                     "value" => 300000,
    //                     "usage_types" => [
    //                         "call" => [
    //                             "unit" => "minutes"
    //                         ]
    //                     ]
    //                 ]
    //             ]
    //         ]
    //     ], [
    //         'key' => 'CALL2',
    //         "rates" => [
    //             "call" => [
    //                 "BASE" => [
    //                     "rate" => [
    //                         [
    //                             "from" => 0,
    //                             "to" => "UNLIMITED",
    //                             "interval" => 1,
    //                             "price" => 1,
    //                             "uom_display" => [
    //                                 "range" => "seconds",
    //                                 "interval" => "seconds"
    //                             ]
    //                         ]
    //                     ]
    //                 ]
    //             ]
    //         ],
    //     ]);
    //     $I->generateSubscriber(
    //         [
    //             'from' => '2025-01-01',
    //             'firstname' => '0531234561',
    //             'aid' => $this->accountDetails['aid'],
    //             'plan' => $this->planDetails['name'],
    //             'services' => [['from' => '2025-02-01', 'name' => $this->serviceDetails[1]['name']]]
    //         ]
    //     );
    //     $this->subscriberDetails = json_decode($I->grabResponse(), true)['entity'];
    //     $I->generateSubscriber(
    //         [
    //             'from' => '2025-01-01',
    //             'firstname' => '0531234562',
    //             'aid' => $this->accountDetails['aid'],
    //             'plan' => $this->planDetails['name'],
    //             'services' => [['from' => '2025-02-01', 'name' => $this->serviceDetails[1]['name']]]
    //         ]
    //     );
    //     $subscriber2 = json_decode($I->grabResponse(), true)['entity'];
    //     Billrun_Factory::config()->setConfigValue('queue.calculators', ["customer", "rate", "pricing", "tax", "unify"]);
    //     //die();
    //     $this->process(
    //         [
    //             'type' => 'abc',
    //             'path' => 'tests/all/calculators/test_files/test2.csv'
    //         ]
    //     );

    //     $I->assertEquals(1, $I->grabCollectionCount('lines', [
    //         'aid' => $this->accountDetails['aid'],
    //         'sid' => $this->subscriberDetails['sid']
    //     ]));

    //     $I->assertEquals(1, $I->grabCollectionCount('lines', [
    //         'aid' => $this->accountDetails['aid'],
    //         'sid' => $subscriber2['sid']
    //     ]));

    //     $I->verifyCollectionRecord('lines', [
    //         'aid' => $this->accountDetails['aid'],
    //         'sid' => $this->subscriberDetails['sid'],
    //         'usaget' => 'call',
    //         'usagev' => 3,
    //         'aprice' => 0,
    //         'arategroups.0.left' => 299997
    //     ]);


    //     $I->verifyCollectionRecord('lines', [
    //         'aid' => $this->accountDetails['aid'],
    //         'sid' => $subscriber2['sid'],
    //         'usaget' => 'call',
    //         'usagev' => 7,
    //         'aprice' => 0,
    //         'arategroups.0.left' => 299993
    //     ]);

    //     $I->assertEquals(2, $I->grabCollectionCount('archive', [
    //         'aid' => $this->accountDetails['aid'],
    //         'sid' => $this->subscriberDetails['sid']
    //     ]));


    //     $I->assertEquals(2, $I->grabCollectionCount('archive', [
    //         'aid' => $this->accountDetails['aid'],
    //         'sid' => $subscriber2['sid']
    //     ]));
    // }



}

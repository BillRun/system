<?php

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
            $this->serviceDetails= json_decode($I->grabResponse(), true)['entity'];
        }
        if ($rateDetails != []) {
            $I->generateRate(array_merge(['tariff_category' => 'retail', 'key' => microtime(true) * 10000], $rateDetails));
            $this->rateDetails= json_decode($I->grabResponse(), true)['entity'];
        }
    }
    public $inputProcessor = [
        "file_type"=>"abc",
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
    protected function process($options){
        //  //['type' => 'simple', 'path'=> 'or_data/receiver/simple/cdr2.csv'];
        // $processor = Billrun_Processor::getInstance($options);
        // $processor->process_files(Billrun_Util::getBillRunPath($options['path']));
        // $linesProcessedCount = $processor->process();



      
         $processor = Billrun_Processor::getInstance($options);
    
    if(!$processor->createLogForProcessWithPath($options)){
      return;
    }
    $linesProcessedCount = $processor->process_files(Billrun_Util::getBillRunPath($options['path']));
    }
    //$processor = Billrun_Processor::getInstance($options);


    public function testUnifyShouldUnifyAllCdrs(ApiTester $I): void
    {
        $this->createData($I, ['firstname'=>'aaa'], ['from'=>'2025-01-01'], [
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
        ],['key'=>'CALL',"rates"=> [
                    "call"=> [
                        "BASE"=> [
                            "rate"=> [
                                [
                                    "from"=> 0,
                                    "to"=> "UNLIMITED",
                                    "interval"=> 11,
                                    "price"=> 11,
                                    "uom_display"=> [
                                        "range"=> "seconds",
                                        "interval"=> "seconds"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],]);
        $I->generateSubscriber(
            [
                'from' => '2025-01-01',
                'firstname'=>'0531234567',
                'aid' => $this->accountDetails['aid'],
                'plan' => $this->planDetails['name'],
                'services' => [['from' => '2025-02-01','name'=> $this->serviceDetails['name']]]
            ]
        );
        $this->subscriberDetails = json_decode($I->grabResponse(), true)['entity'];

        $this->process(
            [
                'type' => 'abc',
                'path' => 'tests/all/calculators/test_files/test1.csv'
            ]
        );
       

        // Get specific balance by billapi get
        // $currentTimestamp = date('Y-m-d H:i:s');
        // $I->sendBillapiGet([
        //     'aid' => $this->accountDetails['aid'],
        //     'sid' => $this->subscriberDetails['sid'],
        //     'from' => ['$lte' => $currentTimestamp],
        //     'to' => ['$gt' => $currentTimestamp]
        // ], 'balances');

        // // Check response staus
        // $I->seeResponseIsJson();
        // $I->seeResponseContainsJson([
        //     'status' => 1
        // ]);

        // $expectedFromDate = date('Y-m-01\T00:00:00+0000'); // First day of current month
        // $expectedToDate = date('Y-m-01\T00:00:00+0000', strtotime('+1 month')); // First day of next month

        // // Check that get the correct balance
        // $I->seeResponseContainsJson([
        //     'details' => [
        //         [
        //             'aid' => $this->accountDetails['aid'],
        //             'sid' => $this->subscriberDetails['sid'],
        //             'balance' => [
        //                 'cost' => 11,
        //                 'totals' => [
        //                     'call' => [
        //                         'cost' => 11,
        //                         'count' => 1,
        //                         'usagev' => 1,
        //                         'out_group' => [
        //                             'usagev' => 1
        //                         ]
        //                     ]
        //                 ]
        //             ],
        //             'connection_type' => 'postpaid',
        //             'plan_description' => 'plan',
        //             'from' => $expectedFromDate,
        //             'to' => $expectedToDate
        //         ]
        //     ]
        // ]);
        // // Check the details array length is 1 ,  validate the response contains only one balance object
        // $details = json_decode($I->grabResponse(), true)['details'];
        // $I->assertCount(1, $details);

    }

}

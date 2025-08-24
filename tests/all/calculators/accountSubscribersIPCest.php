<?php

use function PHPUnit\Framework\assertCount;

class accountSubscribersIPCest
{

    public $accountDetails;
    public $planDetails;
    public $serviceDetails;
    public $subscriberDetails;
    public $rateDetails;

    public static $isIPSet = false;


    public function _before(ApiTester $I)
    {
        // Ensure the IP is set only once
        // This prevents multiple calls to setUP which can lead to duplicate settings
        if (!self::$isIPSet) {
            $this->setUP($I);
            self::$isIPSet = true;
            Billrun_Config::getInstance()->loadDbConfig();
        }
    }
    /**
     * Set up the environment for the tests 
     * This method configures the necessary settings and fields
     * required for the tests to run correctly.
     * It adds foreign account subscribers, call to same account parameters,
     * and sets up the input processor.
     * It also creates the necessary rates for the tests.
     * @param ApiTester $I
     * @param array|null $inputProcessor
     */
    protected function setUP(ApiTester $I, $inputProcessor = null)
    {

        //add  foreign account_subscribers 
        $feilds = $I->getSettings('lines', [])['details']['fields'];
        $feilds[] = [
            "field_name" => "foreign.account_subscribers",
            "title" => "NDCSN of all customer's subscribers",
            "foreign" => [
                "entity" => "account_subscribers",
                "field" => "imsi"
            ],
            "available_from" => "rate",
            "conditions" => [
                [
                    "field_name" => "type",
                    "op" => '$eq',
                    "value" => "account_subscribers"
                ]

            ]
        ];
        $I->setSettings('lines.fields', $feilds);

        //add params.call_to_same_account 
        $feilds = $I->getSettings('rates', [])['details']['fields'];
        $feilds[] = [
            "select_list" => false,
            "display" => true,
            "searchable" => false,
            "editable" => true,
            "multiple" => false,
            "field_name" => "params.call_to_same_account",
            "unique" => false,
            "default_value" => false,
            "title" => "Call to same account",
            "mandatory" => false,
            "type" => "boolean",
            "select_options" => ""
        ];
        $I->setSettings('rates.fields', $feilds);

        $inputProcessor = $inputProcessor ?: $this->inputProcessor;
        $I->setSettings('file_types', $inputProcessor);

        $feilds = $I->getSettings('subscribers.subscriber', [])['details']['fields'];
        $feilds[] = [
            "select_list" => false,
            "display" => true,
            "searchable" => false,
            "editable" => true,
            "multiple" => false,
            "field_name" => "imsi",
            "unique" => true,
            "title" => "imsi",
            "mandatory" => false
        ];
        $I->setSettings('subscribers.subscriber.fields', $feilds);
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
        //create all the rates for all tests once , before the tests
        $BaseRateDetails = [
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
            ]
        ];
        $I->generateRate(array_merge(['tariff_category' => 'retail', 'key' => microtime(true) * 10000], $BaseRateDetails));
        $this->rateDetails['CALL'] = $I->getLastEntity();
        //create rate for call to same account
        $call_to_same_accountRate = $BaseRateDetails;
        $call_to_same_accountRate['params'] = [
            'call_to_same_account' => true
        ];
        $call_to_same_accountRate['key'] = 'CALL_TO_SAME_ACCOUNT';
        $call_to_same_accountRate['rates']['call']['BASE']['rate'][0]['price'] = 0;
        $I->generateRate(array_merge(['tariff_category' => 'retail', 'key' => microtime(true) * 10000], $call_to_same_accountRate));
        $this->rateDetails['CALL_TO_SAME_ACCOUNT'] = $I->getLastEntity();
    }


    /**
     * Create data for the tests
     * @param ApiTester $I
     * @param array $accountDetails
     * @param array $planDetails
     * @param array $serviceDetails
     * @param array $rateDetails
     * @param array $subscriberDetails
     */
    protected function createData(ApiTester $I, $accountDetails = [], $planDetails = [], $serviceDetails = [], $rateDetails = [], $subscriberDetails = [])
    {
        if ($accountDetails != []) {
            $I->createAccountWithAllMandatoryCustomFields(array_merge(['firstname' => 'yossi_test'], $accountDetails));
            $this->accountDetails = $I->getLastEntity();
        }
        if ($planDetails != []) {
            $I->generatePlan($planDetails);
            $this->planDetails = $I->getLastEntity();
        }

        if ($rateDetails != []) {
            $I->generateRate(array_merge(['tariff_category' => 'retail', 'key' => microtime(true) * 10000], $rateDetails));
            $this->rateDetails = $I->getLastEntity();
        }
        if ($subscriberDetails != []) {
            foreach ($subscriberDetails as $sub) {
                $I->generateSubscriber(array_merge(
                    [
                        'firstname' => 'yossi_test',
                        'aid' => $this->accountDetails['aid'],
                        'plan' => $this->planDetails['name']
                    ],
                    $sub
                ));
                $this->subscriberDetails[] = $I->getLastEntity();
            }


        }
    }
    public $inputProcessor = [
        "file_type" => "account_subscribers",
        "type" => "realtime",
        "parser" => [
            "type" => "json",
            "separator" => "",
            "structure" => [
                [
                    "name" => "sid",
                    "checked" => true
                ],
                [
                    "name" => "date",
                    "checked" => true
                ],
                [
                    "name" => "volume",
                    "checked" => true
                ],
                [
                    "name" => "rate",
                    "checked" => true
                ],
                [
                    "name" => "imsi",
                    "checked" => true
                ]
            ],
            "csv_has_header" => false,
            "csv_has_footer" => false,
            "custom_keys" => [
                "sid",
                "date",
                "volume",
                "rate"
            ],
            "line_types" => [
                "H" => "/^none$/",
                "D" => "//",
                "T" => "/^none$/"

            ]
        ],
        "processor" => [
            "type" => "Realtime",
            "date_field" => "date",
            "default_usaget" => "call",
            "default_unit" => "seconds",
            "default_volume_src" => [
                "volume"
            ],
            "orphan_files_time" => "6 hours"
        ],
        "customer_identification_fields" => [
            "call" => [
                [
                    "target_key" => "sid",
                    "src_key" => "sid",
                    "conditions" => [
                        [
                            "field" => "usaget",
                            "regex" => "/.*/"
                        ]
                    ],
                    "clear_regex" => "//"
                ]

            ]

        ],

        "rate_calculators" => [
            "retail" => [
                "call" => [
                    [
                        [
                            "type" => "match",
                            "rate_key" => "params.call_to_same_account",
                            "line_key" => "computed",
                            "computed" => [
                                "line_keys" => [
                                    [
                                        "key" => "imsi"
                                    ],
                                    [
                                        "key" => "foreign.account_subscribers"
                                    ]
                                ],
                                "operator" => '$in',
                                "type" => "condition",
                                "must_met" => true,
                                "projection" => [
                                    "on_true" => [
                                        "key" => "condition_result",
                                        "regex" => "",
                                        "value" => ""
                                    ],
                                    "on_false" => []
                                ]
                            ]
                        ]
                    ],
                    [
                        [
                            "type" => "match",
                            "rate_key" => "key",
                            "line_key" => "rate"
                        ]
                    ]
                ]
            ]
        ],
        "pricing" => [
            "call" => []
        ],
        "realtime" => [
            "postpay_charge" => true
        ],
        "response" => [
            "encode" => "json",
            "fields" => [
                [
                    "response_field_name" => "requestNum",
                    "row_field_name" => "request_num"
                ],
                [
                    "response_field_name" => "requestType",
                    "row_field_name" => "request_type"
                ],
                [
                    "response_field_name" => "sessionId",
                    "row_field_name" => "session_id"
                ],
                [
                    "response_field_name" => "returnCode",
                    "row_field_name" => "granted_return_code"
                ],
                [
                    "response_field_name" => "sid",
                    "row_field_name" => "sid"
                ],
                [
                    "response_field_name" => "grantedVolume",
                    "row_field_name" => "usagev"
                ]
            ]
        ],
        "unify" => [],
        "enabled" => true
    ];

    public function testCallToSameAccount(ApiTester $I): void
    {
        $subscribers = [
            [
                'from' => '2025-01-01',
                'imsi' => '0531234567',

            ],
            [
                'from' => '2025-01-01',
                'imsi' => '0531234568',

            ]
        ];
        $this->createData($I, ['email' => 'yossi@gmail'], ['name' => 'TEST_PLAN_2' . microtime(true) * 10000], [], [], $subscribers);

        $I->sendRealTimeRequest('account_subscribers', [
            "sid" => $this->subscriberDetails[0]['sid'],
            "date" => '2025-05-01T00:00:00+02:00',
            "volume" => 112,
            "rate" => 'CALL',
            "imsi" => $this->subscriberDetails[1]['imsi']
        ]);

        $I->verifyCollectionRecord('lines', [
            'aid' => $this->accountDetails['aid'],
            'sid' => $this->subscriberDetails[0]['sid'],
            'usaget' => 'call',
            'usagev' => 112,
            'aprice' => 0,
            'arate_key' => 'CALL_TO_SAME_ACCOUNT',
        ]);
    }


    public function testCallToAnotherAccount(ApiTester $I): void
    {
        $subscribers = [
            [
                'from' => '2025-01-01',
                'imsi' => '053121111',

            ],
            [
                'from' => '2025-01-01',
                'imsi' => '053121112',

            ]
        ];
        $this->subscriberDetails = [];
        $this->createData($I, ['email' => 'yossi@gmail'], ['name' => 'TEST_PLAN_2' . microtime(true) * 10000], [], [], $subscribers);


        $I->sendRealTimeRequest('account_subscribers', [
            "sid" => $this->subscriberDetails[0]['sid'],
            "date" => '2025-05-01T00:00:00+02:00',
            "volume" => 112,
            "rate" => 'CALL',
            "imsi" => '14526654' // a different imsi than the subscriber's imsi
        ]);

        $I->verifyCollectionRecord('lines', [
            'aid' => $this->accountDetails['aid'],
            'sid' => $this->subscriberDetails[0]['sid'],
            'usaget' => 'call',
            'usagev' => 112,
            'arate_key' => 'CALL',// 'CALL_TO_SAME_ACCOUNT' is not applied here
        ]);
    }


}

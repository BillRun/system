<?php

//this is example test 
require_once(APPLICATION_PATH . '/library/Tests/Util/Generators/generators.php');
class Test_Case_42761
{


    public function test_case()
    {

        generat_test_data::setTestNumber(42761);
        $plan = generat_plans::generatePlan(

            [

                "from" => "2019-05-31T22:00:00Z",
                "name" => generat_test_data::uniqueName("PLAN"),
                "price" => [
                    [
                        "price" => 100,
                        "from" => 0,
                        "to" => "UNLIMITED"
                    ]
                ],
                "description" => "mf",
                "recurrence" => [
                    "periodicity" => "month"
                ],
                "upfront" => 0,

                "connection_type" => "postpaid",
                "to" => "2166-10-16T13:23:53Z",
                "creation_time" => "2017-07-01T04:00:00Z",
                "prorated_start" => true,
                "prorated_end" => true,
                "prorated_termination" => true
            ]
        );
        $discount_name = generat_test_data::uniqueName();

        $discount = generat_discounts::generateDiscount([
          "from" => "2019-05-31T22:00:00Z",
          "to" => "2166-10-16T13:23:53Z",
          "params" => [
            "conditions" => [
                    [
                        "subscriber" => [
                            [
                                "fields" => [
                                    [
                                        "field" => "plan",
                                        "op" => "in",
                                        "value" => [$plan['name']]
                                    ]
                                ]
                            ]
                        ]
                    ]
            ],
        ],
            "subject" => [
                "plan" => [
                    $plan['name'] => ["value" => 100]
                ]
            ],
            'key'=> $discount_name
        ]);

        $account = generat_subscribers::generateAccount();
        $subscriber = generat_subscribers::generateSubscriber([
            'aid' => $account['aid'],
            "from" => "2018-07-04T21:00:00Z",
            "plan" => $plan['name'],
            "overrides" => [

                  [
                    "key" => $discount_name,
                    "type" => "discount",
                    "value" => [
                        "params" => [
                        "conditions" => [
                            ["account" => [
                                "fields" => [
                                    [
                                        "field" => "aid",
                                        "op" => "in",
                                        "value" => [
                                            $account['aid'] //always TRUE 
                                        ]
                                    ]
						        ]

                            ]]
                        ]],
                      "subject" => [
                        "plan" => [
                            $plan['name'] => [
                                "value" => 10
                            ],

                        ]
                    ],
                  ] 

                ]
            ]

        ]);
        $subscriber['from'] = "2022-05-14T21:00:00Z";
        
        $subscriber['overrides'] = [

          [
            "key" => $discount_name,
            "type" => "discount",
            "value" => [
                "params" => [
                        "conditions" => [
                            ["account" => [
                                "fields" => [
                                    [
                                        "field" => "aid",
                                        "op" => "nin",
                                        "value" => [
                                            $account['aid'] //always false 
                                        ]
                                    ]
						        ]

                            ]]
                        ]],
              "subject" => [
                "plan" => [
                    $plan['name'] => [
                        "value" => 20
                    ],

                ]
            ],
          ] 

        ]
          ];
        $update1 = update_test_data::bulidAPI(
            'subscribers',
            ['update' => $subscriber, 'query' => ['_id' => $subscriber['_id']['$id']]]
        );


        // Note!!: there is currently no requirement to prorate the discount in this case; only the last discount override is supported for now.
        return [
            'test' => [
                'label' => ' 2 revisions (in month) for subscriber with different override discount conditions the first meet and the second not- should give only the last revision eligibility',
                'test_number' => 42761,
                "aid" => $account['aid'],
                'sid' => $subscriber['sid'],
                'function' => ['basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'],
                'options' => ["stamp" => "202206", "force_accounts" => [$account['aid']]]
            ],
            'expected' => [
                'billrun' => [
                    'billrun_key' => '202206',
                    'aid' => $account['aid'],
                    'after_vat' => [$subscriber['sid'] => 111.716129032],
                    'total' => 117,
                    'vatable' => 100,//flat 100
                    'vat' => 17
                ],
                'line' => ['types' => ['flat']]
            ],

            'postRun' => [
            ],
        ];
    }
}
<?php

//this is example test 
require_once(APPLICATION_PATH . '/library/Tests/Util/Generators/generators.php');
class Test_Case_42760
{


    public function test_case()
    {

        generat_test_data::setTestNumber(42760);
        $plan = generat_plans::generatePlan(

            [

                "from" => "2019-05-31T22:00:00Z",
                "name" => "PLAN" . time()+random_int(1,111111111),
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
        $discount_name = time()+random_int(1,111111111);

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
        $subscriber1 = generat_subscribers::generateSubscriber([
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
       
        return [
            'test' => [
                'label' => 'subscriber with override discount condition that not true for sub- should not get discount',
                'test_number' => 42760,
                "aid" => $account['aid'],
                'function' => ['basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'],
                'options' => ["stamp" => "202206", "force_accounts" => [$account['aid']]]
            ],
            'expected' => [
                'billrun' => [
                    'billrun_key' => '202206',
                    'aid' => $account['aid'],
                    'after_vat' => [$subscriber1['sid'] => 105.3],
                    'total' =>105.3,
                    'vatable' => 90,//subscriber1 -> flat 100 discount 10 
                    'vat' => 17
                ],
                'line' => ['types' => ['flat', 'credit']]
            ],

            'postRun' => [
            ]
        ];
    }
}
<?php

//this is example test 
require_once(APPLICATION_PATH . '/library/Tests/Util/Generators/generators.php');
class Test_Case_42766
{


    public function test_case()
    {
        $account = generat_subscribers::generateAccount();

        generat_test_data::setTestNumber(42766);
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
        $discount_name1 = generat_test_data::uniqueName("DIS1_");

        $discount1 = generat_discounts::generateDiscount([
          "from" => "2019-05-31T22:00:00Z",
          "to" => "2166-10-16T13:23:53Z",
          "params" => [
                        "conditions" => [
                            ["account" => [
                                "fields" => [
                                    [
                                        "field" => "aid",
                                        "op" => "in",
                                        "value" => [
                                            $account['aid'] //always true 
                                        ]
                                    ]
						        ]

                            ]]
                        ]],
            "subject" => [
                "plan" => [
                    $plan['name'] => ["value" => 100]
                ]
            ],
            'key'=> $discount_name1
        ]);
        $discount_name2 = generat_test_data::uniqueName("DIS2_");

        $discount2 = generat_discounts::generateDiscount([
          "from" => "2019-05-31T22:00:00Z",
          "to" => "2166-10-16T13:23:53Z",
          "params" => [
                        "conditions" => [
                            ["account" => [
                                "fields" => [
                                    [
                                        "field" => "aid",
                                        "op" => "in",
                                        "value" => [
                                            $account['aid'] //always true 
                                        ]
                                    ]
						        ]

                            ]]
                        ]],
            "subject" => [
                "plan" => [
                    $plan['name'] => ["value" => 80]
                ]
            ],
            'key'=> $discount_name2
        ]);

        $subscriber = generat_subscribers::generateSubscriber([
            'aid' => $account['aid'],
            "from" => "2018-07-04T21:00:00Z",
            "plan" => $plan['name'],
       'overrides' => [

          [
            "key" => $discount_name1,
            "type" => "discount",
            "value" => [
            
              "subject" => [
                "plan" => [
                    $plan['name'] => [
                        "value" => 10
                    ],

                ]
            ],
          ] 

        ], [
            "key" => $discount_name2,
            "type" => "discount",
            "value" => [
              "subject" => [
                "plan" => [
                    $plan['name'] => [
                        "value" => 20
                    ],

                ]
            ],
          ] 

        ]]
          ]);
        



        return [
            'test' => [
                'label' => 'one subscriber that override two discounts ',
                'test_number' => 42766,
                "aid" => $account['aid'],
                'sid' => $subscriber['sid'],
                'function' => ['basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'],
                'options' => ["stamp" => "202206", "force_accounts" => [$account['aid']]]
            ],
            'expected' => [
                'billrun' => [
                    'billrun_key' => '202206',
                    'aid' => $account['aid'],
                    'after_vat' => [$subscriber['sid'] => 81.9],
                    'total' => 81.9,
                    'vatable' => 70,//flat 100 /override discount1 (10) /overide discount2 (20)
                    'vat' => 17
                ],
                'line' => ['types' => ['flat', 'credit']]
            ],

            'postRun' => [
            ],
        ];
    }
}
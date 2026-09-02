<?php

//this is example test 
require_once(APPLICATION_PATH . '/library/Tests/Util/Generators/generators.php');
class Test_Case_42758
{


    public function test_case()
    {

        generat_test_data::setTestNumber(42758);
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
            ]],
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
        $subscriber2 = generat_subscribers::generateSubscriber([
            'aid' => $account['aid'],
            "from" => "2018-07-04T21:00:00Z",
            "plan" => $plan['name'],
            "overrides" => [

                  [
                    "key" => $discount_name,
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

                ]
            ]

        ]);
        return [
            'test' => [
                'label' => ' 2 subscribers with different override discount price (full month)- should give different discount for each subscriber by the overide price',
                'test_number' => 42758,
                "aid" => $account['aid'],
                'function' => ['basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'],
                'options' => ["stamp" => "202206", "force_accounts" => [$account['aid']]]
            ],
            'expected' => [
                'billrun' => [
                    'billrun_key' => '202206',
                    'aid' => $account['aid'],
                    'after_vat' => [$subscriber1['sid'] => 90, $subscriber2['sid'] => 80],
                    'total' =>198.9,
                    'vatable' => 170,//subscriber1 -> flat 100 /discount 10 + subscriber2-> flat 100 /discount 20
                    'vat' => 17
                ],
                'line' => ['types' => ['flat', 'credit']]
            ],

            'postRun' => [
            ]
        ];
    }
}
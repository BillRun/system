<?php

//this is example test 
require_once(APPLICATION_PATH . '/library/Tests/Util/Generators/generators.php');
class Test_Case_42769
{


    public function test_case()
    {
        $account = generat_subscribers::generateAccount();
        generat_test_data::setTestNumber(42769);
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
                                        "field" => "aid",
                                        "op" => "in",
                                        "value" => [$account['aid']]
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

        $subscriber = generat_subscribers::generateSubscriber([
            'aid' => $account['aid'],
            "from" => "2018-07-04T21:00:00Z",
            "plan" => $plan['name'],
            "discounts" => [

                  [
                    "params" => [
                        "conditions" => [
                                [
                                    "subscriber" => [
                                        [
                                            "fields" => [
                                                [
                                                    "field" => "aid",
                                                    "op" => "nin",
                                                    "value" => [$account['aid']]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                        ]],
                    "key" => $discount_name,
                    "subject" => [
                        "plan" => [
                            $plan['name'] => [
                                "value" => 90
                            ],

                        ]
                    ],
                  ] 

                
            ],

        ]);

        return [
            'test' => [
                'label' => 'add to subscriber discount with existing key that the condition not meet + the general discont meet- should not get this discount + general',
                'test_number' => 42769,
                "aid" => $account['aid'],
                'function' => ['basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'],
                'options' => ["stamp" => "202206", "force_accounts" => [$account['aid']]]
            ],
            'expected' => [
                'billrun' => [
                    'billrun_key' => '202206',
                    'aid' => $account['aid'],
                    'after_vat' => [$subscriber['sid'] => 117],
                    'total' =>117,
                    'vatable' => 100,//subscriber1 -> flat 100 /override discount 80
                    'vat' => 17
                ],
                'line' => ['types' => ['flat']]
            ],

            'postRun' => [
            ]
        ];
    }
}
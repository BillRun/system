<?php

//this is example test 
require_once(APPLICATION_PATH . '/library/Tests/Util/Generators/generators.php');
class Test_Case_3710
{


    public function test_case()
    {

        generat_test_data::setTestNumber(3710);
        $plan = generat_plans::generatePlan(

            [

                "from" => "2019-05-31T22:00:00Z",
                "name" => "PLAN" . time(),
                "price" => [
                    [
                        "price" => 30,
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
        $service = generat_services::generateService([


            'from' => '2017-07-01T04:00:00Z',
            'name' => "SERVICE_DISCOUNT" . time(),
            "price" => [["price" => 0, "from" => 0, "to" => "UNLIMITED"]]
        ]);


        $account = generat_subscribers::generateAccount();
        $subscriber = generat_subscribers::generateSubscriber([
            'aid' => $account['aid'],
            "from" => "2018-07-04T21:00:00Z",
            "plan" => $plan['name'],
            "discounts" => [
                [
                    "key" => "SUBSCRIBER_DISCOUNT_e8a598cd1054bc39cf4596578cb6df84",
                    "priority" => "",
                    "proration" => "inherited",
                    "type" => "monetary",
                    "subject" => [
                        "plan" => [
                            $plan['name'] => [
                                "value" => 10
                            ],

                        ]
                    ],
                    "description" => "fgsdhdfj",
                    "from" => "2015-12-23T00:00:00Z",
                    "to" => "2122-05-05T00:00:00Z"
                ]
            ]

        ]);
        $subscriber['from'] = "2022-05-14T22:00:00Z";
        $subscriber['services'] = [
            [
                "from" => "2022-05-14T22:00:00Z",
                "name" => $service['name'],
                "creation_time" => "2022-05-14T22:00:00Z",
                "to" => "2120-03-20T20:00:00Z",
                "service_id" => "2222222"
            ]
        ];
        $subscriber['discounts'] = [
            [
                "key" => "SUBSCRIBER_DISCOUNT_e8a598cd1054bc39cf4596578cb6df84",
                "priority" => "",
                "proration" => "inherited",
                "type" => "monetary",
                "subject" => [
                    "plan" => [
                        $plan['name'] => [
                            "value" => 10
                        ]
                    ]
                ],
                "description" => "fgsdhdfj",
                "from" => "2015-12-23T00:00:00Z",
                "to" => "2022-05-05T00:00:00Z"
            ]
        ];
        $update1 = update_test_data::bulidAPI(
            'subscribers',
            ['update' => $subscriber, 'query' => ['_id' => $subscriber['_id']['$id']]]
        );



        return [
            'test' => [
                'label' => 'discount on subscriber, end the discount at new revision should give prorated discount (BRCD-3710)',
                'test_number' => 3710,
                "aid" => $account['aid'],
                'sid' => $subscriber['sid'],
                'function' => ['basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'],
                'options' => ["stamp" => "202206", "force_accounts" => [$account['aid']]]
            ],
            'expected' => [
                'billrun' => [
                    'billrun_key' => '202206',
                    'aid' => $account['aid'],
                    'after_vat' => [$subscriber['sid'] =>25.161290323],
                    'total' => 29.438709678,
                    'vatable' => 25.161290323,
                    'vat' => 17
                ],
                'line' => ['types' => ['flat', 'credit']]
            ],

            'postRun' => [
            ],
        ];
    }
}
<?php

//this is example test 
require_once(APPLICATION_PATH . '/library/Tests/Util/Generators/generators.php');
class Test_Case_42764
{


    public function test_case()
    {
        $account = generat_subscribers::generateAccount();

        generat_test_data::setTestNumber(42764);
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
                        "account" => [
                                "fields" => [
                                    [
                                        "field" => "aid",
                                        "op" => "nin",
                                        "value" => [
                                            $account['aid']
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
  

        $subscriber = generat_subscribers::generateSubscriber([
            'aid' => $account['aid'],
            "from" => "2018-07-04T21:00:00Z",
            "plan" => $plan['name'],
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
                    $plan['name'] => [
                        "value" => 10
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



        return [
            'test' => [
                'label' => ' 2 revisions (in month) for subscriber that only the second have override discount (only override discount meet condtions and not the general dis) - should get only override discount partial',
                'test_number' => 42764,
                "aid" => $account['aid'],
                'sid' => $subscriber['sid'],
                'function' => ['basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'],
                'options' => ["stamp" => "202206", "force_accounts" => [$account['aid']]]
            ],
            'expected' => [
                'billrun' => [
                    'billrun_key' => '202206',
                    'aid' => $account['aid'],
                    'after_vat' => [$subscriber['sid'] => 110.583870968],
                    'total' => 110.583870968,
                    'vatable' => 94.516129032,//flat 100 /override discount (17/31*10)
                    'vat' => 17
                ],
                'line' => ['types' => ['flat', 'credit']]
            ],

            'postRun' => [
            ],
        ];
    }
}
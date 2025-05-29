<?php

//this is example test 
require_once(APPLICATION_PATH . '/library/Tests/Util/Generators/generators.php');
class Test_Case_42774
{


    public function test_case()
    {
        $account = generat_subscribers::generateAccount();
        generat_test_data::setTestNumber(42773);
        $plan1 = generat_plans::generatePlan(

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
        $plan2 = generat_plans::generatePlan(

            [

                "from" => "2019-05-31T22:00:00Z",
                "name" => "PLAN" . time()+random_int(1,111111111),
                "price" => [
                    [
                        "price" => 80,
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
            "price" => [["price" => 20, "from" => 0, "to" => "UNLIMITED"]]
        ]);
        $discount_name = time()+random_int(1,111111111);

        $discount2 = generat_discounts::generateDiscount([
            "from" => "2019-05-31T22:00:00Z",
            "to" => "2166-10-16T13:23:53Z",
            "params" => [
              "conditions" => [
              ]],
              "subject" => [
                  "service" => [
                      $service['name'] => ["value" => 20]
                  ]
              ],
              'key'=> $discount_name,
          ]);
          $discount_name = time()+random_int(1,111111111);

        $discount1 = generat_discounts::generateDiscount([
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
                                          "value" => [$plan1['name']]
                                      ]
                                  ]
                              ]
                          ]
                      ]
              ]],
              "subject" => [
                  "plan" => [
                      $plan1['name'] => ["value" => 10]
                  ]
              ],
              'key'=> $discount_name,
              'excludes' => [$discount2['key']]

          ]);

        $subscriber1 = generat_subscribers::generateSubscriber([
            'aid' => $account['aid'],
            "from" => "2018-07-04T21:00:00Z",
            "plan" => $plan1['name'],
            'services' => [
                [
                    "name" => $service['name'],
                    "from" => "2018-07-04T21:00:00Z",
                    "to" => "2118-05-06T11:06:07Z"
                ]
            ]

        ]);
        $subscriber2 = generat_subscribers::generateSubscriber([
            'aid' => $account['aid'],
            "from" => "2018-07-04T21:00:00Z",
            "plan" => $plan2['name'],
            'services' => [
                [
                    "name" => $service['name'],
                    "from" => "2018-07-04T21:00:00Z",
                    "to" => "2118-05-06T11:06:07Z"
                ]
            ]

        ]);
       

        return [
            'test' => [
                'label' => 'Discount exclusion for one subscriber should not affect other subscribers of the same account',
                'test_number' => 42774,
                "aid" => $account['aid'],
                'function' => ['basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'],
                'options' => ["stamp" => "202206", "force_accounts" => [$account['aid']]]
            ],
            'expected' => [
                'billrun' => [
                    'billrun_key' => '202206',
                    'aid' => $account['aid'],
                    'vat' => 17,
                    'total' => 222.3,
                    'vatable' => 190, //subscriber1 ->plan1(100) discount1(10) service(20)/ subscriber2 -> plan2 80 discount2(20) service(20)
                ],
                'line' => ['types' => ['flat', 'credit']]
            ],

            'postRun' => [
            ]
        ];
    }
}
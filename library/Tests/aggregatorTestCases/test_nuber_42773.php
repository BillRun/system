<?php

//this is example test 
require_once(APPLICATION_PATH . '/library/Tests/Util/Generators/generators.php');
class Test_Case_42773
{


    public function test_case()
    {
        $account = generat_subscribers::generateAccount();
        generat_test_data::setTestNumber(42773);
        $plan1 = generat_plans::generatePlan(

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
        $plan2 = generat_plans::generatePlan(

            [

                "from" => "2019-05-31T22:00:00Z",
                "name" => generat_test_data::uniqueName("PLAN"),
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
        $discount_name = generat_test_data::uniqueName();

        $discount3 = generat_discounts::generateDiscount([
            "from" => "2019-05-31T22:00:00Z",
            "to" => "2166-10-16T13:23:53Z",
            "params" => [
              "conditions" => [],
            ],
              "subject" => [
                  "service" => [
                      $service['name'] => ["value" => 20]
                  ]
              ],
              'key'=> $discount_name
          ]);
        $discount_name = generat_test_data::uniqueName();

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
              'excludes' => [$discount3['key']]

          ]);
          $discount_name = generat_test_data::uniqueName();

        $discount2 = generat_discounts::generateDiscount([
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
                                          "value" => [$plan2['name']]
                                      ]
                                  ]
                              ]
                          ]
                      ]
              ]],
              "subject" => [
                  "plan" => [
                      $plan2['name'] => ["value" => 20]
                  ]
              ],
              'key'=> $discount_name,
              'excludes' => [$discount3['key']]
          ]);
          
        $subscriber = generat_subscribers::generateSubscriber([
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
        $subscriber['from'] = "2022-05-14T21:00:00Z";
        $subscriber['plan'] = $plan2['name'];
        $update1 = update_test_data::bulidAPI(
            'subscribers',
            ['update' => $subscriber, 'query' => ['_id' => $subscriber['_id']['$id']]]
        );

        return [
            'test' => [
                'label' => 'subscribers with 2 revisions in month, the first revision eligibility for dis1 
                and second revision eligibility for dis2
                oth dis1 and dis2 exclude dis3 → expected not get dis3 for all the 2 revisions times',
                'test_number' => 42773,
                "aid" => $account['aid'],
                'function' => ['basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'],
                'options' => ["stamp" => "202206", "force_accounts" => [$account['aid']]]
            ],
            'expected' => [
                'billrun' => [
                    'billrun_key' => '202206',
                    'aid' => $account['aid'],
                    'total' => 109.451612903,
                    'vatable' =>  93.548387097,//subscriber -> flat plan1 100(14/31*100) discount1-(14/31*10) / flat plan2 80(17/31*80) discount2(17/31*20) + service (20)
                    'vat' => 17
                ],
                'line' => ['types' => ['flat', 'credit']]
            ],

            'postRun' => [
            ]
        ];
    }
}
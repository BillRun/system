<?php

//this is example test 
require_once(APPLICATION_PATH . '/library/Tests/Util/Generators/generators.php');
class Test_Case_42770
{


    public function test_case()
    {
        $account = generat_subscribers::generateAccount();
        generat_test_data::setTestNumber(42770);
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
                                          "field" => "aid",
                                          "op" => "nin",
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
        $subscriber1 = generat_subscribers::generateSubscriber([
            'aid' => $account['aid'],
            "from" => "2018-07-04T21:00:00Z",
            "plan" => $plan['name'],
            "discounts" => [

                  [
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
                              $plan['name'] => ["value" => 80]
                          ]
                      ],
                      'key'=> $discount_name
                  ] 

                
            ],

        ]);
        $subscriber2 = generat_subscribers::generateSubscriber([
            'aid' => $account['aid'],
            "from" => "2018-07-04T21:00:00Z",
            "plan" => $plan['name'],
            "discounts" => [

                  [
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
                              $plan['name'] => ["value" => 90]
                          ]
                      ],
                      'key'=> $discount_name
                  ] 

                
            ],

        ]);
        $subscriber3 = generat_subscribers::generateSubscriber([
            'aid' => $account['aid'],
            "from" => "2018-07-04T21:00:00Z",
            "plan" => $plan['name'],

        ]);

        return [
            'test' => [
                'label' => 'non-unique discount key- differnt discounts for different subscribers',
                'test_number' => 42770,
                "aid" => $account['aid'],
                'function' => ['basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'],
                'options' => ["stamp" => "202206", "force_accounts" => [$account['aid']]]
            ],
            'expected' => [
                'billrun' => [
                    'billrun_key' => '202206',
                    'aid' => $account['aid'],
                    'total' => 152.1,
                    'vatable' =>  130,//subscriber1 -> flat 100 / discount 80 + subscriber2 -> flat 100 discount 90 +subscriber3-> flat 100
                    'vat' => 17
                ],
                'line' => ['types' => ['flat', 'credit']]
            ],

            'postRun' => [
            ]
        ];
    }
}
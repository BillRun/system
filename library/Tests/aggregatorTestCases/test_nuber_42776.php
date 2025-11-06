<?php

//this is example test 
require_once(APPLICATION_PATH . '/library/Tests/Util/Generators/generators.php');
class Test_Case_42776
{


    public function test_case()
    {
        $account = generat_subscribers::generateAccount();
        generat_test_data::setTestNumber(42776);
        $plan1 = generat_plans::generatePlan(

            [

                "from" => "2025-09-03T22:00:00Z",
                "name" => "B2C_42776" . time(),
                "price" => [
                    [
                        "price" => 16.79,
                        "from" => 0,
                        "to" => "UNLIMITED"
                    ]
                ],
                "description" => "B2C",
                "recurrence" => [
                    "periodicity" => "month"
                ],
                "upfront" => 0,

                "connection_type" => "postpaid",
                "to" => "2025-09-05T13:23:53Z",
                "creation_time" => "2017-07-01T04:00:00Z",
                "prorated_start" => true,
                "prorated_end" => true,
                "prorated_termination" => FALSE
            ]
        );
        
        $discount_name = "DIS_B2C_" . time();

        $discount = generat_discounts::generateDiscount([
            "from" => "2025-08-01T21:00:00Z",
            "to" => "2125-09-04T21:00:00Z",
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
                      $plan1['name'] => ["value" => 4.2]
                  ]
              ],
              'key'=> $discount_name,

          ]);

        $subscriber1 = generat_subscribers::generateSubscriber([
            'aid' => $account['aid'],
            "from" => "2018-07-04T21:00:00Z",
            "plan" => $plan1['name'],

        ]);
       

        return [
            'test' => [
                'label' => 'BRCD-5000: Discount for not prorate charge on termination plan',
                'test_number' => 42776,
                "aid" => $account['aid'],
                'function' => ['basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'],
                'options' => ["stamp" => "202510", "force_accounts" => [$account['aid']]]
            ],
            'expected' => [
                'billrun' => [
                    'billrun_key' => '202510',
                    'aid' => $account['aid'],
                    'vat' => 17,
                    'total' => 14.7303,
                    'vatable' => 12.59, //subscriber1 ->plan1(16.79) discount1(4.2)
                ],
                'line' => ['types' => ['flat', 'credit']]
            ],

            'postRun' => [
            ]
        ];
    }
}
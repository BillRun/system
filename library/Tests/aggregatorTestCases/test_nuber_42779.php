<?php

//this is example test 
require_once(APPLICATION_PATH . '/library/Tests/Util/Generators/generators.php');
class Test_Case_42779
{


    public function test_case()
    {
        $account = generat_subscribers::generateAccount();
        generat_test_data::setTestNumber(42779);
        $plan1 = generat_plans::generatePlan(

            [

                "from" => "2025-08-01T22:00:00Z",
                "name" => "B2C_42779",
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
                "upfront" => 1,

                "connection_type" => "postpaid",
                "to" => "2026-09-05T13:23:53Z",
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
              'proration' => 'yes',
              'key'=> $discount_name,

          ]);

        $subscriber1 = generat_subscribers::generateSubscriber([
            'aid' => $account['aid'],
            "from" => "2018-07-04T21:00:00Z",
            "plan" => $plan1['name'],

        ]);
       

        return [
            'test' => [
                'label' => 'BRCD-5000: Discount for not prorate charge on termination plan with upfront plan',
                'test_number' => 42779,
                "aid" => $account['aid'],
                'function' => ['basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'],
                'options' => ["stamp" => "202509", "force_accounts" => [$account['aid']]]
            ],
            'expected' => [
                'billrun' => [
                    'billrun_key' => '202509',
                    'aid' => $account['aid'],
                    'vat' => 17,
                    'total' => 9.974816129,
                    'vatable' => 8.525483871, //subscriber1 ->plan1(16.79) discount1(-4.2 -4.064516129)
                ],
                'line' => ['types' => ['flat', 'credit']]
            ],

            'postRun' => [
            ]
        ];
    }
}

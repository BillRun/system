<?php

//this is example test 
require_once(APPLICATION_PATH . '/library/Tests/Util/Generators/generators.php');
class Test_Case_42772
{


    public function test_case()
    {
        $account = generat_subscribers::generateAccount();
        generat_test_data::setTestNumber(42772);
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
        
        $subscriber1 = generat_subscribers::generateSubscriber([
            'aid' => $account['aid'],
            "from" => "2018-07-04T21:00:00Z",
            "plan" => $plan['name'],
            "discounts" => [

                  [
                    "type" => "monetary",
                    "from" => "2019-05-31T22:00:00Z",
                    "to" => "2166-10-16T13:23:53Z",
                    "subject" => [
                        "plan" => [
                            $plan['name'] => ["value" => 80]
                        ]
                    ],
                    'key'=> $discount_name,
                    'description' => $discount_name,
                  ] 

                
            ],

        ]);
        $subscriber1['from'] = "2022-05-14T21:00:00Z";
        
        $subscriber1['discounts'] = [

            [
              "type" => "monetary",
              "from" => "2019-05-31T22:00:00Z",
              "to" => "2166-10-16T13:23:53Z",
              "subject" => [
                  "plan" => [
                      $plan['name'] => ["value" => 90]
                  ]
              ],
              'key'=> $discount_name,
              'description' => $discount_name,
            ] 

          
            ];
        $update1 = update_test_data::bulidAPI(
            'subscribers',
            ['update' => $subscriber1, 'query' => ['_id' => $subscriber1['_id']['$id']]]
        );

        return [
            'test' => [
                'label' => 'non-unique discount key- differnt discounts for 2 revisions of subscribers (should take the last revision) + general discount with this key not exists',
                'test_number' => 42772,
                "aid" => $account['aid'],
                'function' => ['basicCompare', 'totalsPrice', 'lineExists', 'linesVSbillrun', 'rounded'],
                'options' => ["stamp" => "202206", "force_accounts" => [$account['aid']]]
            ],
            'expected' => [
                'billrun' => [
                    'billrun_key' => '202206',
                    'aid' => $account['aid'],
                    'total' => 11.7,
                    'vatable' =>  10, //discount 90
                    'vat' => 17
                ],
                'line' => ['types' => ['flat', 'credit']]
            ],

            'postRun' => [
            ]
        ];
    }
}
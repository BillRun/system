<?php

//this is example test 
require_once(APPLICATION_PATH . '/library/Tests/Util/Generators/generators.php');
class Test_Case_42754
{


    public function test_case()
    {
        $plan_name = generat_test_data::uniqueName();
        $discount_name = generat_test_data::uniqueName();


        generat_test_data::setTestNumber(4298_2);
        $plan = generat_plans::generatePlan(['name' => $plan_name, 'from' => '2020-01-01', "upfront" => false, 'prorated_end' => true,
        'price'=>[["price" => 100, "from" => 0, "to" => "UNLIMITED"]], 'rounding_rules'=> ['rounding_type'=>'nearest', 'rounding_decimals'=>3]]);
        $account = generat_subscribers::generateAccount([
            "from" => '2023-07-01',
            "to" => "2118-05-06T11:06:07Z"
            
        ]);
        $subscriber = generat_subscribers::generateSubscriber([
            'aid' => $account['aid'],
            'plan' => $plan['name'],
            "from" => '2024-02-20',
            "to" => "2118-05-06T11:06:07Z"

        ]);
        $discount = generat_discounts::generateDiscount([
            "conditions" => [
                    [
                        "subscriber" => [
                            [
                                "fields" => [
                                    [
                                        "field" => "plan",
                                        "op" => "in",
                                        "value" => [$plan_name]
                                    ]
                                ]
                            ]
                        ]
                    ]
            ],
            "subject" => [
                "plan" => [
                    $plan_name => ["value" => 1]
                ]
            ],
            'key'=> $discount_name,
            "type" => "percentage"
        ]);
        $stamp = '202403';

        return [
            'test' => [
                'label' => "Discounts rounding inheret from plan (full discount)- rounding_type: nearest rounding_decimals: 3",
                'test_number' => 42754,
                'aid' => $account['aid'],
                'function' => [
                    'basicCompare',
                    'totalsPrice',
                    'linesVSbillrun',
                    'rounded',
                    'lineExists',
                    'checkPerLine'
                ],
                'options' => [
                    "stamp" => $stamp,
                    'force_accounts' => [
                        $account['aid']
                    ],
                ],
            ],
            'expected' => [
                'billrun' => [
                    'billrun_key' => $stamp,
                    'aid' => $account['aid'],
                    'total' => 0,
                    'vatable' => 0,
                    'vat' => 17,
                ],
                'line' => [
                    'types' => [
                        'flat','credit'
                    ],
                ],
                'lines' => [
                    [
                        'query' => [
                            'sid' => $subscriber['sid'],
                            'billrun' => $stamp,
                            'plan' => (string)$plan_name,
                            'type' => 'flat'
                        ],
                        'aprice' => 34.482905982906,
                        'final_charge' => 40.345,
                    ],
                    [
                        'query' => [
                            'sid' => $subscriber['sid'],
                            'billrun' => $stamp,
                            "usaget" => "discount",
                            "arate_key" => (string)$discount_name,
                            'plan' => (string)$plan_name,
                            'type' => 'credit'
                        ],
                        'aprice' => -34.482905982906,
                        'final_charge' => -40.345,

                    ],
                ],

            ],
            'postRun' => [
            ],
        ];
    }
}

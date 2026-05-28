<?php

//this is example test 
require_once(APPLICATION_PATH . '/library/Tests/Util/Generators/generators.php');
class Test_Case_42756
{


    public function test_case()
    {
        $plan_name = generat_test_data::uniqueName();
        $discount_name1 = generat_test_data::uniqueName();
        $discount_name2 = generat_test_data::uniqueName();


        generat_test_data::setTestNumber(4298_2);
        $plan = generat_plans::generatePlan(['name' => $plan_name, 'from' => '2020-01-01', "upfront" => false, 'prorated_end' => true,
        'price'=>[["price" => 1.234, "from" => 0, "to" => "UNLIMITED"]], 'rounding_rules'=> ['rounding_type'=>'up', 'rounding_decimals'=>2]]);
        $account = generat_subscribers::generateAccount([
            "from" => '2023-07-01',
            "to" => "2118-05-06T11:06:07Z"
            
        ]);
        $subscriber = generat_subscribers::generateSubscriber([
            'aid' => $account['aid'],
            'plan' => $plan['name'],
            "from" => '2024-01-01',
            "to" => "2118-05-06T11:06:07Z"

        ]);
        $discountFull = generat_discounts::generateDiscount([
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
                    $plan_name => ["value" => 0.9]
                ]
            ],
            'key'=> $discount_name1,
            "type" => "percentage"
        ]);
        $discountMonetary = generat_discounts::generateDiscount([
            "conditions" => [
                    [
                        "subscriber" => [
                            [
                                "fields" => [
                                    [
                                        "field" => "sid",
                                        "op" => "in",
                                        "value" => [$subscriber['sid']]
                                    ]
                                ]
                            ]
                        ]
                    ]
            ],
            "subject" => [
                "plan" => [
                    $plan_name => ["value" => 10]
                ]
            ],
            'key'=> $discount_name2,
            "type" => "monetary"
        ]);
        $stamp = '202403';

        return [
            'test' => [
                'label' => "limit discounts: 2 Discounts rounding inheret from plan (90% discount  + monetary)- rounding_type: up rounding_decimals: 2",
                'test_number' => 42756,
                'aid' => $account['aid'],
                'function' => [
                    'basicCompare',
                    'totalsPrice',
                    'linesVSbillrun',
                    'rounded',
                    'lineExists',
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
            ],
            'postRun' => [
            ],
        ];
    }
}

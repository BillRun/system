<?php

//this is example test 
require_once(APPLICATION_PATH . '/library/Tests/Util/Generators/generators.php');
class Test_Case_4298_3
{


    public function test_case()
    {
        $service_name = generat_test_data::uniqueName();
        $plan_name = generat_test_data::uniqueName();

        generat_test_data::setTestNumber(4298_3);
        $plan = generat_plans::generatePlan(['name' => $plan_name, 'from' => '2020-01-01',
        'price'=>[["price" => 100, "from" => 0, "to" => "UNLIMITED"]]]);
        $service = generat_services::generateService([
            'from' => '2020-01-01',
            'prorated' => false,
            'name' => $service_name,
            "price" => [["price" => 500, "from" => 0, "to" => "UNLIMITED"]]
        ]);
        $account = generat_subscribers::generateAccount([
            "from" => '2023-07-01',
            "to" => "2118-05-06T11:06:07Z",
            "overrides" => [
                [
                    "type" => "service",
                    "key" => $service_name,
                    "value" => [
                        "price" => [
                            [
                                "price" => 100,
                                "from" => 0,
                                "to" => 'UNLIMITED'
                            ],
                        ]
                    ]
                ]
                        ],
            
        ]);
        $subscriber = generat_subscribers::generateSubscriber([
            'aid' => $account['aid'],
            'plan' => $plan['name'],
            "from" => '2023-07-01',
            "to" => "2118-05-06T11:06:07Z",
            "overrides" => [
                [
                    "type" => "service",
                    "key" => $service_name,
                    "value" => [
                        "price" => [
                            [
                                "price" => 200,
                                "from" => 0,
                                "to" => 'UNLIMITED'
                            ],
                        ]
                    ]
                ]
                        ],
            'services' => [
                [
                    "name" => $service_name,
                    "from" => '2023-07-01T11:06:07Z',
                    "to" => "2118-05-06T11:06:07Z"
                ]
            ],

        ]);
        $stamp = '202310';

        return [
            'test' => [
                'label' => "account and subscriber with overridden about the same service with diff price (BRCD-4298)",
                'test_number' => 42983,
                'aid' => $account['aid'],
                'function' => [
                    'basicCompare',
                    'totalsPrice',
                    'linesVSbillrun',
                    'rounded'
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
                    'total' => 351,
                    'vatable' => 300,
                    'vat' => 17,
                ]

            ],
            'postRun' => [
            ],
        ];
    }
}

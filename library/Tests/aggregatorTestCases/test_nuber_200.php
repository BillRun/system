<?php

//this is example test 
require_once(APPLICATION_PATH .  '/library/Tests/Util/Generators/generators.php');
class Test_Case_200
{
    

    public function test_case()
    {
        $now = new DateTime();
        // Subtract one day
        $now->sub(new DateInterval('P1D'));
        $plan = generat_plans::generatePlan(['name' => "TEST_GENRATE"]);
        $service = generat_services::generateService(['name' => "TEST_GENRATE_SERVICE", 'from' => $now->format('Y-m-d')]);
        $discount = generat_discounts::generateDiscount();
        $account = generat_subscribers::generateAccount();
        $subscriber = generat_subscribers::generateSubscriber([
            'aid' => $account['aid'],
            'plan' => $plan['name'],
            'services' => [
                [
                    "name" => $service['name'],
                    "from" => "2023-01-05T22:00:00Z",
                    "to" => "2118-05-06T11:06:07Z"
                ]
            ]
        ]);

        return [
            'test' => [
                'test_number' => 200,
                'aid' => $account['aid'],
                'function' => [
                    
                ],
                'options' => [
                     "stamp" => Billrun_Billingcycle::getBillrunKeyByTimestamp(strtotime('-1 month')),
                    'force_accounts' => [
                        3,
                    ],
                ],
            ],
            'expected' => [
                'billrun' => [
                    'invoice_id' => 101,
                    'billrun_key' => '201805',
                    'aid' => 3,
                ],
                'line' => [
                    'types' => [
                        'flat',
                        'credit',
                    ],
                    'final_charge' => -10,
                ],
            ],
            'postRun' => [
            ],
        ];
    }
}
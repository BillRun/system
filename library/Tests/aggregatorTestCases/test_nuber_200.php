<?php

//this is example test 
require_once(APPLICATION_PATH .  '/library/Tests/Util/Generators/generators.php');
class Test_Case_200
{
    

    public function test_case()
    {

        generat_test_data::setTestNumber(200);
        $now = new DateTime();
        // Subtract one day
        $now->sub(new DateInterval('P1D'));
      
            $plan = generat_plans::generatePlan(['name' => "TEST_GENRATE",
            "price" => [ json_encode(["price" => 10, "from" => 0, "to" => "UNLIMITED"])]]);
            $service = generat_services::generateService(['name' => "TEST_GENRATE_SERVICE1", 
            'from' => $now->format('Y-m-d'),"price" => [ ["price" => 10, "from" => 0, "to" => "UNLIMITED"]]]);
            $discount = generat_discounts::generateDiscount();
            $account = generat_subscribers::generateAccount();
            $subscriber = generat_subscribers::generateSubscriber([
                'aid' => $account['aid'],
                'plan' => $plan['name'],
                'services' => [
                    [
                        "name" => $service['name'],
                        "from" => $now->format('Y-m-d'),
                        "to" => "2118-05-06T11:06:07Z"
                    ]
                ]
            ]);
            $stamp =Billrun_Billingcycle::getBillrunKeyByTimestamp(strtotime('-1 month'));
      

        return [
            'test' => [
                'label'=>"DEMO NEW TEST",
                'test_number' => 200,
                'aid' => $account['aid'],
                'function' => [
                    'basicCompare','totalsPrice','linesVSbillrun','rounded'
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
                ],
                'line' => [
                    'types' => [
                        'flat'
                    ],
                    'final_charge' => 23.4,
                ],
            ],
            'postRun' => [
            ],
        ];
    }
}

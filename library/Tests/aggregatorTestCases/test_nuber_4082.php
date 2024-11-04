<?php

//this is example test 
require_once(APPLICATION_PATH .  '/library/Tests/Util/Generators/generators.php');
class Test_Case_4082
{
    

    public function test_case()
    {
        $time = time();
        generat_test_data::setTestNumber(4082);
      
        $plan = generat_plans::generatePlan(['name' => "TEST_GENRATE$time",
        "price" => [ json_encode(["price" => 0, "from" => 0, "to" => "UNLIMITED"])]]);
        $service = generat_services::generateService([
            "prorated_start" =>0,
	        "prorated_end" =>0,
	        "prorated_termination" =>0,
            "prorated"  =>0,
            'name' => "TEST_GENRATE_SERVICE1$time", 
            'from' => "2018-05-06","price" => [ ["price" => 10, "from" => 0, "to" => "UNLIMITED"]]]);

        $account = generat_subscribers::generateAccount();
        $subscriber = generat_subscribers::generateSubscriber([
            'aid' => $account['aid'],
            'plan' => $plan['name'],
            "from" => "2024-05-06T11:06:07Z",
            "to" => "2024-05-20T11:06:07Z",
            "deactivation_date" => "2024-05-20T11:06:07Z",
            "creation_time" =>"2024-05-06T11:06:07Z",
            "activation_date" =>"2024-05-06T11:06:07Z",
            "plan_deactivation" =>"2024-05-20T11:06:07Z",
        
            'services' => [
                [
                    "name" => $service['name'],
                    "from" => "2024-05-06T11:06:07Z",
                    "to" => "2118-05-06T11:06:07Z"
                ]
            ]
        ]);
        $stamp = '202406';
  

  

        return [
            'test' => [
                'label'=>"test BRCD-4082",
                'test_number' => 4082,
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
                    'after_vat' => [
                        $subscriber['sid'] =>11.7,
                    ],
                    'total' => 11.7,
                    'vatable' => 10,
                    'vat' => 17
                ],
                'line' => [
                    'types' => [
                        'service'
                    ]
                  
                ],
            ],
            'postRun' => [
            ],
        ];
    }
}
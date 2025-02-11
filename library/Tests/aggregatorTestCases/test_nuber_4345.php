<?php

//this is example test 
require_once(APPLICATION_PATH . '/library/Tests/Util/Generators/generators.php');
class Test_Case_4345
{


    public function test_case()
    {

        generat_test_data::setTestNumber(4345);
        $plan = generat_plans::generatePlan(['name' =>'20240111134913711','from' => '2020-01-01']);
        $service = generat_services::generateService([
            'from' => '2020-01-01',
            'prorated' => false,
            'name' =>'20240111134913712',
            "price" => [["price" => 500, "from" => 0, "to" => "UNLIMITED"]]]);
        $account = generat_subscribers::generateAccount([
            "from" => '2023-07-01',
            "to" => "2118-05-06T11:06:07Z"
        ]);
        $subscriber = generat_subscribers::generateSubscriber([
            'aid' => $account['aid'],
            'plan' => $plan['name'],
            "from" => '2023-07-01',
            "to" => "2118-05-06T11:06:07Z",
            'services' => [
                [
                    "name" => $service['name'],
                    "from" => '2023-07-01T11:06:07Z',
                    "to" => "2118-05-06T11:06:07Z"
                ]
            ],
          
        ]);
        $stamp = '202310';

        return [
            'test' => [
                'label' => "test non prorated with ovveride (BRCD-4345)",
                'test_number' => 4345,
                'aid' => $account['aid'],
                'function' => [
                    'basicCompare', 'totalsPrice', 'linesVSbillrun', 'rounded'
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
                        'total' => 586.17,
                        'vatable' => 501,
                        'vat' => 17,
                ]
               
            ],
            'postRun' => [
            ],
        ];
    }
}

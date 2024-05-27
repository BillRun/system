<?php

//this is example test 
require_once(APPLICATION_PATH .  '/library/Tests/Util/Generators/generators.php');
class Test_Case_4456
{
    

    public function test_case()
    {

        generat_test_data::setTestNumber(4456);
        $now = new DateTime();
        // Subtract one day
        $now->sub(new DateInterval('P1D'));
      
  
            $account = generat_subscribers::generateAccount();
            $subscriber = generat_subscribers::generateSubscriber([
                'aid' => $account['aid'],
                'plan' => 'PLAN_A',
                "from" =>  "2020-05-06T11:06:07Z",
                'services' => [
                    [
                        "name" => 'SERVICE_A',
                        "from" =>  "2020-05-06T11:06:07Z",
                        "to" => "2118-05-06T11:06:07Z"
                    ]
                ]
            ]);

           $subscriber->from="2024-03-24T11:06:07Z";
           $update1 =  update_test_data::bulidAPI('subscribers',['update'=>$subscriber,'query'=>['_id'=>$subscriber['_id']['$id']]]);

           $update1->from="2024-03-24T11:07:07Z";
           $update2 =  update_test_data::bulidAPI('subscribers',['update'=> $update1 ,'query'=>['_id'=>$update1['_id']['$id']]]);

           $update2->from="2024-03-24T11:08:07Z";
           $update3 =  update_test_data::bulidAPI('subscribers',['update'=>$update2,'query'=>['_id'=>$update2['_id']['$id']]]);

           $update3->from="2024-03-24T11:09:07Z";
           $update3->services[]=[];
           $update3 =  update_test_data::bulidAPI('subscribers',['update'=>$update3,'query'=>['_id'=>$update3['_id']['$id']]]);


           $stamp = "202404";
      

        return [
            'test' => [
                'label'=>"DEMO NEW TEST",
                'test_number' => 4456,
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
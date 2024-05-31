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
           //make some revisions in the same cycle for the subscriber and in the last revision remove the service
           $subscriber['from']="2024-03-06T11:06:07Z";
           $subscriber['firstname']="yossi_update";
           $update1 =  update_test_data::bulidAPI('subscribers',['update'=>$subscriber,'query'=>['_id'=>$subscriber['_id']['$id']]]);

           $update1['entity']['from']="2024-03-06T11:06:08Z";
           $update2 =  update_test_data::bulidAPI('subscribers',['update'=> $update1['entity'] ,'query'=>['_id'=>$update1['entity']['_id']['$id']]]);

           $update2['entity']['from']="2024-03-06T11:06:09Z";
           $update3 =  update_test_data::bulidAPI('subscribers',['update'=>$update2['entity'],'query'=>['_id'=>$update2['entity']['_id']['$id']]]);

           $update3['entity']['from']="2024-03-06T11:06:10Z";
           $update3['entity']['services']=[];
           $update3 =  update_test_data::bulidAPI('subscribers',['update'=>$update3['entity'],'query'=>['_id'=>$update3['entity']['_id']['$id']]]);


           $stamp = "202404";
      

        return [
            'test' => [
                'label'=>"test BRCD-4456",
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
                    'after_vat' => [
                        $subscriber['sid'] =>127.94516129032259,
                    ],
                    'total' => 127.94516129032259,
                    'vatable' => 109.35483870967744,
                    'vat' => 17,
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
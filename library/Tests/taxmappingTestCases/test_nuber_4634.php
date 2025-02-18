<?php

//this is example test 
require_once(APPLICATION_PATH . '/library/Tests/Util/Generators/generators.php');
class Test_Case_4634
{


    public function test_case()
    {
        
        generat_test_data::setTestNumber(4634);
        $plan = generat_plans::generatePlan(['name' =>'PLAN_'.time(),'from' => '2020-01-01']);
        
        $account = generat_subscribers::generateAccount([
            "from" => '2023-07-01',
            "to" => "2118-05-06T11:06:07Z"
        ]);
        $subscriber = generat_subscribers::generateSubscriber([
            'aid' => $account['aid'],
            'plan' => $plan['name'],
            "from" => '2023-07-01',
            "to" => "2118-05-06T11:06:07Z"
        ]);
        $stamp = '202310';

        return [
            'test'=>['test_number' => 4634],
            'run_customer_pricinig'=> true,
            'row' => [
                'stamp' => '4634',
                'aid' => $account['aid'],
                'sid' => $subscriber['aid'],
                'rates' => array('DEFAULT_TAX_CALL' => 'retail'), 
                'plan' => $subscriber['plan'], 
                'type' => 'realTime',
                'usaget' => 'call',
                'usagev' => 220,
				'urt' => '2022-02-05'
            ],
            'type'=>'cdr',
            'label' => "test billrun key after tax(BRCD-4634)",
            'test_number' => 4634,
            'functions' => [
                'checkBillrun'
            ],
            'expected' => [
             'billrun'=>  Billrun_Billingcycle::getBillrunKeyByTimestamp(time()),
            ],
            'postRun' => [
            ],
        ];
    }
}
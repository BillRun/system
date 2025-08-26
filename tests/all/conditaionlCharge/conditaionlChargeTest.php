<?php

class conditaionlChargeTest extends \Codeception\Test\Unit
{
   
/**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
        
    }

    protected function _after()
    {
    }
     

    protected function createTestData($account =[],$subscriber =[],$plan =[]){
       
        $this->tester->generatePlan($plan);
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->tester->createAccountWithAllMandatoryCustomFields($account);
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $subscriber =  $this->tester->generateSubscriber(
            [
                'aid' => $account['aid'],
                'plan' => $plan['name']
            ]
        );
        $subscriber = json_decode($this->tester->grabResponse(), true)['entity'];
        return ['account'=>$account,'subscriber'=>$subscriber,'plan'=>$plan];
    }
    public $defaultOptions = array(
        "type" => "customer",
        "stamp" => "202410",
        "page" => 0,
        "size" => 100,
        'fetchonly' => true,
        'generate_pdf' => 0,
        "force_accounts" => array(123)
    );

    protected $epsilon = 0.00001;
   
   
    /**
     * Tests the scenario where a subscriber does not match the charge condition.
     *
     * This function creates test data with a specific plan, runs a billing cycle,
     * and verifies that the correct charge is applied to the account.
     *
     * @return void
     */
    public function test_subscriberNotmutchChargeCondition()
    {
       $data =  $this->createTestData([],[],['name' => 'PLAN_'.microtime(true)*10000, "price" => [
            [
                "price" => 100,
                "from" => 0,
                "to" => "UNLIMITED"
            ]
        ]]);

        $this->tester->generateConditaionlCharge(["params"=> [
            "min_subscribers"=> "",
            "max_subscribers"=> "",
            "conditions"=> [
                [
                    "subscriber"=> [
                        [
                            "fields"=> [
                                [
                                    "field"=> "sid",
                                    "op"=> "nin",
                                    "value"=> [
                                        $data['subscriber']['sid']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]]);
        $charge = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->defaultOptions['force_accounts'] = [$data['account']['aid']];
        $this->defaultOptions["stamp"] = "202501";
        $this->tester->runCycle($this->defaultOptions);
        $this->tester->removeCollectionRecord('charges', array('key' =>$charge['key'] ));
        $this->tester->verifyCollectionRecord('billrun', array('billrun_key' => '202501', 'aid' =>  $data['account']['aid'],'totals.before_vat'=>100));
    }
    
    public function test_subcriberConditionWithoutSubscribers()
    {
        //BRCD-4962
        //sub condition without subscribers - excpect not get charge
        $service = [
            "name" => "SPLIT_BILL_DISCOUNT_" . microtime(true)*10000,
            "price" => [
                [
                    "from"  => 0,
                    "to"    => "UNLIMITED",
                    "price" => 20
                ]
            ],
            "description" => "SPLIT_BILL_DISCOUNT",
            "prorated" => true,
            "tax" => [
                [
                    "type"      => "vat",
                    "taxation"  => "global"
                ]
            ],
            "recurrence" => [
                "periodicity" => "month"
            ],
        ];

        $this->tester->generateService($service);
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $account = ["services" => [
            [
                "name" => $service['name'],
                "from" => "2024-05-16 08:45:53",
                "to"   => "2225-07-09 09:03:17",
                "service_id" => 576851,
                "creation_time" => "2024-05-16 08:45:53"
            ]
        ],
        "overrides" => [
            [
                "type" => "service",
                "id"   => 576851,
                "key"  => $service['name'],
                "value" => [
                    "price" => [
                        [
                            "price" => 100,
                            "from"  => 0,
                            "to"    => "UNLIMITED"
                        ]
                    ]
                ]
            ]
        ]];
        $this->tester->createAccountWithAllMandatoryCustomFields($account);
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        
       
        $this->tester->generateConditaionlCharge([
            "key" => "TERMINATION_FEE_" .microtime(true)*10000,
            "description" => "Termination fee",
            "params" => [
                "min_subscribers" => "",
                "max_subscribers" => "",
                "conditions" => [
                    [
                        "subscriber" => [
                            [
                                "fields" => [
                                    [
                                        "field" => "deactivation_date",
                                        "op"    => "lt",
                                        "value" => "@cycle_end_date@"
                                    ],
                                    [
                                        "field" => "deactivation_date",
                                        "op"    => "gte",
                                        "value" => "@cycle_start_date@"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "type" => "monetary",
            "subject" => [
                "general" => [
                    "value" => 30
                ]
            ],
            "proration" => "inherited",

        ]);
        $charge = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->defaultOptions['force_accounts'] = [$account['aid']];
        $activeBillrun = Billrun_Billrun::getActiveBillrun();
        $this->defaultOptions["stamp"] = $activeBillrun;
        $this->tester->runCycle($this->defaultOptions);
        $this->tester->removeCollectionRecord('charges', array('key' =>$charge['key'] ));
        $this->tester->verifyCollectionRecord('billrun', array('billrun_key' => $activeBillrun, 'aid' =>  $account['aid'],'totals.before_vat'=>100));
    }

    public function test_noConditionsWithoutSubscribers(){

        //no subs condition - excpect get charge
        $service = [
            "name" => "SPLIT_BILL_DISCOUNT_" . microtime(true)*10000,
            "price" => [
                [
                    "from"  => 0,
                    "to"    => "UNLIMITED",
                    "price" => 20
                ]
            ],
            "description" => "SPLIT_BILL_DISCOUNT",
            "prorated" => true,
            "tax" => [
                [
                    "type"      => "vat",
                    "taxation"  => "global"
                ]
            ],
            "recurrence" => [
                "periodicity" => "month"
            ],
        ];

        $this->tester->generateService($service);
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $account = ["services" => [
            [
                "name" => $service['name'],
                "from" => "2024-05-16 08:45:53",
                "to"   => "2225-07-09 09:03:17",
                "service_id" => 576851,
                "creation_time" => "2024-05-16 08:45:53"
            ]
        ],
        "overrides" => [
            [
                "type" => "service",
                "id"   => 576851,
                "key"  => $service['name'],
                "value" => [
                    "price" => [
                        [
                            "price" => 100,
                            "from"  => 0,
                            "to"    => "UNLIMITED"
                        ]
                    ]
                ]
            ]
        ]];
        $this->tester->createAccountWithAllMandatoryCustomFields($account);
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        
       
        $this->tester->generateConditaionlCharge([
            "key" => "TERMINATION_FEE_" .microtime(true)*10000,
            "description" => "Termination fee",
            "params" => [
                "min_subscribers" => "",
                "max_subscribers" => "",
                "conditions" => [[]
                ]
            ],
            "type" => "monetary",
            "subject" => [
                "general" => [
                    "value" => 30
                ]
            ],
            "proration" => "inherited",
        ]);
        $charge = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->defaultOptions['force_accounts'] = [$account['aid']];
        $activeBillrun = Billrun_Billrun::getActiveBillrun();
        $this->defaultOptions["stamp"] = $activeBillrun;
        $this->tester->runCycle($this->defaultOptions);
        $this->tester->removeCollectionRecord('charges', array('key' =>$charge['key'] ));
        $this->tester->verifyCollectionRecord('billrun', array('billrun_key' => $activeBillrun, 'aid' =>  $account['aid'],'totals.before_vat'=>130));
    }
}
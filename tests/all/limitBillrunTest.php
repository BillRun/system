<?php

class limitBillrunTest extends \Codeception\Test\Unit
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
    

    protected function createTestData($account =[],$subscriber =[],$plan =[],$numOfSubscriber=1){
        $this->tester->generatePlan($plan);
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->tester->createAccountWithAllMandatoryCustomFields($account);
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        for($i=0;$i<$numOfSubscriber;$i++){
            $this->tester->generateSubscriber(
                [
                    'aid' => $account['aid'],
                    'plan' => $plan['name']
                ]
            );
            $subscriber[] = json_decode($this->tester->grabResponse(), true)['entity'];
        }
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
     * Tests the behavior of the billrun object when the subscriber limit is set to 1.
     * 
     * This test verifies that when the 'billrun.save_to_file_subs_limit' configuration
     * is set to 1, the billrun object is created with an empty 'subs' array. This happens
     * because the subscriber data is saved to a file instead of being stored directly in
     * the billrun object when the number of subscribers exceeds the configured limit.
     * 
     * The test:
     * 1. Gets the active billrun stamp
     * 2. Sets the subscriber limit to 1
     * 3. Creates test data with a plan
     * 4. Runs a billing cycle for the created account
     * 5. Verifies that the billrun object has an empty 'subs' array
     * 
     * @return void
     */
    public function test_limitBillrunObjectTo1_empty_subs()
    {
        $stamp = Billrun_Billrun::getActiveBillrun();
       \Billrun_Factory::config()->setConfigValue('billrun.save_to_file_subs_limit', 1);
       $data =  $this->createTestData([],[],[ "price" => [
            [
                "price" => 100,
                "from" => 0,
                "to" => "UNLIMITED"
            ]
        ]]);
        $this->defaultOptions['force_accounts'] = [$data['account']['aid']];
        $this->defaultOptions["stamp"] = $stamp ;
        $this->tester->runCycle($this->defaultOptions);
        $this->tester->seeInCollection('billrun', ['billrun_key' => $stamp, 'aid' =>  $data['account']['aid'],'subs'=>[]]);
    }
   
    public function test_limitBillrunObjectTo2_haveOneSub_saveToSubs()
    {
        $stamp = Billrun_Billrun::getActiveBillrun();
       \Billrun_Factory::config()->setConfigValue('billrun.save_to_file_subs_limit', 2);
       $data =  $this->createTestData([],[],["price" => [
            [
                "price" => 100,
                "from" => 0,
                "to" => "UNLIMITED"
            ]
        ]]);
        $this->defaultOptions['force_accounts'] = [$data['account']['aid']];
        $this->defaultOptions["stamp"] = $stamp ;
        $this->tester->runCycle($this->defaultOptions);
        $this->tester->seeInCollection('billrun', [
            'billrun_key' => $stamp, 
            'aid' =>  $data['account']['aid'],
            'subs.0.sid'=>0,
            'subs.1.sid'=>$data['subscriber'][0]['sid']
            ]
        );
    } 
 


	public function test_expandSubRevisions_1(){
        //3 services that start from the same revision and each ends on a different date – 4 revisions 
        $plan = 
        [

            "from" => "2024-08-03T22:00:00Z",
            "name" => "PLAN_A" . time(),
            "price" => [
                [
                    "price" => 0,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ],
            "recurrence" => [
                "periodicity" => "month"
            ],
            "upfront" => 0,
            "connection_type" => "postpaid",
            "to" => "2028-09-05T13:23:53Z",
            "creation_time" => "2017-07-01T04:00:00Z",
            "prorated_start" => true,
            "prorated_end" => true,
            "prorated_termination" => true
        ];
        $this->tester->generatePlan($plan);
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $service1 = [
            'from' => '2017-07-01T04:00:00Z',
            'name' => "SERVICE_1" . time(),
            "price" => [["price" => 100, "from" => 0, "to" => "UNLIMITED"]],
        ];
        $this->tester->generateService($service1);
        $service1 = json_decode($this->tester->grabResponse(), true)['entity'];
        $service1['from'] =  new Mongodloid_Date($service1['from']['sec']);
        $service1['to'] =  new Mongodloid_Date($service1['to']['sec']);
        $services[$service1['name']] = new Mongodloid_Entity($service1);

        $service2 = [
            'from' => '2017-07-01T04:00:00Z',
            'name' => "SERVICE_2" . time(),
            "price" => [["price" => 100, "from" => 0, "to" => "UNLIMITED"]],
        ];
        $this->tester->generateService($service2);
        $service2 = json_decode($this->tester->grabResponse(), true)['entity'];
        $service2['from'] =  new Mongodloid_Date($service2['from']['sec']);
        $service2['to'] =  new Mongodloid_Date($service2['to']['sec']);
        $services[$service2['name']] = new Mongodloid_Entity($service2);

        $service3 = [
            'from' => '2017-07-01T04:00:00Z',
            'name' => "SERVICE_3" . time(),
            "price" => [["price" => 100, "from" => 0, "to" => "UNLIMITED"]],
        ];
        $this->tester->generateService($service3);
        $service3 = json_decode($this->tester->grabResponse(), true)['entity'];
        $service3['from'] =  new Mongodloid_Date($service3['from']['sec']);
        $service3['to'] =  new Mongodloid_Date($service3['to']['sec']);
        $services[$service3['name']] = new Mongodloid_Entity($service3);        

        $this->tester->createAccountWithAllMandatoryCustomFields();
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->tester->generateSubscriber(
            [
                'aid' => $account['aid'],
                "from" => "2018-07-04T21:00:00Z",
                "plan" => $plan['name'],
                'services' => [
                        [
                            "name" => $service1['name'],
                            "from" => "2025-09-05T04:00:00Z",
                            "to" =>  "2025-10-05T04:00:00Z"
                        ],
                        [
                            "name" => $service2['name'],
                            "from" => "2025-09-05T04:00:00Z",
                            "to" => "2025-10-06T04:00:00Z"
                        ],
                        [
                            "name" => $service3['name'],
                            "from" => "2025-09-05T04:00:00Z",
                            "to" => "2025-10-07T04:00:00Z"
                            ]
                    ]
            ]
        );
        $subscriber = json_decode($this->tester->grabResponse(), true)['entity'];
        $subRevisionsFields = ['services','plans'];
        foreach($subRevisionsFields as $fieldName) {
			if(empty($subscriber[$fieldName])) { continue; }
			foreach($subscriber[$fieldName] as &$subRev) {
                if(!empty($subRev['from']['sec'])) {
                    $subRev['from'] =  new Mongodloid_Date($subRev['from']['sec']);
                }
                if(!empty($subRev['to']['sec'])) {
                    $subRev['to'] =  new Mongodloid_Date($subRev['to']['sec']);
                }
                if(!empty($subRev['creation_time']['sec'])) {
                    $subRev['creation_time'] =  new Mongodloid_Date($subRev['creation_time']['sec']);
                }
            }
        }
        $subscriber['from'] =  $subscriber['from']['sec'];
        $subscriber['to'] =  $subscriber['to']['sec'];
        $stamp = "202511";
        $cycle = new Billrun_DataTypes_CycleTime($stamp);
        $subRevisions = Billrun_Cycle_Account::expandSubRevisions($subscriber, $cycle->start(),$cycle->end(), $services);
		$this->assertEquals(4, count($subRevisions));
        $this->assertEquals(3, count($subRevisions[0]['services']));
        $this->assertEquals(1759636800, $subRevisions[0]['to']->sec);//5.10

        $this->assertEquals(2, count($subRevisions[1]['services']));
        $this->assertEquals(1759723200, $subRevisions[1]['to']->sec);//6.10

        $this->assertEquals(1, count($subRevisions[2]['services']));
        $this->assertEquals(1759809600, $subRevisions[2]['to']->sec);//7.10
        
        $this->assertEquals(0, count($subRevisions[3]['services']));
        $this->assertEquals(1761955200, $subRevisions[3]['to']->sec);//1.11

    } 
    
    public function test_expandSubRevisions_2(){
        //3 services that end on the same revision and each starts on a different date – 4 revisions
        $plan = 
        [

            "from" => "2024-08-03T22:00:00Z",
            "name" => "PLAN_A" . time(),
            "price" => [
                [
                    "price" => 0,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ],
            "recurrence" => [
                "periodicity" => "month"
            ],
            "upfront" => 0,
            "connection_type" => "postpaid",
            "to" => "2028-09-05T13:23:53Z",
            "creation_time" => "2017-07-01T04:00:00Z",
            "prorated_start" => true,
            "prorated_end" => true,
            "prorated_termination" => true
        ];
        $this->tester->generatePlan($plan);
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $service1 = [
            'from' => '2017-07-01T04:00:00Z',
            'name' => "SERVICE_1" . time(),
            "price" => [["price" => 100, "from" => 0, "to" => "UNLIMITED"]],
        ];
        $this->tester->generateService($service1);
        $service1 = json_decode($this->tester->grabResponse(), true)['entity'];
        $service1['from'] =  new Mongodloid_Date($service1['from']['sec']);
        $service1['to'] =  new Mongodloid_Date($service1['to']['sec']);
        $services[$service1['name']] = new Mongodloid_Entity($service1);

        $service2 = [
            'from' => '2017-07-01T04:00:00Z',
            'name' => "SERVICE_2" . time(),
            "price" => [["price" => 100, "from" => 0, "to" => "UNLIMITED"]],
        ];
        $this->tester->generateService($service2);
        $service2 = json_decode($this->tester->grabResponse(), true)['entity'];
        $service2['from'] =  new Mongodloid_Date($service2['from']['sec']);
        $service2['to'] =  new Mongodloid_Date($service2['to']['sec']);
        $services[$service2['name']] = new Mongodloid_Entity($service2);

        $service3 = [
            'from' => '2017-07-01T04:00:00Z',
            'name' => "SERVICE_3" . time(),
            "price" => [["price" => 100, "from" => 0, "to" => "UNLIMITED"]],
        ];
        $this->tester->generateService($service3);
        $service3 = json_decode($this->tester->grabResponse(), true)['entity'];
        $service3['from'] =  new Mongodloid_Date($service3['from']['sec']);
        $service3['to'] =  new Mongodloid_Date($service3['to']['sec']);
        $services[$service3['name']] = new Mongodloid_Entity($service3);        

        $this->tester->createAccountWithAllMandatoryCustomFields();
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->tester->generateSubscriber(
            [
                'aid' => $account['aid'],
                "from" => "2018-07-04T21:00:00Z",
                "plan" => $plan['name'],
                'services' => [
                        [
                            "name" => $service1['name'],
                            "from" => "2025-10-05T04:00:00Z",
                            "to" => "2025-11-21T04:00:00Z"
                        ],
                        [
                            "name" => $service2['name'],
                            "from" => "2025-10-06T04:00:00Z",
                            "to" => "2025-11-21T04:00:00Z"
                        ],
                        [
                            "name" => $service3['name'],
                            "from" => "2025-10-07T04:00:00Z",
                            "to" => "2025-11-21T04:00:00Z"
                        ]
                ],
            ]
        );
        $subscriber = json_decode($this->tester->grabResponse(), true)['entity'];
        $subRevisionsFields = ['services','plans'];
        foreach($subRevisionsFields as $fieldName) {
			if(empty($subscriber[$fieldName])) { continue; }
			foreach($subscriber[$fieldName] as &$subRev) {
                if(!empty($subRev['from']['sec'])) {
                    $subRev['from'] =  new Mongodloid_Date($subRev['from']['sec']);
                }
                if(!empty($subRev['to']['sec'])) {
                    $subRev['to'] =  new Mongodloid_Date($subRev['to']['sec']);
                }
                if(!empty($subRev['creation_time']['sec'])) {
                    $subRev['creation_time'] =  new Mongodloid_Date($subRev['creation_time']['sec']);
                }
            }
        }
        $subscriber['from'] =  $subscriber['from']['sec'];
        $subscriber['to'] =  $subscriber['to']['sec'];
        $stamp = "202511";
        $cycle = new Billrun_DataTypes_CycleTime($stamp);
        $subRevisions = Billrun_Cycle_Account::expandSubRevisions($subscriber, $cycle->start(),$cycle->end(), $services);
		$this->assertEquals(4, count($subRevisions));
        $this->assertEquals(0, count($subRevisions[0]['services']));
        $this->assertEquals(1759276800, $subRevisions[0]['from']->sec);//1.10

        $this->assertEquals(1, count($subRevisions[1]['services']));
        $this->assertEquals(1759636800, $subRevisions[1]['from']->sec);//5.10

        $this->assertEquals(2, count($subRevisions[2]['services']));
        $this->assertEquals(1759723200, $subRevisions[2]['from']->sec);//6.10

        $this->assertEquals(3, count($subRevisions[3]['services']));
        $this->assertEquals(1759809600, $subRevisions[3]['from']->sec);//7.10
        
       

    } 

    public function test_expandSubRevisions_3(){
        //he main revision starts earlier and ends later + the second case – 5 revisions 
        $plan = 
        [

            "from" => "2024-08-03T22:00:00Z",
            "name" => "PLAN_A" . time(),
            "price" => [
                [
                    "price" => 0,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ],
            "recurrence" => [
                "periodicity" => "month"
            ],
            "upfront" => 0,
            "connection_type" => "postpaid",
            "to" => "2028-09-05T13:23:53Z",
            "creation_time" => "2017-07-01T04:00:00Z",
            "prorated_start" => true,
            "prorated_end" => true,
            "prorated_termination" => true
        ];
        $this->tester->generatePlan($plan);
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $service1 = [
            'from' => '2017-07-01T04:00:00Z',
            'name' => "SERVICE_1" . time(),
            "price" => [["price" => 100, "from" => 0, "to" => "UNLIMITED"]],
            'description' => "SERVICE_1"
        ];
        $this->tester->generateService($service1);
        $service1 = json_decode($this->tester->grabResponse(), true)['entity'];
        $service1['from'] =  new Mongodloid_Date($service1['from']['sec']);
        $service1['to'] =  new Mongodloid_Date($service1['to']['sec']);
        $services[$service1['name']] = new Mongodloid_Entity($service1);

        $service2 = [
            'from' => '2017-07-01T04:00:00Z',
            'name' => "SERVICE_2" . time(),
            "price" => [["price" => 100, "from" => 0, "to" => "UNLIMITED"]],
            'description' => "SERVICE_2"
        ];
        $this->tester->generateService($service2);
        $service2 = json_decode($this->tester->grabResponse(), true)['entity'];
        $service2['from'] =  new Mongodloid_Date($service2['from']['sec']);
        $service2['to'] =  new Mongodloid_Date($service2['to']['sec']);
        $services[$service2['name']] = new Mongodloid_Entity($service2);

        $service3 = [
            'from' => '2017-07-01T04:00:00Z',
            'name' => "SERVICE_3" . time(),
            "price" => [["price" => 100, "from" => 0, "to" => "UNLIMITED"]],
            'description' => "SERVICE_3"
        ];
        $this->tester->generateService($service3);
        $service3 = json_decode($this->tester->grabResponse(), true)['entity'];
        $service3['from'] =  new Mongodloid_Date($service3['from']['sec']);
        $service3['to'] =  new Mongodloid_Date($service3['to']['sec']);
        $services[$service3['name']] = new Mongodloid_Entity($service3);        

        $this->tester->createAccountWithAllMandatoryCustomFields();
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->tester->generateSubscriber(
            [
                'aid' => $account['aid'],
                "from" => "2018-07-04T21:00:00Z",
                "plan" => $plan['name'],
                'services' => [
                        [
                            "name" => $service1['name'],
                            "from" => "2025-10-05T04:00:00Z",
                            "to" => "2025-10-21T04:00:00Z"
                        ],
                        [
                            "name" => $service2['name'],
                            "from" => "2025-10-06T04:00:00Z",
                            "to" => "2025-10-21T04:00:00Z"
                        ],
                        [
                            "name" => $service3['name'],
                            "from" => "2025-10-07T04:00:00Z",
                            "to" => "2025-10-21T04:00:00Z"
                            ]
                    ]
            ]
        );
        $subscriber = json_decode($this->tester->grabResponse(), true)['entity'];
        $subRevisionsFields = ['services','plans'];
        foreach($subRevisionsFields as $fieldName) {
			if(empty($subscriber[$fieldName])) { continue; }
			foreach($subscriber[$fieldName] as &$subRev) {
                if(!empty($subRev['from']['sec'])) {
                    $subRev['from'] =  new Mongodloid_Date($subRev['from']['sec']);
                }
                if(!empty($subRev['to']['sec'])) {
                    $subRev['to'] =  new Mongodloid_Date($subRev['to']['sec']);
                }
                if(!empty($subRev['creation_time']['sec'])) {
                    $subRev['creation_time'] =  new Mongodloid_Date($subRev['creation_time']['sec']);
                }
            }
        }
        $subscriber['from'] =  $subscriber['from']['sec'];
        $subscriber['to'] =  $subscriber['to']['sec'];
        $stamp = "202511";
        $cycle = new Billrun_DataTypes_CycleTime($stamp);
        $subRevisions = Billrun_Cycle_Account::expandSubRevisions($subscriber, $cycle->start(),$cycle->end(), $services);
		$this->assertEquals(5, count($subRevisions));
        $this->assertEquals(0, count($subRevisions[0]['services']));
        $this->assertEquals(1759276800, $subRevisions[0]['from']->sec);//1.10

        $this->assertEquals(1, count($subRevisions[1]['services']));
        $this->assertEquals(1759636800, $subRevisions[1]['from']->sec);//5.10

        $this->assertEquals(2, count($subRevisions[2]['services']));
        $this->assertEquals(1759723200, $subRevisions[2]['from']->sec);//6.10

        $this->assertEquals(3, count($subRevisions[3]['services']));
        $this->assertEquals(1759809600, $subRevisions[3]['from']->sec);//7.10
        
        $this->assertEquals(0, count($subRevisions[4]['services']));
        $this->assertEquals(1761019200, $subRevisions[4]['from']->sec);//21.10

    } 

    // public function test_expandSubRevisions_4(){
    //     //he main revision starts earlier and ends later + the second case – 5 revisions 
    //     $plan = 
    //     [

    //         "from" => "2024-08-03T22:00:00Z",
    //         "name" => "PLAN_A" . time(),
    //         "price" => [
    //             [
    //                 "price" => 0,
    //                 "from" => 0,
    //                 "to" => "UNLIMITED"
    //             ]
    //         ],
    //         "recurrence" => [
    //             "periodicity" => "month"
    //         ],
    //         "upfront" => 0,
    //         "connection_type" => "postpaid",
    //         "to" => "2028-09-05T13:23:53Z",
    //         "creation_time" => "2017-07-01T04:00:00Z",
    //         "prorated_start" => true,
    //         "prorated_end" => true,
    //         "prorated_termination" => true
    //     ];
    //     $this->tester->generatePlan($plan);
    //     $plan = json_decode($this->tester->grabResponse(), true)['entity'];
    //     $service1 = [
    //         'from' => '2017-07-01T04:00:00Z',
    //         'name' => "SERVICE_1" . time(),
    //         "price" => [["price" => 100, "from" => 0, "to" => "UNLIMITED"]],
    //         'description' => "SERVICE_1"
    //     ];
    //     $this->tester->generateService($service1);
    //     $service1 = json_decode($this->tester->grabResponse(), true)['entity'];
    //     $service1['from'] =  new Mongodloid_Date($service1['from']['sec']);
    //     $service1['to'] =  new Mongodloid_Date($service1['to']['sec']);
    //     $services[$service1['name']] = new Mongodloid_Entity($service1);

    //     $service2 = [
    //         'from' => '2017-07-01T04:00:00Z',
    //         'name' => "SERVICE_2" . time(),
    //         "price" => [["price" => 100, "from" => 0, "to" => "UNLIMITED"]],
    //         'description' => "SERVICE_2"
    //     ];
    //     $this->tester->generateService($service2);
    //     $service2 = json_decode($this->tester->grabResponse(), true)['entity'];
    //     $service2['from'] =  new Mongodloid_Date($service2['from']['sec']);
    //     $service2['to'] =  new Mongodloid_Date($service2['to']['sec']);
    //     $services[$service2['name']] = new Mongodloid_Entity($service2);

    //     $service3 = [
    //         'from' => '2017-07-01T04:00:00Z',
    //         'name' => "SERVICE_3" . time(),
    //         "price" => [["price" => 100, "from" => 0, "to" => "UNLIMITED"]],
    //         'description' => "SERVICE_3"
    //     ];
    //     $this->tester->generateService($service3);
    //     $service3 = json_decode($this->tester->grabResponse(), true)['entity'];
    //     $service3['from'] =  new Mongodloid_Date($service3['from']['sec']);
    //     $service3['to'] =  new Mongodloid_Date($service3['to']['sec']);
    //     $services[$service3['name']] = new Mongodloid_Entity($service3);        

    //     $this->tester->createAccountWithAllMandatoryCustomFields();
    //     $account = json_decode($this->tester->grabResponse(), true)['entity'];
    //     $this->tester->generateSubscriber(
    //         [
    //             'aid' => $account['aid'],
    //             "from" => "2018-07-04T21:00:00Z",
    //             "plan" => $plan['name'],
    //             'services' => [
    //                     [
    //                         "name" => $service1['name'],
    //                         "from" => "2025-10-05T04:00:00Z",
    //                         "to" => "2025-10-21T04:00:00Z"
    //                     ],
    //                     [
    //                         "name" => $service2['name'],
    //                         "from" => "2025-10-06T04:00:00Z",
    //                         "to" => "2025-10-21T05:00:00Z"
    //                     ],
    //                     [
    //                         "name" => $service3['name'],
    //                         "from" => "2025-10-07T04:00:00Z",
    //                         "to" => "2025-10-21T06:00:00Z"
    //                         ]
    //                 ]
    //         ]
    //     );
    //     $subscriber = json_decode($this->tester->grabResponse(), true)['entity'];
    //     $subRevisionsFields = ['services','plans'];
    //     foreach($subRevisionsFields as $fieldName) {
	// 		if(empty($subscriber[$fieldName])) { continue; }
	// 		foreach($subscriber[$fieldName] as &$subRev) {
    //             if(!empty($subRev['from']['sec'])) {
    //                 $subRev['from'] =  new Mongodloid_Date($subRev['from']['sec']);
    //             }
    //             if(!empty($subRev['to']['sec'])) {
    //                 $subRev['to'] =  new Mongodloid_Date($subRev['to']['sec']);
    //             }
    //             if(!empty($subRev['creation_time']['sec'])) {
    //                 $subRev['creation_time'] =  new Mongodloid_Date($subRev['creation_time']['sec']);
    //             }
    //         }
    //     }
    //     $subscriber['from'] =  $subscriber['from']['sec'];
    //     $subscriber['to'] =  $subscriber['to']['sec'];
    //     $stamp = "202511";
    //     $cycle = new Billrun_DataTypes_CycleTime($stamp);
    //     $subRevisions = Billrun_Cycle_Account::expandSubRevisions($subscriber, $cycle->start(),$cycle->end(), $services);
	// 	$this->assertEquals(5, count($subRevisions));
    //     $this->assertEquals(0, count($subRevisions[0]['services']));
    //     $this->assertEquals(1759276800, $subRevisions[0]['from']->sec);//1.10

    //     $this->assertEquals(1, count($subRevisions[1]['services']));
    //     $this->assertEquals(1759636800, $subRevisions[1]['from']->sec);//5.10

    //     $this->assertEquals(2, count($subRevisions[2]['services']));
    //     $this->assertEquals(1759723200, $subRevisions[2]['from']->sec);//6.10

    //     $this->assertEquals(3, count($subRevisions[3]['services']));
    //     $this->assertEquals(1759809600, $subRevisions[3]['from']->sec);//7.10
        
    //     $this->assertEquals(2, count($subRevisions[4]['services']));
    //     $this->assertEquals(1761019200, $subRevisions[4]['from']->sec);//21.10

    //     $this->assertEquals(1, count($subRevisions[4]['services']));
    //     $this->assertEquals(1761019200, $subRevisions[4]['from']->sec);//21.10

    //     $this->assertEquals(0, count($subRevisions[4]['services']));
    //     $this->assertEquals(1761019200, $subRevisions[4]['from']->sec);//21.10

    // } 
    
    public function test_expandSubRevisions_5(){
        //3 services that end on the same revision and each starts on a different date – 4 revisions
        $plan = 
        [

            "from" => "2024-08-03T22:00:00Z",
            "name" => "PLAN_A" . time(),
            "price" => [
                [
                    "price" => 0,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ],
            "recurrence" => [
                "periodicity" => "month"
            ],
            "upfront" => 0,
            "connection_type" => "postpaid",
            "to" => "2028-09-05T13:23:53Z",
            "creation_time" => "2017-07-01T04:00:00Z",
            "prorated_start" => true,
            "prorated_end" => true,
            "prorated_termination" => true
        ];
        $this->tester->generatePlan($plan);
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $service1 = [
            'from' => '2017-07-01T04:00:00Z',
            'name' => "SERVICE_1" . time(),
            "price" => [["price" => 100, "from" => 0, "to" => "UNLIMITED"]],
        ];
        $this->tester->generateService($service1);
        $service1 = json_decode($this->tester->grabResponse(), true)['entity'];
        $service1['from'] =  new Mongodloid_Date($service1['from']['sec']);
        $service1['to'] =  new Mongodloid_Date($service1['to']['sec']);
        $services[$service1['name']] = new Mongodloid_Entity($service1);

        $service2 = [
            'from' => '2017-07-01T04:00:00Z',
            'name' => "SERVICE_2" . time(),
            "price" => [["price" => 100, "from" => 0, "to" => "UNLIMITED"]],
        ];
        $this->tester->generateService($service2);
        $service2 = json_decode($this->tester->grabResponse(), true)['entity'];
        $service2['from'] =  new Mongodloid_Date($service2['from']['sec']);
        $service2['to'] =  new Mongodloid_Date($service2['to']['sec']);
        $services[$service2['name']] = new Mongodloid_Entity($service2);

        $service3 = [
            'from' => '2017-07-01T04:00:00Z',
            'name' => "SERVICE_3" . time(),
            "price" => [["price" => 100, "from" => 0, "to" => "UNLIMITED"]],
        ];
        $this->tester->generateService($service3);
        $service3 = json_decode($this->tester->grabResponse(), true)['entity'];
        $service3['from'] =  new Mongodloid_Date($service3['from']['sec']);
        $service3['to'] =  new Mongodloid_Date($service3['to']['sec']);
        $services[$service3['name']] = new Mongodloid_Entity($service3);        

        $this->tester->createAccountWithAllMandatoryCustomFields();
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->tester->generateSubscriber(
            [
                'aid' => $account['aid'],
                "from" => "2018-07-04T21:00:00Z",
                "plan" => $plan['name'],
                'services' => [
                        [
                            "name" => $service1['name'],
                            "from" => "2025-10-05T04:00:00Z",
                            "to" => "2025-10-21T04:00:00Z"
                        ],
                        [
                            "name" => $service2['name'],
                            "from" => "2025-10-05T04:00:00Z",
                            "to" => "2025-10-21T04:00:00Z"
                        ],
                        [
                            "name" => $service3['name'],
                            "from" => "2025-10-05T04:00:00Z",
                            "to" => "2025-10-21T04:00:00Z"
                        ]
                ],
            ]
        );
        $subscriber = json_decode($this->tester->grabResponse(), true)['entity'];
        $subRevisionsFields = ['services','plans'];
        foreach($subRevisionsFields as $fieldName) {
			if(empty($subscriber[$fieldName])) { continue; }
			foreach($subscriber[$fieldName] as &$subRev) {
                if(!empty($subRev['from']['sec'])) {
                    $subRev['from'] =  new Mongodloid_Date($subRev['from']['sec']);
                }
                if(!empty($subRev['to']['sec'])) {
                    $subRev['to'] =  new Mongodloid_Date($subRev['to']['sec']);
                }
                if(!empty($subRev['creation_time']['sec'])) {
                    $subRev['creation_time'] =  new Mongodloid_Date($subRev['creation_time']['sec']);
                }
            }
        }
        $subscriber['from'] =  $subscriber['from']['sec'];
        $subscriber['to'] =  $subscriber['to']['sec'];
        $stamp = "202511";
        $cycle = new Billrun_DataTypes_CycleTime($stamp);
        $subRevisions = Billrun_Cycle_Account::expandSubRevisions($subscriber, $cycle->start(),$cycle->end(), $services);
		$this->assertEquals(3, count($subRevisions));
        $this->assertEquals(0, count($subRevisions[0]['services']));
        $this->assertEquals(1759276800, $subRevisions[0]['from']->sec);//1.10

        $this->assertEquals(3, count($subRevisions[1]['services']));
        $this->assertEquals(1759636800, $subRevisions[1]['from']->sec);//5.10

        $this->assertEquals(0, count($subRevisions[2]['services']));
        $this->assertEquals(1761019200, $subRevisions[2]['from']->sec);//21.10
        
    } 
}
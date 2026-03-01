<?php

class expandSubRevisions_7Test extends \Codeception\Test\Unit
{
   
/**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
      $this->tester->cleanDB();
    }

    protected function _after()
    {
    }

    public function test_expandSubRevisions_7(){
        //All services are outside the cycle (after) – only one revision of the plan
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
        $service1 = $this->tester->generateService($service1);
        $services[$service1['name']] = new Mongodloid_Entity($service1);

        $service2 = [
            'from' => '2017-07-01T04:00:00Z',
            'name' => "SERVICE_2" . time(),
            "price" => [["price" => 100, "from" => 0, "to" => "UNLIMITED"]],
        ];
        $service2 = $this->tester->generateService($service2);
        $services[$service2['name']] = new Mongodloid_Entity($service2);

        $service3 = [
            'from' => '2017-07-01T04:00:00Z',
            'name' => "SERVICE_3" . time(),
            "price" => [["price" => 100, "from" => 0, "to" => "UNLIMITED"]],
        ];
        $service3 = $this->tester->generateService($service3);
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
                            "from" => "2026-10-05T04:00:00Z",
                            "to" => "2026-10-21T04:00:00Z"
                        ],
                        [
                            "name" => $service2['name'],
                            "from" => "2026-10-05T04:00:00Z",
                            "to" => "2026-10-21T04:00:00Z"
                        ],
                        [
                            "name" => $service3['name'],
                            "from" => "2026-10-05T04:00:00Z",
                            "to" => "2026-10-21T04:00:00Z"
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
		$this->assertEquals(1, count($subRevisions));
        $this->assertEquals(0, count($subRevisions[0]['services']));
        $this->assertEquals(1759276800, $subRevisions[0]['from']->sec);//1.10
        $this->assertEquals(1761955200, $subRevisions[0]['to']->sec);//1.10

        
    }
}
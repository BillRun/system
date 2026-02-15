<?php

class ArrearsTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected $epsilon = 0.00001;

    public $defaultOptions = array(
        "type" => "customer",
        "page" => 0,
        "size" => 100,
        'fetchonly' => true,
        'generate_pdf' => 0,
    );

    protected function _before()
    {
        ini_set('error_reporting', E_ALL & ~E_WARNING & ~E_NOTICE);
        $this->tester->enableExternalModeSettings();
        $this->tester->cleanDB();
    }

    protected function _after()
    {
    }


    

    public function testDiscountsArrearsPlan()
    {
        /*
        BRCD-5076 + 5080: arrears plan with two subscribers discounts not creates the second discount 
        */
        $aid =5100002472;
        $this->defaultOptions['stamp'] = '202512';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = 'PLAN_5076';
        $discount_name = 'DIS_PLAN_5076';
        $this->tester->generatePlan(['name' => $planName]);// charge on termination = true
        $this->tester->generateDiscount([
            "from" => "2025-08-01T21:00:00Z",
            "to" => "2025-11-06T05:00:00Z",
            "params" => [
              "conditions" => [
                      [
                          "subscriber" => [
                              [
                                  "fields" => [
                                      [
                                          "field" => "plan",
                                          "op" => "in",
                                          "value" => [$planName]
                                      ]
                                  ]
                              ]
                          ]
                      ]
              ]],
              "subject" => [
                  "plan" => [
                    $planName => ["value" => 20]
                  ]
              ],
              'key'=> $discount_name,

          ]);// charge on termination = true
        $planName = 'PLAN_5076';
        $this->tester->generatePlan(['name' => $planName]);// charge on termination = true
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->tester->runCycle($this->defaultOptions);
        // $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLine = $this->tester->grabFromCollection('lines', array('type' => "flat", "name"=> $planName, 'aid' => $aid));
        $discountLine1 = $this->tester->grabFromCollection('lines', array('type' => "credit", "usaget" => "discount", 'aid' => $aid, 'key'=>'SUBSCRIBER_DISCOUNT_1'));
        $discountLine2 = $this->tester->grabFromCollection('lines', array('type' => "credit", "usaget" => "discount", 'aid' => $aid, 'key'=>'SUBSCRIBER_DISCOUNT_2'));

  
        $this->assertEqualsWithDelta(22.403333333, $planLine['aprice'],$this->epsilon);
        $this->assertEqualsWithDelta(-11.204, $discountLine1['aprice'],$this->epsilon);
        $this->assertEqualsWithDelta(-2.801, $discountLine2['aprice'],$this->epsilon);
       
    }

    public function testDiscountsArrearsWithCycles_1()
    {
        /*
        BRCD-5102: cycles not work when proration: no
        */
        $aid =5100002243;
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = 'PLAN_5102_1';
        $this->tester->generatePlan(['name' => $planName]);
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->defaultOptions['stamp'] = '202510';
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $this->assertEqualsWithDelta((13.553333333), $billrun['totals']['before_vat'], $this->epsilon);

        $this->defaultOptions['stamp'] = '202511';
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $this->assertEqualsWithDelta((31.924), $billrun['totals']['before_vat'], $this->epsilon);

        $this->defaultOptions['stamp'] = '202512';
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $this->assertEqualsWithDelta((34.445), $billrun['totals']['before_vat'], $this->epsilon);

       
    }

    public function testDiscountsArrearsWithCycles_2()
    {
        /*
        BRCD-5102: cycles not work when proration:inherited with false
        */
        $aid =5100002244;
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = 'PLAN_5102';
        $this->tester->generatePlan(['name' => $planName, "prorated_termination" =>false, "prorated_start" =>false]);
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->defaultOptions['stamp'] = '202510';
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $this->assertEqualsWithDelta((31.924), $billrun['totals']['before_vat'], $this->epsilon);
        
        $this->defaultOptions['stamp'] = '202511';
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $this->assertEqualsWithDelta((31.924), $billrun['totals']['before_vat'], $this->epsilon);

        $this->defaultOptions['stamp'] = '202512';
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $this->assertEqualsWithDelta((34.445), $billrun['totals']['before_vat'], $this->epsilon);

       
    }

    public function testDiscountsArrearsWithCycles_3()
    {
        /*
        BRCD-5102: cycles not work when proration:inherited with "prorated_termination" =>true , "prorated_start" =>false
        */
        $aid =5100002245;
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = 'PLAN_5102_2';
        $this->tester->generatePlan(['name' => $planName, "prorated_termination" =>true , "prorated_start" =>false]);
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->defaultOptions['stamp'] = '202510';
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $this->assertEqualsWithDelta((31.924), $billrun['totals']['before_vat'], $this->epsilon);
        
        $this->defaultOptions['stamp'] = '202511';
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $this->assertEqualsWithDelta((31.924), $billrun['totals']['before_vat'], $this->epsilon);

        $this->defaultOptions['stamp'] = '202512';
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $this->assertEqualsWithDelta((34.445), $billrun['totals']['before_vat'], $this->epsilon);

       
    }

    public function testDiscountsArrearsWithCycles_4()
    {
        /*
        BRCD-5102: cycles not work when proration:inherited with "prorated_termination" =>false , "prorated_start" =>true - therothical test no practical use
        */
        $aid =5100002246;
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = 'PLAN_5102_3';
        $this->tester->generatePlan(['name' => $planName, "prorated_termination" =>false , "prorated_start" =>true]);
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->defaultOptions['stamp'] = '202510';
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $this->assertEqualsWithDelta((14.897866667), $billrun['totals']['before_vat'], $this->epsilon);
        
        $this->defaultOptions['stamp'] = '202511';
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $this->assertEqualsWithDelta((31.924), $billrun['totals']['before_vat'], $this->epsilon);

        $this->defaultOptions['stamp'] = '202512';
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $this->assertEqualsWithDelta((34.445), $billrun['totals']['before_vat'], $this->epsilon);
    }

    public function testDiscountsArrearsWithCycles_5()
    {
        /*
        BRCD-5102: cycles not work when proration:inherited with "prorated_termination" =>true , "prorated_start" =>true - therothical test no practical use
        */
        $aid =5100002247;
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = 'PLAN_5102_4';
        $this->tester->generatePlan(['name' => $planName]);
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->defaultOptions['stamp'] = '202510';
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $this->assertEqualsWithDelta((14.897866666666669), $billrun['totals']['before_vat'], $this->epsilon);
        
        $this->defaultOptions['stamp'] = '202511';
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $this->assertEqualsWithDelta((31.924), $billrun['totals']['before_vat'], $this->epsilon);

        $this->defaultOptions['stamp'] = '202512';
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $this->assertEqualsWithDelta((33.016433333), $billrun['totals']['before_vat'], $this->epsilon);

       
    }



    // BRCD-5156 Discount elgibilty is empty for subscribers with revisions out side of the discount span
    public function testDiscountsOutsideOfRevisionData()
    {
   
        $aid =5156;
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = '201000003';
        $this->tester->generatePlan(['name' => $planName, "price" => [
                [
                    "price" => 100,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ]]);

        //partial month - proration discount 
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->defaultOptions['stamp'] = '202602';
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $this->assertEqualsWithDelta((37.096774193548384), $billrun['totals']['before_vat'], $this->epsilon);
        //full month  - full discount
        $this->defaultOptions['stamp'] = '202603';
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $this->assertEqualsWithDelta((50), $billrun['totals']['before_vat'], $this->epsilon);


    }
}
<?php

class UpfrontTest extends \Codeception\Test\Unit
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
        $this->cleanDB();
    }

    protected function _after()
    {
    }
    
    protected function cleanDB(){

        $plans = Billrun_Factory::db()->plansCollection();
        $plans->remove(['_id'=>['$exists' => true]]);
        $lines = Billrun_Factory::db()->linesCollection();
        $lines->remove(['_id'=>['$exists' => true]]);
        $billruns = Billrun_Factory::db()->billrunCollection();
        $billruns->remove(['_id'=>['$exists' => true]]);
        $billing_cycleCollection = Billrun_Factory::db()->billing_cycleCollection();
        $billing_cycleCollection->remove(['_id'=>['$exists' => true]]);

    }

    public function testDiscountFinishPreviousMonthOnUpfronInheritedPlan_1()
    {
        /*
        upfront plan  discount with "proration": "inherited" and plan not finish
        but discount finish in the previous month  (for both Prorate charge on termination = false /true)
        -> expected proration charge from the termination of the discount + not discount on the current cycle 0
        */
        $aid =5100002408;
        $this->defaultOptions['stamp'] = '202601';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = 'B2C_5GUNLIMITEDMAX_PP_INADV';
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1]);//Prorate charge on termination = true
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLine = $this->tester->grabFromCollection('lines', array('type' => "flat", "name"=> $planName, 'aid' => $aid));
        $discountLine = $this->tester->grabFromCollection('lines', array('type' => "credit", "usaget" => "discount", 'aid' => $aid));

        //flat-33.605 discount(+4.337032258)(finish in 2025-12-23 10:04:25) - 8/31*16.806 
        $this->assertEqualsWithDelta(37.942032258, $billrun['totals']['before_vat'],$this->epsilon);
        $this->assertEquals(strtotime("2026-01-01T00:00:00Z"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2026-02-01T00:00:00Z"), $planLine['end']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-23T10:04:25Z"), $discountLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2026-02-01T00:00:00Z"), $discountLine['end']->toDateTime()->getTimestamp());
    }

    public function testDiscountFinishPreviousMonthOnUpfronInheritedPlan_2()
    {
        /*
        upfront plan  discount with "proration": "inherited" and plan not finish
        but discount finish in the previous month  (for both Prorate charge on termination = false /true)
        -> expected proration charge from the termination of the discount + not discount on the current cycle 0
        */
        $aid =5100002413;
        $this->defaultOptions['stamp'] = '202601';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = 'B2C_5GUNLIMITEDMAX_PP_INADV_1';
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1, "prorated_termination" =>false]);//Prorate charge on termination = false
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLine = $this->tester->grabFromCollection('lines', array('type' => "flat", "name"=> $planName, 'aid' => $aid));
        $discountLine = $this->tester->grabFromCollection('lines', array('type' => "credit", "usaget" => "discount", 'aid' => $aid));

        //flat-33.605 discount(+4.337032258)(finish in 2025-12-23 10:04:25) - 8/31*16.806 
        $this->assertEqualsWithDelta(37.942032258, $billrun['totals']['before_vat'],$this->epsilon);
        $this->assertEquals(strtotime("2026-01-01T00:00:00Z"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2026-02-01T00:00:00Z"), $planLine['end']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-23T10:04:25Z"), $discountLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2026-02-01T00:00:00Z"), $discountLine['end']->toDateTime()->getTimestamp());
    }
    
    // public function testDiscountOfPlanFinishPreviousMonthOnUpfronInheritedPlan_1()
    // {
    //     /*
    //     upfront plan  discount with "proration": "inherited" and plan finish in the previous month
    //     but discount not finish  + Prorate charge on termination = false 
    //     -> expected not proration credit on from the termination of the plan + proration charge on from the termination of the discount
    //     */
    //     $aid =5100002414;
    //     $this->defaultOptions['stamp'] = '202601';
    //     $this->defaultOptions['force_accounts'] = [$aid];
    //     $this->tester->generatePlan(['name' => 'B2C_5GUNLIMITEDMAX_PP_INADV_1', "upfront" => 1, "prorated_termination" =>false]);//Prorate charge on termination = false
    //     $this->tester->runCycle($this->defaultOptions);
    //     $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
    //     //flat-(plan prorated_termination =false not need to be credit) + discount(+4.87916129)(finish in 2025-12-23 10:04:25) - 9/31*16.806 
    //     $this->assertEqualsWithDelta(4.87916129, $billrun['totals']['before_vat'],$this->epsilon);
    // }

    // public function testDiscountOfPlanFinishPreviousMonthOnUpfronInheritedPlan_2()
    // {
    //     /*
    //     upfront plan  discount with "proration": "inherited" and plan finish in the previous month
    //     but discount not finish  + Prorate charge on termination = true 
    //     -> expected proration credit on from the termination of the plan + proration charge on from the termination of the discount
    //     */
    //     $aid =5100002409;
    //     $this->defaultOptions['stamp'] = '202601';
    //     $this->defaultOptions['force_accounts'] = [$aid];
    //     $this->tester->generatePlan(['name' => 'B2C_5GUNLIMITEDMAX_PP_INADV', "upfront" => 1]);//Prorate charge on termination = true
    //     $this->tester->runCycle($this->defaultOptions);
    //     $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
    //     //flat-(-13.008387097)(plan prorated_termination =true  need to be credit) + discount(+6.505548387)(finish in 2025-12-20 10:04:25) - 12/31*16.806 
    //     $this->assertEqualsWithDelta((-6.50283871), $billrun['totals']['before_vat'],$this->epsilon);
    // } 

    // public function testDiscountOfPlanFinishPreviousMonthOnUpfronInheritedPlan_3()
    // {
    //     /*
    //     upfront plan  discount with "proration": "inherited" and plan finish in the previous month
    //     but discount not finish  + Prorate charge on termination = false 
    //     -> expected not proration charge on from the termination of the plan + not discount on the current cycle 
    //     */
    //     $aid =5100002415;
    //     $this->defaultOptions['stamp'] = '202601';
    //     $this->defaultOptions['force_accounts'] = [$aid];
    //     $this->tester->generatePlan(['name' => 'B2C_5GUNLIMITEDMAX_PP_INADV_1', "upfront" => 1, "prorated_termination" =>false]);//Prorate charge on termination = false
    //     $this->tester->runCycle($this->defaultOptions);
    //     $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
    //     //flat-(plan prorated_termination =false not need to be credit)
    //     $this->assertEqualsWithDelta(0, $billrun['totals']['before_vat'],$this->epsilon);
    // }

    // public function testDiscountOfPlanFinishPreviousMonthOnUpfronInheritedPlan_4()
    // {
    //     /*
    //     upfront plan  discount with "proration": "inherited" and plan finish in the previous month
    //     but discount not finish  + Prorate charge on termination = true 
    //     -> expected not proration charge on from the termination of the plan + not discount on the current cycle 
    //     */
    //     $aid =5100002410;
    //     $this->defaultOptions['stamp'] = '202601';
    //     $this->defaultOptions['force_accounts'] = [$aid];
    //     $this->tester->generatePlan(['name' => 'B2C_5GUNLIMITEDMAX_PP_INADV', "upfront" => 1]);//Prorate charge on termination = true
    //     $this->tester->runCycle($this->defaultOptions);
    //     $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
    //     //flat-(-13.008387097)(plan prorated_termination =true  need to be credit) + discount(+6.505548387)(finish in 2025-12-20 10:04:25) - 12/31*16.806 
    //     $this->assertEqualsWithDelta((-6.50283871), $billrun['totals']['before_vat'],$this->epsilon);
    // } 


    public function testDiscountStartMiddleMonthOnUpfronInheritedPlan_1()
    {
        /*
        upfront plan  discount with "proration": "inherited" and plan start previous month
        and discount start in the middle of previous month,  prorate start = true- > 
        expected proration discount from the start of the discount +  discount on the current cycle (assume still not finish- need to support also finish before case)
        */
        $aid =5100002408;
        $this->defaultOptions['stamp'] = '202511';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = 'B2C_5GUNLIMITEDMAX_PP_INADV';
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1]);//Prorate start = true
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLine = $this->tester->grabFromCollection('lines', array('type' => "flat", "name"=> $planName, 'aid' => $aid));
        $discountLine = $this->tester->grabFromCollection('lines', array('type' => "credit", "usaget" => "discount", 'aid' => $aid));
        //flat-42.566333333(9.756290323+33.605), discount(-16.806 +(-4.87916129))(start in in 2025-10-23 10:04:25) 9/30*16.806
        $this->assertEqualsWithDelta(21.676129033, $billrun['totals']['before_vat'],$this->epsilon);
        $this->assertEquals(strtotime("2025-10-23T13:04:25Z"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-01T00:00:00Z"), $planLine['end']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-10-23T10:04:25Z"), $discountLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-01T00:00:00Z"), $discountLine['end']->toDateTime()->getTimestamp());
    }
    

    public function testDiscountStartMiddleMonthOnUpfronInheritedPlan_2()
    {
        /*
        upfront plan  discount with "proration": "inherited" and plan start before previous month
        but discount start in the middle of previous month,  prorate start = false- > 
        expected proration discount from the start of the discount +  discount on the current cycle (assume still not finish- need to support also finish before case)
        */
        $aid =5100002418;
        $this->defaultOptions['stamp'] = '202511';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = 'B2C_5GUNLIMITEDMAX_PP_INADV_2';
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1, "prorated_start" =>false]);//Prorate start = false
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLine = $this->tester->grabFromCollection('lines', array('type' => "flat", "name"=> $planName, 'aid' => $aid));
        $discountLine = $this->tester->grabFromCollection('lines', array('type' => "credit", "usaget" => "discount", 'aid' => $aid));
        //flat-67.21(33.605+33.605), discount(-16.806 +(-4.87916129))(start in in 2025-10-23 10:04:25) 9/30*16.806
        $this->assertEqualsWithDelta(45.52483871, $billrun['totals']['before_vat'],$this->epsilon);
        $this->assertEquals(strtotime("2025-11-01T00:00:00Z"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-01T00:00:00Z"), $planLine['end']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-10-23T10:04:25Z"), $discountLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-01T00:00:00Z"), $discountLine['end']->toDateTime()->getTimestamp());
    }

    public function testDiscountOfUpfronInheritedPlanStartMiddleMonth_1()
    {
        /*
        upfront plan  discount with "proration": "inherited" and plan start in the middle of previous month
        and discount start before plan + Prorate start = true -> 
        expected discount from the max(start of the previous cycle, discount start) +  discount on the current cycle (assume still not finish)
        */
        $aid =5100002411;
        $this->defaultOptions['stamp'] = '202511';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = 'B2C_5GUNLIMITEDMAX_PP_INADV';
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1]);//Prorate start = true
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLine = $this->tester->grabFromCollection('lines', array('type' => "flat", "name"=> $planName, 'aid' => $aid));
        $discountLine = $this->tester->grabFromCollection('lines', array('type' => "credit", "usaget" => "discount", 'aid' => $aid));
        //flat-42.566333333(9.756290323+33.605), discount(-16.806 +(-4.87916129)) 9/30*16.806
        $this->assertEqualsWithDelta(21.676129033, $billrun['totals']['before_vat'],$this->epsilon);
        $this->assertEquals(strtotime("2025-10-23T13:04:25Z"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-01T00:00:00Z"), $planLine['end']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-10-23T13:04:25Z"), $discountLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-01T00:00:00Z"), $discountLine['end']->toDateTime()->getTimestamp());
    }
    

    public function testDiscountOfUpfronInheritedPlanStartMiddleMonth_2()
    {
        /*
        upfront plan  discount with "proration": "inherited" and plan start in the middle of previous month
        and discount start before plan + Prorate start = false -> 
        expected discount from the max(start of the previous cycle, discount start) +  discount on the current cycle (assume still not finish)
        */
        $aid =5100002416;
        $this->defaultOptions['stamp'] = '202511';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = 'B2C_5GUNLIMITEDMAX_PP_INADV_2';
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1, "prorated_start" =>false]);//Prorate start = false
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLine = $this->tester->grabFromCollection('lines', array('type' => "flat", "name"=> $planName, 'aid' => $aid));
        $discountLine = $this->tester->grabFromCollection('lines', array('type' => "credit", "usaget" => "discount", 'aid' => $aid));
        //flat-67.21(33.605+33.605), discount(-16.806 -16.806)
        $this->assertEqualsWithDelta(33.598, $billrun['totals']['before_vat'],$this->epsilon);
        $this->assertEqualsWithDelta(21.676129033, $billrun['totals']['before_vat'],$this->epsilon);
        $this->assertEquals(strtotime("2025-10-01T00:00:00Z"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-01T00:00:00Z"), $planLine['end']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-10-01T00:00:00Z"), $discountLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-01T00:00:00Z"), $discountLine['end']->toDateTime()->getTimestamp());
    }

    public function testDiscountStartMiddleMonthAndFinishMiddleMonth_1()
    {
        /*
        upfront plan  discount with "proration": "inherited" and plan start before previous month (prorated= true)
        but discount start in the middle of previous month , and also finish in the middle of month  
        -> expected proration discount from the start+ end of the discount +  not discount on the current cycle
        */
        $aid =5100002412;
        $this->defaultOptions['stamp'] = '202512';
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => 'B2C_5GUNLIMITEDMAX_PP_INADV', "upfront" => 1]);//Prorate  = true
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        //flat-33.605, discount(-6.7224)13/30*16.806
        $this->assertEqualsWithDelta(26.3224, $billrun['totals']['before_vat'],$this->epsilon);
    }

    public function testDiscountStartMiddleMonthAndFinishMiddleMonth_2()
    {
        /*
        upfront plan  discount with "proration": "inherited"  and plan start before previous month (prorated= false)
        but discount start in the middle of previous month , and also finish in the middle of month  
        -> expected proration discount from the start+ end of the discount +  not discount on the current cycle
        */
        $aid =5100002417;
        $this->defaultOptions['stamp'] = '202512';
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => 'B2C_5GUNLIMITEDMAX_PP_INADV_3', "upfront" => 1, "prorated_start" =>false , "prorated_termination" =>false]);//Prorate  = false 
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        //flat-33.605, discount(-6.7224)
        $this->assertEqualsWithDelta(26.3224, $billrun['totals']['before_vat'],$this->epsilon);
    }

    public function testDiscountFinishPreviousMonthOnUpfronNoInheritedPlan_1()
    {
        /*
        upfront plan  discount with "proration": "no" and plan not finish
        but discount finish in the previous month 
        -> expected not proration charge from the termination of the discount + not discount on the current cycle 
        */
        $aid =5100002419;
        $this->defaultOptions['stamp'] = '202601';
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => 'B2C_5GUNLIMITEDMAX_PP_INADV', "upfront" => 1]);// charge on termination = true
        $this->tester->runCycle($this->defaultOptions);
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        //flat-33.605, discount(0)
        $this->assertEqualsWithDelta(33.605, $billrun['totals']['before_vat'],$this->epsilon);
    }

    public function testDiscountOnUpfronNoInheritedPlanFinishPreviousMonth_1()
    {
        /*
        upfront plan  discount with "proration": "no" and plan finish in the previous month
        but discount not finish -> 
        expected not proration charge on from the termination of the plan + not discount on the current cycle 
        */
        $aid =5100002420;
        $this->defaultOptions['stamp'] = '202601';
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => 'B2C_5GUNLIMITEDMAX_PP_INADV', "upfront" => 1]);// charge on termination = true
        $this->tester->runCycle($this->defaultOptions);
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        //flat-0, discount(0)
        $this->assertEqualsWithDelta(0, $billrun['totals']['before_vat'],$this->epsilon);
    }

    public function testDiscountOnUpfronNoInherited()
    {
        /*
        upfront plan  discount with "proration": "no" and plan not finish in the previous month
        and also discount not finish
        expected -> discount on the current cycle 
        */
        $aid =5100002421;
        $this->defaultOptions['stamp'] = '202601';
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => 'B2C_5GUNLIMITEDMAX_PP_INADV', "upfront" => 1]);// charge on termination = true
        $this->tester->runCycle($this->defaultOptions);
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        //flat-33.605 discount(-16.806) 
        $this->assertEqualsWithDelta(16.799, $billrun['totals']['before_vat'],$this->epsilon);
    }
}
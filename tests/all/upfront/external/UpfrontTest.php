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
        $this->tester->cleanDB();
        $this->tester->setTimezone('UTC');
        $this->tester->enableExternalModeSettings();
        \Billrun_Plans_Charge_Upfront::resetReconciliationCache();
    }

    protected function _after()
    {
        $this->tester->restoreTimezone();
        $this->tester->enableDBModeSettings();
    }

    /**
     * BRCD-5421 - the upfront charges are reconciled against the previous cycle run lines. the
     * legacy expectations assume the previous run did NOT know the changes in advance - so it runs
     * with the full fraction (legacy) upfront behavior, and the tested cycle runs with the default
     * (knowing the changes in advance) behavior.
     */
    protected function runCycleWithPrevious($options)
    {
        $previousOptions = $options;
        $previousOptions['stamp'] = \Billrun_Billingcycle::getPreviousBillrunKey($options['stamp']);
        \Billrun_Factory::config()->setConfigValue('billrun.upfront.full_fraction', true);
        try {
            $this->tester->runCycle($previousOptions);
        } finally {
            \Billrun_Factory::config()->setConfigValue('billrun.upfront.full_fraction', false);
        }
        $this->tester->runCycle($options);
    }
    

    public function testDiscountFinishPreviousMonthOnUpfronInheritedPlan_1()
    {
        /*
        upfront plan  discount with "proration": "inherited" and plan not finish
        but discount finish in the previous month  (for both Prorate charge on termination = false /true)
        -> expected proration charge from the termination of the discount + not discount on the current cycle 0
        */
        $aid = 12408;
        $this->defaultOptions['stamp'] = '202601';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = "UPFRONT_PLAN_PORATED";
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1]);//Prorate charge on termination = true
        $this->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid));
        $discountLineUpfront = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid, 'charge_op' => 'refund'));
        //flat-33.605 discount(+4.337032258)(finish in 2025-12-23 10:04:25) - 8/31*16.806 
        $this->assertEqualsWithDelta(37.942032258, $billrun['totals']['before_vat'],$this->epsilon);
        $this->assertEquals(strtotime("2026-01-01 00:00:00"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2026-02-01 00:00:00"), $planLine['end']->toDateTime()->getTimestamp());
        
        $this->assertEquals(strtotime("2025-12-23 10:04:25"), $discountLineUpfront['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2026-01-01 00:00:00"), $discountLineUpfront['discount_end']->toDateTime()->getTimestamp());
    }

    public function testDiscountFinishPreviousMonthOnUpfronInheritedPlan_2()
    {
        /*
        upfront plan  discount with "proration": "yes" and plan not finish
        but discount finish in the previous month  (for both Prorate charge on termination = false /true)
        -> expected proration charge from the termination of the discount + not discount on the current cycle 0
        */
        $aid = 12425;
        $this->defaultOptions['stamp'] = '202601';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = "UPFRONT_PLAN_PORATED_TERMINATION_FALSE";
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1, "prorated_termination" =>false]);//Prorate charge on termination = false
        $this->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid));
        $discountLineUpfront = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid, 'charge_op' => 'refund'));

        //flat-33.605 discount(+4.337032258)(finish in 2025-12-23 10:04:25) - 8/31*16.806 
        $this->assertEqualsWithDelta(37.942032258, $billrun['totals']['before_vat'],$this->epsilon);
        $this->assertEquals(strtotime("2026-01-01 00:00:00"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2026-02-01 00:00:00"), $planLine['end']->toDateTime()->getTimestamp());
        
        $this->assertEquals(strtotime("2025-12-23 10:04:25"), $discountLineUpfront['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2026-01-01 00:00:00"), $discountLineUpfront['discount_end']->toDateTime()->getTimestamp());

    }

    public function testDiscountFinishPreviousMonthOnUpfronInheritedPlan_3()
    {
        /*
        upfront plan  discount with "proration": "inherited" and plan not finish
        but discount finish in the previous month  (for both Prorate charge on termination = false /true)
        -> expected not proration charge from the termination of the discount + not discount on the current cycle 0
        */
        $aid = 12413;
        $this->defaultOptions['stamp'] = '202601';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = "UPFRONT_PLAN_PORATED_TERMINATION_FALSE";
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1, "prorated_termination" =>false]);//Prorate charge on termination = false
        $this->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid));
        $discountLineUpfront = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid, 'charge_op' => 'refund'));

        //flat-33.605  
        $this->assertEqualsWithDelta(33.605, $billrun['totals']['before_vat'],$this->epsilon);
        $this->assertEquals(strtotime("2026-01-01 00:00:00"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2026-02-01 00:00:00"), $planLine['end']->toDateTime()->getTimestamp());
        $this->assertEquals(null, $discountLineUpfront);

    }
    
    public function testDiscountOfPlanFinishPreviousMonthOnUpfronInheritedPlan_1()
    {
        /*
        upfront plan  discount with "proration": "inherited" and plan finish in the previous month
        but discount not finish  + Prorate charge on termination = false 
        -> expected not proration credit on from the termination of the plan + proration charge on from the termination of the discount
        */
        $aid = 12414;
        $this->defaultOptions['stamp'] = '202601';
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => "UPFRONT_PLAN_PORATED_TERMINATION_FALSE", "upfront" => 1, "prorated_termination" =>false]);//Prorate charge on termination = false
        $this->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        //flat-(plan prorated_termination =false not need to be credit) + discount("proration": "inherited"  + prorated_termination =false not need to be credit) 
        $this->assertEquals($billrun, null);
    }

    public function testDiscountOfPlanFinishPreviousMonthOnUpfronInheritedPlan_2()
    {
        /*
        upfront plan  discount with "proration": "inherited" and plan finish in the previous month
        but discount not finish  + Prorate charge on termination = true 
        -> expected proration credit on from the termination of the plan + proration charge on from the termination of the discount
        */
        $aid = 12422;
        $this->defaultOptions['stamp'] = '202512';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = "UPFRONT_PLAN_PORATED";
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1]);//Prorate charge on termination = true
        $this->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid));
        $discountLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid));
        //flat-(17.922 = 33.605- 14/30*33.605)(plan prorated_termination =true  need to be credit) + discount(16.806-14/30*16.806 = 8.9632)(finish in 2025-11-15 00:00:00) - 14/30*16.806 
        $this->assertEqualsWithDelta((-8.959466667), $billrun['totals']['before_vat'], $this->epsilon);
        $this->assertEquals(strtotime("2025-11-15 00:00:00"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-11-15 00:00:00"), $discountLine['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $discountLine['discount_end']->toDateTime()->getTimestamp());
    }


    public function testDiscountStartMiddleMonthOnUpfronInheritedPlan_1()
    {
        /*
        upfront plan  discount with "proration": "inherited" and plan start previous month
        and discount start in the middle of previous month,  prorate start = true- > 
        expected proration discount from the start of the discount +  discount on the current cycle (assume still not finish- need to support also finish before case)
        */
        $aid = 12408;
        $this->defaultOptions['stamp'] = '202511';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = "UPFRONT_PLAN_PORATED";
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1]);//Prorate start = true
        $this->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLineUpfront = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid, 'is_upfront' => true));
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid, 'is_upfront' => false));
        $discountLineUpfront = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid, 'is_upfront' => true));
        $discountLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid, 'is_upfront' => false));

        //flat-42.566333333(9.756290323+33.605), discount(-16.806 +(-4.87916129))(start in in 2025-10-23 10:04:25) 9/30*16.806
        $this->assertEqualsWithDelta(21.676129033, $billrun['totals']['before_vat'],$this->epsilon);
        $this->assertEquals(strtotime("2025-10-23 13:04:25"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(null, $planLine['end']);
        $this->assertEquals(strtotime("2025-11-01 00:00:00"), $planLineUpfront['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $planLineUpfront['end']->toDateTime()->getTimestamp());

        $this->assertEquals(strtotime("2025-10-23 13:04:25"), $discountLine['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-11-01 00:00:00"), $discountLine['discount_end']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-11-01 00:00:00"), $discountLineUpfront['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $discountLineUpfront['discount_end']->toDateTime()->getTimestamp());
    }
    

    public function testDiscountStartMiddleMonthOnUpfronInheritedPlan_2()
    {
        /*
        upfront plan  discount with "proration": "yes" and plan start before previous month
        but discount start in the middle of previous month,  prorate start = false- > 
        expected proration discount from the start of the discount +  discount on the current cycle (assume still not finish- need to support also finish before case)
        */
        $aid = 12426;
        $this->defaultOptions['stamp'] = '202511';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = 'UPFRONT_PLAN_PORATED_START_FALSE';
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1, "prorated_start" =>false]);//Prorate start = false
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLineUpfront = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid, 'is_upfront' => true));
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid, 'is_upfront' => false));
        $discountLineUpfront = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid, 'is_upfront' => true));
        $discountLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid, 'is_upfront' => false));
        //flat-67.21(33.605+33.605), discount(-16.806 +(-4.87916129))(start in in 2025-10-23 10:04:25) 9/30*16.806
        $this->assertEqualsWithDelta(45.52483871, $billrun['totals']['before_vat'],$this->epsilon);
        $this->assertEquals(null, $planLine['start']);
        $this->assertEquals(null, $planLine['end']);
        $this->assertEquals(strtotime("2025-11-01 00:00:00"), $planLineUpfront['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $planLineUpfront['end']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-10-23 13:04:25"), $discountLine['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-11-01 00:00:00"), $discountLine['discount_end']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-11-01 00:00:00"), $discountLineUpfront['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $discountLineUpfront['discount_end']->toDateTime()->getTimestamp());

    }

    public function testDiscountStartMiddleMonthOnUpfronInheritedPlan_3()
    {
        /*
        upfront plan  discount with "proration": "inherited" and plan start before previous month
        but discount start in the middle of previous month,  prorate start = false- > 
        expected proration discount from the start of the discount +  discount on the current cycle (assume still not finish- need to support also finish before case)
        */
        $aid = 12418;
        $this->defaultOptions['stamp'] = '202511';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = 'UPFRONT_PLAN_PORATED_START_FALSE';
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1, "prorated_start" =>false]);//Prorate start = false
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLineUpfront = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid, 'is_upfront' => true));
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid, 'is_upfront' => false));
        $discountLineUpfront = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid, 'is_upfront' => true));
        $discountLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid, 'is_upfront' => false));
        //flat-67.21(33.605+33.605), discount(-16.806 +(-16.806))(start in in 2025-10-23 10:04:25) 9/30*16.806
        $this->assertEqualsWithDelta(33.598, $billrun['totals']['before_vat'],$this->epsilon);
        $this->assertEquals(null, $planLine['start']);
        $this->assertEquals(null, $planLine['end']);
        $this->assertEquals(strtotime("2025-11-01 00:00:00"), $planLineUpfront['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $planLineUpfront['end']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-10-01 00:00:00"), $discountLine['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-11-01 00:00:00"), $discountLine['discount_end']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-11-01 00:00:00"), $discountLineUpfront['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $discountLineUpfront['discount_end']->toDateTime()->getTimestamp());

    }

    public function testDiscountOfUpfronInheritedPlanStartMiddleMonth_1()
    {
        /*
        upfront plan  discount with "proration": "inherited" and plan start in the middle of previous month
        and discount start before plan + Prorate start = true -> 
        expected discount from the max(start of the previous cycle, discount start) +  discount on the current cycle (assume still not finish)
        */
        $aid = 12411;
        $this->defaultOptions['stamp'] = '202511';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = "UPFRONT_PLAN_PORATED";
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1]);//Prorate start = true
        $this->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLineUpfront = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid, 'is_upfront' => true));
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid, 'is_upfront' => false));
        $discountLineUpfront = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid, 'is_upfront' => true));
        $discountLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid, 'is_upfront' => false));
        //flat-42.566333333(9.756290323+33.605), discount(-16.806 +(-4.87916129)) 9/30*16.806
        $this->assertEqualsWithDelta(21.676129033, $billrun['totals']['before_vat'],$this->epsilon);
        $this->assertEquals(strtotime("2025-10-23 13:04:25"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(null, $planLine['end']);
        $this->assertEquals(strtotime("2025-11-01 00:00:00"), $planLineUpfront['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $planLineUpfront['end']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-10-23 13:04:25"), $discountLine['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-11-01 00:00:00"), $discountLine['discount_end']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-11-01 00:00:00"), $discountLineUpfront['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $discountLineUpfront['discount_end']->toDateTime()->getTimestamp());
    }
    

    public function testDiscountOfUpfronInheritedPlanStartMiddleMonth_2()
    {
        /*
        upfront plan  discount with "proration": "inherited" and plan start in the middle of previous month
        and discount start before plan + Prorate start = false -> 
        expected discount from the max(start of the previous cycle, discount start) +  discount on the current cycle (assume still not finish)
        */
        $aid = 12416;
        $this->defaultOptions['stamp'] = '202511';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = 'UPFRONT_PLAN_PORATED_START_FALSE';
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1, "prorated_start" =>false]);//Prorate start = false
        $this->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLineUpfront = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid, 'is_upfront' => true));
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid, 'is_upfront' => false));
        $discountLineUpfront = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid, 'is_upfront' => true));
        $discountLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid, 'is_upfront' => false));
        //flat-67.21(33.605+33.605), discount(-16.806 -16.806)
        $this->assertEqualsWithDelta(33.598, $billrun['totals']['before_vat'],$this->epsilon);
        $this->assertEquals(null, $planLine['start']);
        $this->assertEquals(null, $planLine['end']);
        $this->assertEquals(strtotime("2025-11-01 00:00:00"), $planLineUpfront['start']->toDateTime()->getTimestamp());

        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $planLineUpfront['end']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-10-01 00:00:00"), $discountLine['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-11-01 00:00:00"), $discountLine['discount_end']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-11-01 00:00:00"), $discountLineUpfront['discount_start']->toDateTime()->getTimestamp());

        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $discountLineUpfront['discount_end']->toDateTime()->getTimestamp());
    }

    public function testDiscountStartMiddleMonthAndFinishMiddleMonth_1()
    {
        /*
        upfront plan  discount with "proration": "inherited" and plan start before previous month (prorated= true)
        but discount start in the middle of previous month , and also finish in the middle of month  
        -> expected proration discount from the start+ end of the discount +  not discount on the current cycle
        */
        $aid = 12412;
        $this->defaultOptions['stamp'] = '202512';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = "UPFRONT_PLAN_PORATED";
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1]);//Prorate  = true
        $this->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid));
        $discountLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid));
        //flat-33.605, discount(-6.7224)13/30*16.806
        $this->assertEqualsWithDelta(26.3224, $billrun['totals']['before_vat'],$this->epsilon);
        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2026-01-01 00:00:00"), $planLine['end']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-11-10 10:04:25"), $discountLine['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-11-23 10:04:25"), $discountLine['discount_end']->toDateTime()->getTimestamp());
    }

    public function testDiscountStartMiddleMonthAndFinishMiddleMonth_2()
    {
        /*
        upfront plan  discount with "proration": "yes"  and plan start before previous month (prorated= false)
        but discount start in the middle of previous month , and also finish in the middle of month  
        -> expected proration discount from the start+ end of the discount +  not discount on the current cycle
        */
        $aid = 12427;
        $this->defaultOptions['stamp'] = '202512';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = 'UPFRONT_PLAN_PORATED_FALSE';
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1, "prorated_start" =>false , "prorated_termination" =>false]);//Prorate  = false 
        $this->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid));
        $discountLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid));
        //flat-33.605, discount(-6.7224)
        $this->assertEqualsWithDelta(26.3224, $billrun['totals']['before_vat'],$this->epsilon);
        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2026-01-01 00:00:00"), $planLine['end']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-11-10 10:04:25"), $discountLine['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-11-23 10:04:25"), $discountLine['discount_end']->toDateTime()->getTimestamp());

    }
    public function testDiscountStartMiddleMonthAndFinishMiddleMonth_3()
    {
        /*
        upfront plan  discount with "proration": "inherited"  and plan start before previous month (prorated= false)
        but discount start in the middle of previous month , and also finish in the middle of month  
        -> expected proration discount from the start+ end of the discount +  not discount on the current cycle
        */
        $aid = 12417;
        $this->defaultOptions['stamp'] = '202512';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = 'UPFRONT_PLAN_PORATED_FALSE';
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1, "prorated_start" =>false , "prorated_termination" =>false]);//Prorate  = false 
        $this->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid));
        $discountLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid));
        //flat-33.605, discount(-16.806)
        $this->assertEqualsWithDelta(16.799, $billrun['totals']['before_vat'],$this->epsilon);
        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2026-01-01 00:00:00"), $planLine['end']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-11-01 00:00:00"), $discountLine['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $discountLine['discount_end']->toDateTime()->getTimestamp());

    }

    public function testDiscountFinishPreviousMonthOnUpfronNoInheritedPlan_1()
    {
        /*
        upfront plan  discount with "proration": "no" and plan not finish
        but discount finish in the previous month 
        -> expected not proration charge from the termination of the discount + not discount on the current cycle 
        */
        $aid = 12419;
        $this->defaultOptions['stamp'] = '202601';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = "UPFRONT_PLAN_PORATED";
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1]);// charge on termination = true
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid));
        $discountLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid));
        //flat-33.605, discount(0)
        $this->assertEqualsWithDelta(33.605, $billrun['totals']['before_vat'],$this->epsilon);
        $this->assertEquals(strtotime("2026-01-01 00:00:00"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2026-02-01 00:00:00"), $planLine['end']->toDateTime()->getTimestamp());
        $this->assertEquals($discountLine, null);
    }

    public function testDiscountOnUpfronNoInheritedPlanFinishPreviousMonth_1()
    {
        /*
        upfront plan  discount with "proration": "no" and plan finish in the previous month
        but discount not finish -> 
        expected not proration charge on from the termination of the plan + not discount on the current cycle 
        */
        $aid = 12420;
        $this->defaultOptions['stamp'] = '202601';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = "UPFRONT_PLAN_PORATED";
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1]);// charge on termination = true
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid));
        $discountLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid));
        //flat-8.672258064516129, discount("proration": "no" -no need to credit )
        $this->assertEqualsWithDelta(-8.672258064516129, $billrun['totals']['before_vat'],$this->epsilon);
        $this->assertEquals(strtotime("2025-12-23 10:04:25"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2026-01-01 00:00:00"), $planLine['end']->toDateTime()->getTimestamp());
        $this->assertEquals($discountLine, null);

    }

    public function testDiscountOnUpfronNoInherited_1()
    {
        /*
        upfront plan  discount with "proration": "no" and plan not finish in the previous month
        and also discount not finish
        expected -> discount on the current cycle 
        */
        $aid = 12421;
        $this->defaultOptions['stamp'] = '202601';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = "UPFRONT_PLAN_PORATED";
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1]);// charge on termination = true
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid));
        $discountLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid));
        //flat-33.605 discount(-16.806) 
        $this->assertEqualsWithDelta(16.799, $billrun['totals']['before_vat'],$this->epsilon);
        $this->assertEquals(strtotime("2026-01-01 00:00:00"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2026-02-01 00:00:00"), $planLine['end']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2026-01-01 00:00:00"), $discountLine['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2026-02-01 00:00:00"), $discountLine['discount_end']->toDateTime()->getTimestamp());
    }

    public function testChangeSubFromArrearsPlanToUpfrontPlan()
    {
        /*
        Change Subscriber From Arrears Plan To Upfront Plan
        transfer from arrears to upfront in "2025-10-29 17:38:59"
        */
        $aid = 11279;
        $this->defaultOptions['stamp'] = '202511';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = 'B2C_UPFRONT';
        $planNameArrears= 'B2C_ARREARS';
        $this->tester->generatePlan(['name' => $planNameArrears]);// charge on termination = true
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1]);// charge on termination = true
        $this->runCycleWithPrevious($this->defaultOptions);
        $planLineArrears = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planNameArrears, 'aid' => $aid));
        $planLineUpfront = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid, 'is_upfront' => true));
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid, 'is_upfront' => false));
        $discountLineArrears = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid, 'plan'=> $planNameArrears));
        $discountLineUpfront = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid, 'is_upfront' => true));
        $discountLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid, 'is_upfront' => false, 'plan'=> $planName));
        //flat
        // B2C_UPFRONT(33.605+3.252096774 (3/31*33.605))
        $this->assertEqualsWithDelta(33.605, $planLineUpfront['aprice'],$this->epsilon);
        $this->assertEquals(strtotime("2025-11-01 00:00:00"), $planLineUpfront['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $planLineUpfront['end']->toDateTime()->getTimestamp());
        
        $this->assertEqualsWithDelta(3.252096774, $planLine['aprice'],$this->epsilon);
        $this->assertEquals(strtotime("2025-10-29 17:38:59"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(null, $planLine['end']);

        //B2C_ARREARS 31.436935484(arrears)
        $this->assertEqualsWithDelta(31.436935484, $planLineArrears['aprice'],$this->epsilon);

        //discounts
        //B2C_UPFRONT - upfront
        $this->assertEqualsWithDelta(-16.806, $discountLineUpfront['aprice'],$this->epsilon);
        $this->assertEquals(strtotime("2025-11-01 00:00:00"), $discountLineUpfront['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $discountLineUpfront['discount_end']->toDateTime()->getTimestamp());

        //B2C_UPFRONT  3/31*16.806
        $this->assertEqualsWithDelta(-1.626387097, $discountLine['aprice'],$this->epsilon);
        $this->assertEquals(strtotime("2025-10-29 17:38:59"), $discountLine['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-11-01 00:00:00"), $discountLine['discount_end']->toDateTime()->getTimestamp());

        //B2C_ARREARS  29/31*16.806(arrears)
        $this->assertEqualsWithDelta(-15.721741935, $discountLineArrears['aprice'],$this->epsilon);
    }
    
    public function testUpfrontPlanOfPoratedSubscriberRevision_1()
    {
        /*
        Upfront Plan with prorated_termination false, subscriber deactive in 2025-11-28 10:42:32 and start in 2025-11-26 15:06:42
        */
        $aid = 12565;
        $this->defaultOptions['stamp'] = '202512';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = "UPFRONT_PLAN_PORATED_TERMINATION_FALSE";
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1, "prorated_termination" =>false]);//Prorate charge on termination = false
      
        $this->runCycleWithPrevious($this->defaultOptions);
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid, 'is_upfront' => false));
        //5/30*33.605
        $this->assertEqualsWithDelta(5.600833333, $planLine['aprice'],$this->epsilon);
        $this->assertEquals(strtotime("2025-11-26 15:06:42"), $planLine['start']->toDateTime()->getTimestamp());
    }

    public function testChangeSubFroUpfrontPlanToUpfrontPlan()
    {
        /*
        BRCD-5088: Change Subscriber Upfront Plan To Upfront Plan
        */
        $aid = 12564;
        $this->defaultOptions['stamp'] = '202512';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName1 = 'B2C_UPFRONT_1';
        $planName2= 'B2C_UPFRONT_2';
        $this->tester->generatePlan(['name' => $planName1, "upfront" => 1]);// charge on termination = true
        $this->tester->generatePlan(['name' => $planName2, "upfront" => 1]);// charge on termination = true
        $this->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        //B2C_UPFRONT_1: 
        $this->assertEqualsWithDelta(12.091599999999996, $billrun['totals']['before_vat'],$this->epsilon);

    }


    public function testChangeSubFroUpfrontPlanToUpfrontPlan_2()
    {
        /*
        BRCD-5088: Change Subscriber Upfront Plan To Upfront Plan
        */

        $aid = 12593;
        $this->defaultOptions['stamp'] = '202602';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName1 = 'B2C_UPFRONT_1_BRCD_5093';
        $planName2= 'B2C_UPFRONT_2_BRCD_5093';
        $this->tester->generatePlan(['name' => $planName1, "upfront" => 1]);// charge on termination = true
        $this->tester->generatePlan(['name' => $planName2, "upfront" => 1]);// charge on termination = true
        $this->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $this->assertEqualsWithDelta(5.250967741935486, $billrun['totals']['before_vat'],$this->epsilon);


    }


    
    public function testDiscountOfServiceFinishPreviousMonthOnUpfronInheritedPlan_1()
    {
        /*
        BRCD-5056
        Upfront plan, Service triggers discount (the discount subject is the plan)
        Service ends mid-month (2025-11-06 02:00:00)
        Running cycle 202512, expect refund for about 24 days of discount.
        */
        $aid = 12423;
        $this->defaultOptions['stamp'] = '202512';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = 'B2C_5GUNLIMITEDMAX_PP_INADV';
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1]);//Prorate charge on termination = true
        $this->tester->generateService(['name' => 'SERVICE', "prorated" => true]);//Prorate charge on termination = true

        $this->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid));
        $discountLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid));
        //flat-(33.605) + discount(+13.4448)(service finish in 2025-11-06 02:00:00) - 24/30*16.806 
        $this->assertEqualsWithDelta(33.605, $planLine['aprice'],$this->epsilon);

        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2026-01-01 00:00:00"), $planLine['end']->toDateTime()->getTimestamp());
        $this->assertEqualsWithDelta(13.4448, $discountLine['aprice'], $this->epsilon);
        $this->assertEquals(strtotime("2025-11-07 00:00:00"), $discountLine['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $discountLine['discount_end']->toDateTime()->getTimestamp());

    }

    public function testDiscountOfServiceStartPreviousMonthOnUpfronInheritedPlan_1()
    {
        /*
        BRCD-5056
        Upfront plan, Service triggers discount (the discount subject is the plan)
        Service start in mid-month (2024-11-06 16:32:11)
        Running cycle 202412, expect discount on  about 24 days of discount.
        */
        $aid = 12423;
        $this->defaultOptions['stamp'] = '202412';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = 'B2C_5GUNLIMITEDMAX_PP_INADV';
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1]);//Prorate charge on termination = true
        $this->tester->generateService(['name' => 'SERVICE', "prorated" => true]);//Prorate charge on termination = true

        $this->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid));
        $discountLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid));
        //flat-(33.605) + discount(-14.005 -16.806 )(service start in 2024-11-06 16:32:11) - 25/30*16.806 
        $this->assertEqualsWithDelta(33.605, $planLine['aprice'],$this->epsilon);

        $this->assertEquals(strtotime("2024-12-01 00:00:00"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-01-01 00:00:00"), $planLine['end']->toDateTime()->getTimestamp());
        $this->assertEqualsWithDelta(-30.811, $discountLine['aprice'], $this->epsilon);
        $this->assertEquals(strtotime("2024-11-06 00:00:00"), $discountLine['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-01-01 00:00:00"), $discountLine['discount_end']->toDateTime()->getTimestamp());

    }

    public function testDiscountOfServiceStartAndFinishPreviousMonthOnUpfronInheritedPlan_1()
    {
        /*
        BRCD-5056
        Upfront plan, Service triggers discount (the discount subject is the plan)
        Service start in mid-month (2024-11-06 16:32:11 -2024-11-23 02:00:00)
        Running cycle 202412, expect discount on  about 18 days of discount.
        */
        $aid = 12424;
        $this->defaultOptions['stamp'] = '202412';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = 'B2C_5GUNLIMITEDMAX_PP_INADV';
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1]);//Prorate charge on termination = true
        $this->tester->generateService(['name' => 'SERVICE', "prorated" => true]);//Prorate charge on termination = true

        $this->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid));
        $discountLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid));
        //flat-(33.605) + discount(-14.005 -16.806 ) - 18/30*16.806 
        $this->assertEqualsWithDelta(33.605, $planLine['aprice'],$this->epsilon);

        $this->assertEquals(strtotime("2024-12-01 00:00:00"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-01-01 00:00:00"), $planLine['end']->toDateTime()->getTimestamp());
        $this->assertEqualsWithDelta(-10.0836, $discountLine['aprice'], $this->epsilon);
        $this->assertEquals(strtotime("2024-11-06 00:00:00"), $discountLine['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2024-11-24 00:00:00"), $discountLine['discount_end']->toDateTime()->getTimestamp());

    }

    public function testDiscountOfFullServiceOnUpfronInheritedPlan_1()
    {
        /*
        BRCD-5056
        Upfront plan, Service triggers discount (the discount subject is the plan)
        full Service 
        Running cycle 202501, expect full discount.
        */
        $aid = 12423;
        $this->defaultOptions['stamp'] = '202501';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = 'B2C_5GUNLIMITEDMAX_PP_INADV';
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1]);//Prorate charge on termination = true
        $this->tester->generateService(['name' => 'SERVICE', "prorated" => true]);//Prorate charge on termination = true

        $this->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid));
        $discountLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid));
        //flat-(33.605) + discount(-16.806 )(service start in 2024-11-06 16:32:11) - 25/30*16.806 
        $this->assertEqualsWithDelta(33.605, $planLine['aprice'],$this->epsilon);

        $this->assertEquals(strtotime("2025-01-01 00:00:00"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-02-01 00:00:00"), $planLine['end']->toDateTime()->getTimestamp());
        $this->assertEqualsWithDelta(-16.806, $discountLine['aprice'], $this->epsilon);
        $this->assertEquals(strtotime("2025-01-01 00:00:00"), $discountLine['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-02-01 00:00:00"), $discountLine['discount_end']->toDateTime()->getTimestamp());

    }

    public function testNoDiscountOfServiceOnUpfronInheritedPlan_1()
    {
        /*
        BRCD-5056
        Upfront plan, Service triggers discount (the discount subject is the plan)
        no Service in the previous cycle  
        Running cycle 202601, expect no discount.
        */
        $aid = 12423;
        $this->defaultOptions['stamp'] = '202601';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = 'B2C_5GUNLIMITEDMAX_PP_INADV';
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1]);//Prorate charge on termination = true
        $this->tester->generateService(['name' => 'SERVICE', "prorated" => true]);//Prorate charge on termination = true

        $this->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid));
        $discountLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid));
        //flat-(33.605) + discount(-16.806 )(service start in 2024-11-06 16:32:11) - 25/30*16.806 
        $this->assertEqualsWithDelta(33.605, $planLine['aprice'],$this->epsilon);

        $this->assertEquals(strtotime("2026-01-01 00:00:00"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2026-02-01 00:00:00"), $planLine['end']->toDateTime()->getTimestamp());
        $this->assertEquals(null, $discountLine);

    }

    public function testFullDiscountAndPlan_1()
    {
        /*
        upfront plan  discount with "proration": "inherited" and plan start previous month
        and discount start in the middle of previous month,  prorate start = true- > 
        expected proration discount from the start of the discount +  discount on the current cycle (assume still not finish- need to support also finish before case)
        */
        $aid = 12408;
        $this->defaultOptions['stamp'] = '202512';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = "UPFRONT_PLAN_PORATED";
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1]);//Prorate start = true
        $this->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLineUpfront = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid, 'is_upfront' => true));
        $discountLineUpfront = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid, 'is_upfront' => true));
        //flat-42.566333333(9.756290323+33.605), discount(-16.806 +(-4.87916129))(start in in 2025-10-23 10:04:25) 9/30*16.806
        $this->assertEqualsWithDelta(16.799, $billrun['totals']['before_vat'],$this->epsilon);
        $this->assertEquals(null, $planLine);
        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $planLineUpfront['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2026-01-01 00:00:00"), $planLineUpfront['end']->toDateTime()->getTimestamp());

        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $discountLineUpfront['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2026-01-01 00:00:00"), $discountLineUpfront['discount_end']->toDateTime()->getTimestamp());
    }

    /*
     * ==================== BRCD-5421 - Knowing upfront changes in advance ====================
     *
     * Covers:
     * 1. The upfront (next cycle) price is prorated by a plan deactivation that is already known.
     * 2. Refund reconciliation - old and new upfront lines exist: the difference is credited/charged.
     * 3. Refund reconciliation - a plan with no subscriber record at all is not reconciled (boundary).
     * 4. Refund reconciliation - only a new upfront charge exists (previous run missed it): charged as is.
     * 5. Discounts (b) - the upfront discount is calculated like an arrears discount of the next cycle,
     *    taking the discount end date into account.
     * 6. Discounts (a) - the upfront discount of the previous cycle is reconciled with the discount
     *    that should have been given.
     * 7. The upfront charge amounts - the regular (arrears) charge of the next cycle, paid in
     *    advance - examined directly on the charge class.
     * 8. Refund reconciliation - the plan was (retroactively) deactivated before the cycle started and
     *    re-activated in its middle: the difference between the re-activated period and the full month
     *    that was charged is credited.
     * 9. Upfront services are not reconciled - only subscriber plan records are.
     * 10. A custom recurrence plan is reconciled only when the previous cycle was one of its
     *     charging cycles.
     * 11. No duplicate discount CDRs when the plan has both an upfront and a refund line - one
     *     next cycle discount + one current cycle correction.
     * 12. A discount that only starts within the next (upfront paid) cycle - not eligible on the
     *     current cycle at all - still discounts the upfront line, by the next cycle eligibility.
     */

    protected function mongoDate($str)
    {
        return new \MongoDB\BSON\UTCDateTime(strtotime($str) * 1000);
    }

    /**
     * The previous billrun key of the tested cycle
     */
    protected function previousBillrunKey()
    {
        return \Billrun_Billingcycle::getPreviousBillrunKey($this->defaultOptions['stamp']);
    }

    /**
     * The reconciliation only runs when the previous billing cycle has ended
     */
    protected function seedPreviousBillrun($aid)
    {
        $cycle = new \Billrun_DataTypes_CycleTime($this->defaultOptions['stamp']);
        // Billrun_Billingcycle::hasCycleEnded requires zero_pages_limit finished empty pages
        $zeroPages = max(1, (int) \Billrun_Factory::config()->getConfigValue('customer.aggregator.zero_pages_limit', 1));
        for ($page = 0; $page < $zeroPages; $page++) {
            $cycleDoc = array(
                'billrun_key' => $this->previousBillrunKey(),
                'page_number' => $page,
                'page_size' => (int) \Billrun_Factory::config()->getConfigValue('customer.aggregator.size', 100),
                'count' => 0,
                'start_time' => new \MongoDB\BSON\UTCDateTime(($cycle->start() + 600) * 1000),
                'end_time' => new \MongoDB\BSON\UTCDateTime(($cycle->start() + 1200) * 1000),
            );
            if (\Billrun_Factory::config()->isMultiDayCycle()) {
                $cycleDoc['invoicing_day'] = \Billrun_Factory::config()->getConfigChargingDay();
            }
            $this->tester->haveInCollection('billing_cycle', $cycleDoc);
        }
    }

    /**
     * An upfront plan line as created by the previous cycle run - covering the tested cycle
     */
    protected function seedPreviousUpfrontLine($aid, $sid, $plan, $aprice)
    {
        $cycle = new \Billrun_DataTypes_CycleTime($this->defaultOptions['stamp']);
        $this->tester->haveInCollection('lines', array(
            'stamp' => md5('test_old_upfront_' . $aid . '_' . $sid . '_' . $plan),
            'aid' => $aid,
            'sid' => $sid,
            'billrun' => $this->previousBillrunKey(),
            'type' => 'flat',
            'usaget' => 'flat',
            'plan' => $plan,
            'charge_op' => 'charge',
            'is_upfront' => true,
            'aprice' => $aprice,
            'full_price' => $aprice,
            'usagev' => 1,
            'source' => 'billrun',
            'urt' => new \MongoDB\BSON\UTCDateTime(($cycle->start() - 1) * 1000),
            'start' => new \MongoDB\BSON\UTCDateTime($cycle->start() * 1000),
            'end' => new \MongoDB\BSON\UTCDateTime($cycle->end() * 1000),
        ));
    }

    /**
     * An upfront discount line as created by the previous cycle run - relating to the tested cycle
     */
    protected function seedPreviousUpfrontDiscountLine($aid, $sid, $key, $aprice)
    {
        $cycle = new \Billrun_DataTypes_CycleTime($this->defaultOptions['stamp']);
        $this->tester->haveInCollection('lines', array(
            'stamp' => md5('test_old_upfront_discount_' . $aid . '_' . $sid . '_' . $key),
            'aid' => $aid,
            'sid' => $sid,
            'billrun' => $this->previousBillrunKey(),
            'type' => 'credit',
            'usaget' => 'discount',
            'key' => $key,
            'name' => $key,
            'is_upfront' => true,
            'aprice' => $aprice,
            'usagev' => 1,
            'source' => 'billrun',
            'urt' => new \MongoDB\BSON\UTCDateTime(($cycle->start() - 1) * 1000),
            'discount_from' => new \MongoDB\BSON\UTCDateTime($cycle->start() * 1000),
            'discount_to' => new \MongoDB\BSON\UTCDateTime($cycle->end() * 1000),
            'discount_start' => new \MongoDB\BSON\UTCDateTime($cycle->start() * 1000),
            'discount_end' => new \MongoDB\BSON\UTCDateTime($cycle->end() * 1000),
        ));
    }

    /**
     * 1. plan_deactivation (2026-01-16) is known while running the 202601 cycle -
     *    the upfront charge for January is prorated instead of a full month.
     */
    public function testUpfrontChargeIsProratedByKnownDeactivation()
    {
        $aid = 41001;
        $sid = 51001;
        $planName = 'UPFRONT_ADV_PLAN1';
        $this->defaultOptions['stamp'] = '202601';
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => $planName, 'upfront' => 1]);
        // December was correctly charged by the previous cycle run - nothing to reconcile
        $this->seedPreviousBillrun($aid);
        $this->seedPreviousUpfrontLine($aid, $sid, $planName, 100);
        $this->tester->runCycle($this->defaultOptions);

        $planLine = $this->tester->grabFromCollection('lines', array('type' => 'flat', 'name' => $planName, 'aid' => $aid, 'charge_op' => 'charge', 'billrun' => '202601'));
        $this->assertNotEmpty($planLine, 'upfront plan line was not created');
        // 15 days (the arrears convention - the deactivation day is not charged) out of 31
        $this->assertEqualsWithDelta(100 * 15 / 31, $planLine['aprice'], $this->epsilon);
        $this->assertEquals(strtotime('2026-01-01 00:00:00'), $planLine['prorated_start_date']->toDateTime()->getTimestamp());
        // the arrears convention - the charge period ends a second before the deactivation
        $this->assertEquals(strtotime('2026-01-16 00:00:00') - 1, $planLine['end_date']->toDateTime()->getTimestamp());

        // the December charge did not change - no reconciliation line
        $reconcileLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => 'flat', 'charge_op' => 'refund', 'aid' => $aid));
        $this->assertEmpty($reconcileLine, 'the previous charge did not change - no reconciliation line was expected');

        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202601', 'aid' => $aid));
        $this->assertEqualsWithDelta(100 * 15 / 31, $billrun['totals']['before_vat'], $this->epsilon);
    }

    /**
     * 2. The previous run charged a full month upfront, and now a deactivation in the middle of
     *    that month (2025-12-16) is known - the difference is credited.
     */
    public function testReconciliationCreditsTheDifferenceOnDeactivation()
    {
        $aid = 41002;
        $sid = 51002;
        $planName = 'UPFRONT_ADV_PLAN2';
        $this->defaultOptions['stamp'] = '202601';
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => $planName, 'upfront' => 1]);
        $this->seedPreviousBillrun($aid);
        $this->seedPreviousUpfrontLine($aid, $sid, $planName, 100);
        $this->tester->runCycle($this->defaultOptions);

        $reconcileLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => 'flat', 'charge_op' => 'refund', 'aid' => $aid));
        $this->assertNotEmpty($reconcileLine, 'reconciliation line was not created');
        $this->assertEquals('refund', $reconcileLine['charge_op']);
        $this->assertEquals('202601', $reconcileLine['billrun']);
        // expected 15/31 of the month (the arrears convention), charged a full month -> credit the difference
        $this->assertEqualsWithDelta(100 * 15 / 31 - 100, $reconcileLine['aprice'], $this->epsilon);
        // the line carries the corrected (expected) charge period
        $this->assertEquals(strtotime('2025-12-01 00:00:00'), $reconcileLine['prorated_start_date']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime('2025-12-16 00:00:00') - 1, $reconcileLine['end_date']->toDateTime()->getTimestamp());

        // the plan deactivates before the next cycle starts - no new upfront charge
        $upfrontLine = $this->tester->grabFromCollection('lines', array('type' => 'flat', 'charge_op' => 'charge', 'aid' => $aid, 'billrun' => '202601'));
        $this->assertEmpty($upfrontLine, 'no upfront charge was expected for the next cycle');

        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202601', 'aid' => $aid));
        $this->assertEqualsWithDelta(100 * 15 / 31 - 100, $billrun['totals']['before_vat'], $this->epsilon);
    }

    /**
     * 3. A plan that no longer exists in the subscriber records is not reconciled - the
     *    reconciliation is driven by the plan charge flow, so a record must exist (e.g. via
     *    plan_deactivation). A fully removed plan keeps its previous charge untouched.
     */
    public function testRemovedPlanIsNotReconciled()
    {
        $aid = 41003;
        $sid = 51003;
        $removedPlan = 'UPFRONT_ADV_PLAN3';
        $arrearsPlan = 'SIMPLE_ADV_PLAN3';
        $this->defaultOptions['stamp'] = '202601';
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => $removedPlan, 'upfront' => 1]);
        $this->tester->generatePlan(['name' => $arrearsPlan, 'price' => [['price' => 50, 'from' => 0, 'to' => 'UNLIMITED']]]);
        $this->seedPreviousBillrun($aid);
        $this->seedPreviousUpfrontLine($aid, $sid, $removedPlan, 100);
        $this->tester->runCycle($this->defaultOptions);

        // no record of the removed plan - nothing reconciles it
        $reconcileLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => 'flat', 'charge_op' => 'refund', 'aid' => $aid));
        $this->assertEmpty($reconcileLine, 'a plan without a subscriber record is not reconciled');

        // the arrears plan of December is charged regularly
        $arrearsLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => 'flat', 'name' => $arrearsPlan, 'aid' => $aid));
        $this->assertEqualsWithDelta(50, $arrearsLine['aprice'], $this->epsilon);

        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202601', 'aid' => $aid));
        $this->assertEqualsWithDelta(50, $billrun['totals']['before_vat'], $this->epsilon);
    }

    /**
     * 4. The previous run did not charge the (retroactively added) upfront plan - it is charged as is,
     *    in addition to the regular upfront charge of the next cycle.
     */
    public function testReconciliationChargesMissedUpfrontAsIs()
    {
        $aid = 41004;
        $sid = 51004;
        $planName = 'UPFRONT_ADV_PLAN4';
        $this->defaultOptions['stamp'] = '202601';
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => $planName, 'upfront' => 1]);
        $this->seedPreviousBillrun($aid);
        $this->tester->runCycle($this->defaultOptions);

        $reconcileLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => 'flat', 'charge_op' => 'refund', 'aid' => $aid));
        $this->assertNotEmpty($reconcileLine, 'reconciliation line was not created');
        $this->assertEquals('refund', $reconcileLine['charge_op']);
        $this->assertEqualsWithDelta(100, $reconcileLine['aprice'], $this->epsilon);
        $this->assertEquals(strtotime('2025-12-01 00:00:00'), $reconcileLine['prorated_start_date']->toDateTime()->getTimestamp());

        // the regular upfront charge of January
        $upfrontLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => 'flat', 'charge_op' => 'charge', 'aid' => $aid));
        $this->assertEqualsWithDelta(100, $upfrontLine['aprice'], $this->epsilon);
        $this->assertEquals(strtotime('2026-01-01 00:00:00'), $upfrontLine['prorated_start_date']->toDateTime()->getTimestamp());

        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202601', 'aid' => $aid));
        $this->assertEqualsWithDelta(200, $billrun['totals']['before_vat'], $this->epsilon);
    }

    /**
     * 5. A discount that ends in the middle of the next (upfront paid) cycle (2026-01-16) -
     *    the upfront discount is calculated like an arrears discount of the next cycle.
     */
    public function testUpfrontDiscountIsProratedByKnownDiscountEnd()
    {
        $aid = 41005;
        $sid = 51005;
        $planName = 'UPFRONT_ADV_PLAN5';
        $this->defaultOptions['stamp'] = '202601';
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => $planName, 'upfront' => 1]);
        // December was correctly charged and discounted by the previous cycle run - nothing to reconcile
        $this->seedPreviousBillrun($aid);
        $this->seedPreviousUpfrontLine($aid, $sid, $planName, 100);
        $this->seedPreviousUpfrontDiscountLine($aid, $sid, 'SUBSCRIBER_DISCOUNT_1', -10);
        $this->tester->runCycle($this->defaultOptions);

        $planLine = $this->tester->grabFromCollection('lines', array('type' => 'flat', 'name' => $planName, 'aid' => $aid, 'charge_op' => 'charge', 'billrun' => '202601'));
        $this->assertEqualsWithDelta(100, $planLine['aprice'], $this->epsilon);

        $discountLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => 'credit', 'usaget' => 'discount', 'aid' => $aid, 'discount_from' => $this->mongoDate('2026-01-01 00:00:00')));
        $this->assertNotEmpty($discountLine, 'upfront discount line was not created');
        // 15 days out of the 31 days of January
        $this->assertEqualsWithDelta(-10 * 15 / 31, $discountLine['aprice'], $this->epsilon);
        $this->assertEquals(strtotime('2026-01-01 00:00:00'), $discountLine['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime('2026-01-16 00:00:00'), $discountLine['discount_end']->toDateTime()->getTimestamp());

        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202601', 'aid' => $aid));
        $this->assertEqualsWithDelta(100 - 10 * 15 / 31, $billrun['totals']['before_vat'], $this->epsilon);
    }

    /**
     * 6. The previous run gave a full month upfront discount, but the discount ended in the middle
     *    of that month (2025-12-16) - the difference is charged back.
     */
    public function testUpfrontDiscountReconciliation()
    {
        $aid = 41006;
        $sid = 51006;
        $planName = 'UPFRONT_ADV_PLAN6';
        $this->defaultOptions['stamp'] = '202601';
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => $planName, 'upfront' => 1]);
        $this->seedPreviousBillrun($aid);
        $this->seedPreviousUpfrontLine($aid, $sid, $planName, 100);
        $this->seedPreviousUpfrontDiscountLine($aid, $sid, 'SUBSCRIBER_DISCOUNT_1', -10);
        $this->tester->runCycle($this->defaultOptions);

        // the plan charge did not change - no plan reconciliation line
        $planReconcileLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => 'flat', 'charge_op' => 'refund', 'aid' => $aid));
        $this->assertEmpty($planReconcileLine, 'the plan charge did not change - no reconciliation line was expected');

        // the discount ended within December - no upfront discount for January
        $upfrontDiscountLine = $this->tester->grabFromCollection('lines', array('type' => 'credit', 'usaget' => 'discount', 'aid' => $aid, 'billrun' => '202601', 'discount_from' => $this->mongoDate('2026-01-01 00:00:00')));
        $this->assertEmpty($upfrontDiscountLine, 'the discount already ended - no upfront discount was expected');

        // the previous run gave -10 but only -10*15/31 was deserved -> charge back the difference.
        // the reconciliation may be split to several CDRs (e.g. when a plugin renames discount keys),
        // so the total reconciled amount is asserted
        $reconcileCdrs = \Billrun_Factory::db()->linesCollection()
                ->query(array('type' => 'credit', 'usaget' => 'discount', 'aid' => $aid, 'billrun' => '202601'))
                ->cursor();
        $reconciledAmount = 0;
        $reconcileCdrsCount = 0;
        foreach ($reconcileCdrs as $reconcileCdr) {
            $reconciledAmount += $reconcileCdr['aprice'];
            $reconcileCdrsCount++;
        }
        $this->assertGreaterThan(0, $reconcileCdrsCount, 'discount reconciliation CDR was not created');
        $this->assertEqualsWithDelta(-10 * 15 / 31 + 10, $reconciledAmount, $this->epsilon);

        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202601', 'aid' => $aid));
        $this->assertEqualsWithDelta(100 - 10 * 15 / 31 + 10, $billrun['totals']['before_vat'], $this->epsilon);
    }

    /**
     * 8. The previous run charged December fully, and now it is known that the plan was deactivated
     *    before December started (2025-11-25, recorded after that run) and re-activated in the middle
     *    of December (2025-12-16) - the reconciliation credits the difference between the re-activated
     *    period and the full month that was charged, and January is paid upfront.
     */
    public function testReactivatedPlanReconcilesThePreviousCharge()
    {
        $aid = 41007;
        $sid = 51007;
        $planName = 'UPFRONT_ADV_PLAN7';
        $this->defaultOptions['stamp'] = '202601';
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => $planName, 'upfront' => 1]);
        $this->seedPreviousBillrun($aid);
        $this->seedPreviousUpfrontLine($aid, $sid, $planName, 100);
        $this->tester->runCycle($this->defaultOptions);

        // the plan was active only from the re-activation (16/31), but a full month was charged -
        // the reconciliation credits the difference
        $reconcileLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => 'flat', 'charge_op' => 'refund', 'aid' => $aid));
        $this->assertNotEmpty($reconcileLine, 'reconciliation line was not created');
        $this->assertEqualsWithDelta(100 * 16 / 31 - 100, $reconcileLine['aprice'], $this->epsilon);
        $this->assertEquals(strtotime('2025-12-16 00:00:00'), $reconcileLine['prorated_start_date']->toDateTime()->getTimestamp());

        // January is paid upfront as usual
        $upfrontLine = $this->tester->grabFromCollection('lines', array('type' => 'flat', 'charge_op' => 'charge', 'aid' => $aid, 'billrun' => '202601', 'prorated_start_date' => $this->mongoDate('2026-01-01 00:00:00')));
        $this->assertNotEmpty($upfrontLine, 'upfront plan line was not created');
        $this->assertEqualsWithDelta(100, $upfrontLine['aprice'], $this->epsilon);
        $this->assertTrue(!empty($upfrontLine['is_upfront']));

        // 16/31*100 - 100 (reconciliation) + 100 (January upfront)
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202601', 'aid' => $aid));
        $this->assertEqualsWithDelta(100 * 16 / 31, $billrun['totals']['before_vat'], $this->epsilon);
    }

    /**
     * 9. Upfront services are not reconciled - a service record does not carry plan_activation, and
     *    its lines are not 'flat' lines, so reconciling it would never find its previous run lines
     *    and would charge the whole cycle again as a missed charge (double billing) every month.
     */
    public function testUpfrontServiceIsNotReconciled()
    {
        $cycle = new \Billrun_DataTypes_CycleTime('202601');
        $base = array(
            'cycle' => $cycle,
            'name' => 'ADV_SERVICE9',
            'price' => array(array('price' => 100, 'from' => 0, 'to' => 'UNLIMITED')),
            'start' => strtotime('2025-10-23 00:00:00'),
            'end' => strtotime('2200-01-01 00:00:00'),
            'line_stump' => array('aid' => 41100, 'sid' => 51100),
        );

        // a service record (no plan_activation) - no reconciliation
        $charge = new \Billrun_Plans_Charge_Upfront_Month($base);
        $this->assertNull($charge->getRefund($cycle));

        // the same record as a subscriber plan record is reconciled - nothing was charged by the
        // previous run, so the whole cycle is charged as missed
        $charge = new \Billrun_Plans_Charge_Upfront_Month(array_merge($base, array(
            'plan' => 'ADV_SERVICE9',
            'plan_activation' => new \Mongodloid_Date(strtotime('2025-10-23 00:00:00')),
        )));
        $rows = $charge->getRefund($cycle);
        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(100, $rows[0]['value'], $this->epsilon);
    }

    /**
     * 10. A custom recurrence (e.g. quarterly) plan is reconciled only when the previous cycle was
     *     one of its charging cycles - its upfront lines were created by the last charging run, so
     *     in any other cycle nothing would be found, and the whole (already paid) period would be
     *     wrongly charged again as a missed charge.
     */
    public function testCustomRecurrencePlanReconcilesOnlyAfterItsChargingCycle()
    {
        $cycle = new \Billrun_DataTypes_CycleTime('202601');
        $base = array(
            'cycle' => $cycle,
            'plan' => 'ADV_QUARTERLY10',
            'name' => 'ADV_QUARTERLY10',
            'price' => array(array('price' => 100, 'from' => 0, 'to' => 'UNLIMITED')),
            'recurrence' => array('frequency' => 3, 'periodicity' => 'month'),
            'end' => strtotime('2200-01-01 00:00:00'),
        );

        // activated 2025-07-01 - the plan quarters are [Oct 1, Jan 1), ... and the [Oct 1, Jan 1)
        // quarter was paid upfront by the September cycle run (billrun key 202510). the previous
        // cycle (202512) is not a charging cycle - without the gate, the quarter would not be found
        // under 202512 and would be charged again as missed
        $charge = new \Billrun_Plans_Charge_Upfront_Custom(array_merge($base, array(
            'start' => strtotime('2025-07-01 00:00:00'),
            'plan_activation' => new \Mongodloid_Date(strtotime('2025-07-01 00:00:00')),
            'activation_date' => new \Mongodloid_Date(strtotime('2025-07-01 00:00:00')),
            'line_stump' => array('aid' => 41101, 'sid' => 51101),
        )));
        $this->assertNull($charge->getRefund($cycle));

        // activated 2025-09-01 - the previous cycle (202512) is a charging cycle of the plan, so it
        // is reconciled against its lines (nothing was charged here - charged as missed)
        $charge = new \Billrun_Plans_Charge_Upfront_Custom(array_merge($base, array(
            'start' => strtotime('2025-09-01 00:00:00'),
            'plan_activation' => new \Mongodloid_Date(strtotime('2025-09-01 00:00:00')),
            'activation_date' => new \Mongodloid_Date(strtotime('2025-09-01 00:00:00')),
            'line_stump' => array('aid' => 41102, 'sid' => 51102),
        )));
        $this->assertNotEmpty($charge->getRefund($cycle));

        // activated 2025-10-01 - within the current [Oct 1, Jan 1) quarter: a mid cycle activation
        // is not gated by the alignment - its quarter was never paid and is charged as missed
        $charge = new \Billrun_Plans_Charge_Upfront_Custom(array_merge($base, array(
            'start' => strtotime('2025-10-01 00:00:00'),
            'plan_activation' => new \Mongodloid_Date(strtotime('2025-10-01 00:00:00')),
            'activation_date' => new \Mongodloid_Date(strtotime('2025-10-01 00:00:00')),
            'line_stump' => array('aid' => 41103, 'sid' => 51103),
        )));
        $this->assertNotEmpty($charge->getRefund($cycle));
    }

    /**
     * 11. A plan with both an upfront line and a refund line (re-activated mid cycle, see 8.) and a
     *     discount - no duplicate discount CDRs: exactly one discount for the next (upfront paid)
     *     cycle anchored on the upfront line, and one correction of the current cycle discount.
     *     The refund line itself is not discounted directly.
     */
    public function testNoDuplicateDiscountOnUpfrontAndRefundLines()
    {
        $aid = 41008;
        $sid = 51008;
        $planName = 'UPFRONT_ADV_PLAN8';
        $this->defaultOptions['stamp'] = '202601';
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => $planName, 'upfront' => 1]);
        $this->seedPreviousBillrun($aid);
        // the previous run charged and discounted December fully
        $this->seedPreviousUpfrontLine($aid, $sid, $planName, 100);
        $this->seedPreviousUpfrontDiscountLine($aid, $sid, 'SUBSCRIBER_DISCOUNT_2', -10);
        $this->tester->runCycle($this->defaultOptions);

        // both a refund line (the December reconciliation) and an upfront line (January) exist
        $reconcileLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => 'flat', 'charge_op' => 'refund', 'aid' => $aid));
        $this->assertNotEmpty($reconcileLine, 'reconciliation line was not created');
        $upfrontLine = $this->tester->grabFromCollection('lines', array('type' => 'flat', 'charge_op' => 'charge', 'aid' => $aid, 'billrun' => '202601'));
        $this->assertNotEmpty($upfrontLine, 'upfront plan line was not created');

        // the discount CDRs of the run - grouped by their cycle window to prove no duplicates
        $discountCdrs = \Billrun_Factory::db()->linesCollection()
                ->query(array('type' => 'credit', 'usaget' => 'discount', 'aid' => $aid, 'billrun' => '202601'))
                ->cursor();
        $nextCycleAmount = 0;
        $currentCycleAmount = 0;
        foreach ($discountCdrs as $discountCdr) {
            if (\Billrun_Utils_Time::getTime($discountCdr['discount_from']) >= strtotime('2026-01-01 00:00:00')) {
                $nextCycleAmount += $discountCdr['aprice'];
            } else {
                $currentCycleAmount += $discountCdr['aprice'];
            }
        }
        // January (the upfront paid cycle) is discounted fully, once
        $this->assertEqualsWithDelta(-10, $nextCycleAmount, $this->epsilon);
        // December was discounted fully (-10) by the previous run, but the discount only starts in
        // its middle (2025-12-16) - the difference is charged back, once
        $this->assertEqualsWithDelta(10 - 10 * 16 / 31, $currentCycleAmount, $this->epsilon);

        // plan: 16/31*100 - 100 (reconciliation) + 100 (January upfront), discounts as above
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202601', 'aid' => $aid));
        $this->assertEqualsWithDelta(90 * 16 / 31, $billrun['totals']['before_vat'], $this->epsilon);
    }

    /**
     * 12. A discount that only starts within the next (upfront paid) cycle (2026-01-10) - not
     *     eligible on the current (December) cycle at all, but the upfront line is discounted by
     *     the next cycle eligibility, prorated from the discount start.
     */
    public function testUpfrontDiscountEligibleOnlyOnNextCycle()
    {
        $aid = 41009;
        $sid = 51009;
        $planName = 'UPFRONT_ADV_PLAN9';
        $this->defaultOptions['stamp'] = '202601';
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => $planName, 'upfront' => 1]);
        // December was correctly charged by the previous cycle run - nothing to reconcile
        $this->seedPreviousBillrun($aid);
        $this->seedPreviousUpfrontLine($aid, $sid, $planName, 100);
        $this->tester->runCycle($this->defaultOptions);

        $planLine = $this->tester->grabFromCollection('lines', array('type' => 'flat', 'name' => $planName, 'aid' => $aid, 'charge_op' => 'charge', 'billrun' => '202601'));
        $this->assertEqualsWithDelta(100, $planLine['aprice'], $this->epsilon);

        // the upfront (January) discount, prorated from the discount start (22 of the 31 days)
        $discountLine = $this->tester->grabFromCollection('lines', array('type' => 'credit', 'usaget' => 'discount', 'aid' => $aid, 'billrun' => '202601'));
        $this->assertNotEmpty($discountLine, 'upfront discount line was not created');
        $this->assertEqualsWithDelta(-10 * 22 / 31, $discountLine['aprice'], $this->epsilon);
        $this->assertEquals(strtotime('2026-01-10 00:00:00'), $discountLine['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime('2026-02-01 00:00:00'), $discountLine['discount_end']->toDateTime()->getTimestamp());

        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202601', 'aid' => $aid));
        $this->assertEqualsWithDelta(100 - 10 * 22 / 31, $billrun['totals']['before_vat'], $this->epsilon);
    }

    /**
     * 7. The upfront charge amounts - the regular (arrears) charge of the next cycle, paid in
     *    advance, taking the deactivation that is already known into account.
     *    Examined directly on the charge class (cycle 202601 = December 2025, paying January upfront).
     */
    public function testUpfrontChargeFractionsByKnownDeactivation()
    {
        $base = array(
            'cycle' => new \Billrun_DataTypes_CycleTime('202601'),
            'plan' => 'FRACTIONS_TEST',
            'name' => 'FRACTIONS_TEST',
            'price' => array(array('price' => 100, 'from' => 0, 'to' => 'UNLIMITED')),
            'start' => strtotime('2025-10-23 00:00:00'),
        );

        // ongoing plan - the full next month is charged
        $charge = new \Billrun_Plans_Charge_Upfront_Month(array_merge($base, array('end' => strtotime('2200-01-01 00:00:00'))));
        $rows = $charge->getPrice();
        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(100, $rows[0]['value'], $this->epsilon);
        $this->assertEquals(strtotime('2026-01-01 00:00:00'), $rows[0]['prorated_start_date']->sec);
        $this->assertEquals(strtotime('2026-02-01 00:00:00'), $rows[0]['prorated_end_date']->sec);

        // the deactivation is already known within the upfront cycle - a prorated charge
        // (the arrears convention - the deactivation day is not charged)
        $charge = new \Billrun_Plans_Charge_Upfront_Month(array_merge($base, array('end' => strtotime('2026-01-16 00:00:00'))));
        $rows = $charge->getPrice();
        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(100 * 15 / 31, $rows[0]['value'], $this->epsilon);
        $this->assertEquals(strtotime('2026-01-01 00:00:00'), $rows[0]['prorated_start_date']->sec);
        $this->assertEquals(strtotime('2026-01-16 00:00:00') - 1, $rows[0]['prorated_end_date']->sec);

        // the deactivation is exactly when the upfront cycle starts - nothing to charge
        $charge = new \Billrun_Plans_Charge_Upfront_Month(array_merge($base, array('end' => strtotime('2026-01-01 00:00:00'))));
        $this->assertNull($charge->getPrice());

        // the deactivation is within the current (already upfront paid) cycle - nothing to charge,
        // the reconciliation (getRefund) credits the unused period
        $charge = new \Billrun_Plans_Charge_Upfront_Month(array_merge($base, array('end' => strtotime('2025-12-16 00:00:00'))));
        $this->assertNull($charge->getPrice());

        // activation in the middle of the current cycle - only the full upfront month is charged;
        // the current cycle part is settled by the reconciliation (getRefund)
        $charge = new \Billrun_Plans_Charge_Upfront_Month(array_merge($base, array(
            'start' => strtotime('2025-12-16 00:00:00'),
            'end' => strtotime('2200-01-01 00:00:00'),
        )));
        $rows = $charge->getPrice();
        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(100, $rows[0]['value'], $this->epsilon);

        // activation in the middle of the current cycle and a known deactivation within the next
        // one - a prorated upfront month
        $charge = new \Billrun_Plans_Charge_Upfront_Month(array_merge($base, array(
            'start' => strtotime('2025-12-16 00:00:00'),
            'end' => strtotime('2026-01-16 00:00:00'),
        )));
        $rows = $charge->getPrice();
        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(100 * 15 / 31, $rows[0]['value'], $this->epsilon);
        $this->assertEquals(strtotime('2026-01-16 00:00:00') - 1, $rows[0]['prorated_end_date']->sec);
    }
}

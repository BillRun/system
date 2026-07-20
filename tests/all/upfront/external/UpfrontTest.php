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
        // the discounts static cache is keyed by cycle - tests sharing cycle keys must not see
        // each other's (cleaned) discounts
        \Billrun_DiscountManager::resetDiscountsCache();
    }

    protected function _after()
    {
        $this->tester->restoreTimezone();
        $this->tester->enableDBModeSettings();
    }

    /**
     * BRCD-5421 - the upfront charges are reconciled against the previous cycle run lines. the
     * legacy expectations assume the previous run did NOT know the changes in advance - so by
     * default it runs with the full fraction (legacy) upfront behavior, and the tested cycle runs
     * with the default (knowing the changes in advance) behavior.
     *
     * @param array $options the tested cycle options
     * @param boolean $fullFraction run the previous cycle with the full fraction (legacy) upfront
     * behavior - pass false to run it with the knowing in advance behavior as well
     */
    protected function runCycleWithPrevious($options, $fullFraction = true)
    {
        $previousOptions = $options;
        $previousOptions['stamp'] = \Billrun_Billingcycle::getPreviousBillrunKey($options['stamp']);
        \Billrun_Factory::config()->setConfigValue('billrun.upfront.full_fraction', $fullFraction);
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
        $this->assertEquals(strtotime("2025-12-24 00:00:00"), $planLine['start']->toDateTime()->getTimestamp());
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
        // the discount splits to two lines - the next cycle upfront discount and the current
        // cycle reconciliation (refund)
        $discountLineUpfront = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid, 'is_upfront' => true));
        $discountLineRefund = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid, 'charge_op' => 'refund'));
        //flat-(33.605) + discount(-14.005 -16.806 )(service start in 2024-11-06 16:32:11) - 25/30*16.806 
        $this->assertEqualsWithDelta(33.605, $planLine['aprice'],$this->epsilon);

        $this->assertEquals(strtotime("2024-12-01 00:00:00"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEqualsWithDelta(-16.806, $discountLineUpfront['aprice'], $this->epsilon);
        $this->assertEquals(strtotime("2024-12-01 00:00:00"), $discountLineUpfront['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-01-01 00:00:00"), $discountLineUpfront['discount_end']->toDateTime()->getTimestamp());
        $this->assertEqualsWithDelta(-14.005, $discountLineRefund['aprice'], $this->epsilon);
        $this->assertEquals(strtotime("2024-11-06 00:00:00"), $discountLineRefund['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2024-12-01 00:00:00"), $discountLineRefund['discount_end']->toDateTime()->getTimestamp());

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
        and discount finish in the middle of next month,  prorate end = true- > 
        expected proration discount until the end of the discount (upfront knowing in adevance) 
        */
        $aid = 12408;
        $this->defaultOptions['stamp'] = '202512';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = "UPFRONT_PLAN_PORATED";
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1]);//Prorate end = true
        $this->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLineUpfront = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid, 'is_upfront' => true));
        $discountLineUpfront = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid, 'is_upfront' => true));
        //nowing in advance so - flat-33.605, discount(-12.468967742)(finish in 2025-12-23 10:04:25) 23/31*16.806
        $this->assertEqualsWithDelta(21.136032258, $billrun['totals']['before_vat'],$this->epsilon);
        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $planLineUpfront['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2026-01-01 00:00:00"), $planLineUpfront['end']->toDateTime()->getTimestamp());

        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $discountLineUpfront['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-23 10:04:25"), $discountLineUpfront['discount_end']->toDateTime()->getTimestamp());
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
     *    advance, taking the known activation/deactivation fractions into account.
     * 8. Refund reconciliation - the plan was (retroactively) deactivated before the cycle started and
     *    re-activated in its middle: the difference between the re-activated period and the full month
     *    that was charged is credited.
     * 11. No duplicate discount CDRs when the plan has both an upfront and a refund line - one
     *     next cycle discount + one current cycle correction.
     * 12. A discount that only starts within the next (upfront paid) cycle - not eligible on the
     *     current cycle at all - still discounts the upfront line, by the next cycle eligibility.
     * 13. An upfront plan that starts in the middle of the next (upfront paid) cycle - the
     *     prorated prepaid month is charged in advance.
     * 14. A discount of an upfront plan that starts in the middle of the next (upfront paid)
     *     cycle - the upfront discount prorated from the discount start.
     * 15. A plan change in the middle of the next (upfront paid) cycle, from another upfront plan
     *     held before - each plan pays its own part of the prepaid month.
     */

    protected function mongoDate($str)
    {
        return new \MongoDB\BSON\UTCDateTime(strtotime($str) * 1000);
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
        $this->runCycleWithPrevious($this->defaultOptions);

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
        // the previous run charged a full month, not knowing the deactivation (the full fraction
        // legacy behavior emulates a run before the change was recorded)
        $this->runCycleWithPrevious($this->defaultOptions);

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
        // the subscriber held the upfront plan until 2025-11-25 and switched to the arrears
        // plan - the previous run (not knowing the change) charged December for the upfront plan
        $this->runCycleWithPrevious($this->defaultOptions);

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
        // no previous cycle ran at all - the tested run charges the missed cycle as is
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
        $this->runCycleWithPrevious($this->defaultOptions);

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
        // the previous run charged and discounted a full month, not knowing the discount end
        $this->runCycleWithPrevious($this->defaultOptions);

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
        // the previous run charged a full month, not knowing the deactivation and re-activation
        $this->runCycleWithPrevious($this->defaultOptions);

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
        // the previous run charged December fully, and gave no discount (it only starts in the
        // middle of December - after that run charge time)
        $this->runCycleWithPrevious($this->defaultOptions);

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
        // the previous run gave no December discount (it only starts in its middle, 2025-12-16) -
        // the deserved part is given by the reconciliation, once
        $this->assertEqualsWithDelta(-10 * 16 / 31, $currentCycleAmount, $this->epsilon);

        // plan: 16/31*100 - 100 (reconciliation) + 100 (January upfront), discounts as above
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202601', 'aid' => $aid));
        $this->assertEqualsWithDelta(90 * 16 / 31 - 10, $billrun['totals']['before_vat'], $this->epsilon);
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
        $this->runCycleWithPrevious($this->defaultOptions);

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
     * 13. An upfront plan that starts in the middle of the next (upfront paid) cycle (2026-07-15,
     *     cycle 202607 paying July upfront) - knowing the activation in advance, the prepaid month
     *     should be charged prorated from the activation (100 * 17/31).
     */
    public function testUpfrontPlanStartsMidPrepaidCycle()
    {
        $aid = 41010;
        $planName = 'UPFRONT_ADV_PLAN10';
        $this->defaultOptions['stamp'] = '202607';
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => $planName, 'upfront' => 1]);
        $this->runCycleWithPrevious($this->defaultOptions);

        // the plan is known to start on 2026-07-15 - the prepaid July is prorated (17 of 31 days)
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => 'flat', 'name' => $planName, 'aid' => $aid, 'charge_op' => 'charge'));
        $this->assertNotEmpty($planLine, 'upfront plan line was not created');
        $this->assertEqualsWithDelta(100 * 17 / 31, $planLine['aprice'], $this->epsilon);
        $this->assertEquals(strtotime('2026-07-15 00:00:00'), $planLine['prorated_start_date']->toDateTime()->getTimestamp());

        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $this->assertEqualsWithDelta(100 * 17 / 31, $billrun['totals']['before_vat'], $this->epsilon);
    }

    /**
     * 15. A plan change in the middle of the next (upfront paid) cycle (2026-07-15, cycle 202607
     *     paying July upfront), from another upfront plan held before this cycle - knowing the
     *     change in advance, each plan pays its own part of the prepaid month (14/31 + 17/31).
     */
    public function testUpfrontPlanChangeMidPrepaidCycle()
    {
        $aid = 41012;
        $oldPlan = 'UPFRONT_ADV_PLAN12A';
        $newPlan = 'UPFRONT_ADV_PLAN12B';
        $this->defaultOptions['stamp'] = '202607';
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => $oldPlan, 'upfront' => 1]);
        $this->tester->generatePlan(['name' => $newPlan, 'upfront' => 1]);
        $this->runCycleWithPrevious($this->defaultOptions);

        // the previous plan is known to end on 2026-07-15 - its prepaid July part is prorated
        // (14 of 31 days)
        $oldPlanLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => 'flat', 'name' => $oldPlan, 'aid' => $aid, 'charge_op' => 'charge'));
        $this->assertNotEmpty($oldPlanLine, 'previous plan upfront line was not created');
        $this->assertEqualsWithDelta(100 * 14 / 31, $oldPlanLine['aprice'], $this->epsilon);
        $this->assertEquals(strtotime('2026-07-01 00:00:00'), $oldPlanLine['prorated_start_date']->toDateTime()->getTimestamp());

        // the new plan is known to start on 2026-07-15 - its prepaid July part is prorated
        // (17 of 31 days)
        $newPlanLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => 'flat', 'name' => $newPlan, 'aid' => $aid, 'charge_op' => 'charge'));
        $this->assertNotEmpty($newPlanLine, 'new plan upfront line was not created');
        $this->assertEqualsWithDelta(100 * 17 / 31, $newPlanLine['aprice'], $this->epsilon);
        $this->assertEquals(strtotime('2026-07-15 00:00:00'), $newPlanLine['prorated_start_date']->toDateTime()->getTimestamp());

        // June was charged correctly for the previous plan - nothing to reconcile
        $reconcileLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => 'flat', 'charge_op' => 'refund', 'aid' => $aid));
        $this->assertEmpty($reconcileLine, 'the previous charge did not change - no reconciliation line was expected');

        // the two parts complete a full month of the same price
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $this->assertEqualsWithDelta(100, $billrun['totals']['before_vat'], $this->epsilon);
    }

    /**
     * 14. A discount of an upfront plan that starts in the middle of the next (upfront paid) cycle
     *     (2026-07-15, cycle 202607 paying July upfront) - the upfront discount is prorated from
     *     the discount start (10 * 17/31).
     */
    public function testUpfrontDiscountStartsMidPrepaidCycle()
    {
        $aid = 41011;
        $sid = 51011;
        $planName = 'UPFRONT_ADV_PLAN11';
        $this->defaultOptions['stamp'] = '202607';
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => $planName, 'upfront' => 1]);
        // June was correctly charged by the previous cycle run - nothing to reconcile
        $this->runCycleWithPrevious($this->defaultOptions);

        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => 'flat', 'name' => $planName, 'aid' => $aid, 'charge_op' => 'charge', "is_upfront" => true));
        $this->assertEqualsWithDelta(100, $planLine['aprice'], $this->epsilon);

        // the discount is known to start on 2026-07-15 - the prepaid July discount is prorated
        // from its start (17 of 31 days)
        $discountLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => 'credit', 'usaget' => 'discount', 'aid' => $aid));
        $this->assertNotEmpty($discountLine, 'upfront discount line was not created');
        $this->assertEqualsWithDelta(-10 * 17 / 31, $discountLine['aprice'], $this->epsilon);
        $this->assertEquals(strtotime('2026-07-15 00:00:00'), $discountLine['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime('2026-08-01 00:00:00'), $discountLine['discount_end']->toDateTime()->getTimestamp());

        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $this->assertEqualsWithDelta(100 - 10 * 17 / 31, $billrun['totals']['before_vat'], $this->epsilon);
    }

    /**
     * 7. The upfront charge amounts - the regular (arrears) charge of the next cycle, paid in
     *    advance, taking the changes that are already known into account. One account, a
     *    subscriber per fraction case, over two runs (202512 then 202601 - both knowing the
     *    changes in advance, so the tested run has nothing to reconcile):
     *    - 51120 ongoing plan: the full next month
     *    - 51121 deactivation within the upfront (January) cycle: prorated up to it
     *    - 51122 deactivation exactly at the upfront cycle start: no charge
     *    - 51123 deactivation within the current (December) cycle: no charge, and nothing to
     *      correct - the previous run knew it
     *    - 51124 activation within the current cycle: the full next month
     *    - 51125 activation within the current cycle and deactivation within the next: prorated
     */
    public function testUpfrontChargeFractionsByKnownDeactivation()
    {
        $aid = 41017;
        $planName = 'UPFRONT_ADV_PLAN17';
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => $planName, 'upfront' => 1]);

        // the previous run knows all the changes in advance - December is charged correctly per
        // subscriber, so the tested (202601) run only charges January upfront
        $this->defaultOptions['stamp'] = '202512';
        $this->tester->runCycle($this->defaultOptions);
        $this->defaultOptions['stamp'] = '202601';
        $this->tester->runCycle($this->defaultOptions);

        // ongoing plan - the full next month is charged
        $line = $this->tester->grabFromCollection('lines', array('billrun' => '202601', 'aid' => $aid, 'sid' => 51120, 'charge_op' => 'charge'));
        $this->assertNotEmpty($line, 'ongoing plan upfront line was not created');
        $this->assertEqualsWithDelta(100, $line['aprice'], $this->epsilon);
        $this->assertEquals(strtotime('2026-01-01 00:00:00'), $line['start']->toDateTime()->getTimestamp());

        // the deactivation is already known within the upfront cycle - a prorated charge
        // (the arrears convention - the deactivation day is not charged)
        $line = $this->tester->grabFromCollection('lines', array('billrun' => '202601', 'aid' => $aid, 'sid' => 51121, 'charge_op' => 'charge'));
        $this->assertNotEmpty($line, 'prorated upfront line was not created');
        $this->assertEqualsWithDelta(100 * 15 / 31, $line['aprice'], $this->epsilon);
        $this->assertEquals(strtotime('2026-01-16 00:00:00'), $line['end']->toDateTime()->getTimestamp());

        // the deactivation is exactly when the upfront cycle starts - nothing to charge
        $line = $this->tester->grabFromCollection('lines', array('billrun' => '202601', 'aid' => $aid, 'sid' => 51122));
        $this->assertEmpty($line, 'no charge was expected - the plan deactivates when the upfront cycle starts');

        // the deactivation is within the current (already charged) cycle - nothing to charge, and
        // nothing to correct (the previous run charged December prorated, knowing the deactivation)
        $line = $this->tester->grabFromCollection('lines', array('billrun' => '202601', 'aid' => $aid, 'sid' => 51123));
        $this->assertEmpty($line, 'no charge was expected - the plan deactivates within the current cycle');

        // activation in the middle of the current cycle - the full upfront month is charged (the
        // current cycle part was charged by the previous run, knowing the activation)
        $line = $this->tester->grabFromCollection('lines', array('billrun' => '202601', 'aid' => $aid, 'sid' => 51124, 'charge_op' => 'charge'));
        $this->assertNotEmpty($line, 'ongoing plan upfront line was not created (mid cycle activation)');
        $this->assertEqualsWithDelta(100, $line['aprice'], $this->epsilon);

        // activation in the middle of the current cycle and a known deactivation within the next
        // one - a prorated upfront month
        $line = $this->tester->grabFromCollection('lines', array('billrun' => '202601', 'aid' => $aid, 'sid' => 51125, 'charge_op' => 'charge'));
        $this->assertNotEmpty($line, 'prorated upfront line was not created (mid cycle activation)');
        $this->assertEqualsWithDelta(100 * 15 / 31, $line['aprice'], $this->epsilon);
        $this->assertEquals(strtotime('2026-01-16 00:00:00'), $line['end']->toDateTime()->getTimestamp());

        // the previous run knew all the changes - the tested run has no reconciliation lines
        $reconcileLine = $this->tester->grabFromCollection('lines', array('billrun' => '202601', 'aid' => $aid, 'charge_op' => 'refund'));
        $this->assertEmpty($reconcileLine, 'nothing to reconcile - the previous run knew all the changes');
    }

    /**
     * 16. An upfront plan known to start in the middle of the next (upfront paid) cycle
     *     (2026-08-15, cycle 202608 paying August upfront) is charged in advance, prorated from
     *     the activation (100 * 17/31). The activation is then cancelled (the account decided not
     *     to activate any plan after all) before the next cycle runs - cycle 202609 expects no
     *     August charge and fully refunds the advance one.
     *     NOTE - the account must be included in the cycle runs (getBillable) although no plan is
     *     active within their own windows - here force_accounts and the CRM mockup include it.
     */
    public function testUpfrontPlanCancelledAfterChargedInAdvance()
    {
        $aid = 41013;
        $planName = 'UPFRONT_ADV_PLAN13';
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => $planName, 'upfront' => 1]);

        $fixturePath = __DIR__ . '/../../../../docker/billrun-docker/mockup-servers/crm_data/' . $aid . '.json';
        $original = file_get_contents($fixturePath);
        try {
            // the plan is known to start on 2026-08-15 - cycle 202608 (paying August upfront)
            // charges it in advance, prorated from the activation (17 of 31 days)
            $this->defaultOptions['stamp'] = '202608';
            $this->tester->runCycle($this->defaultOptions);
            $planLine = $this->tester->grabFromCollection('lines', array('billrun' => '202608', 'type' => 'flat', 'name' => $planName, 'aid' => $aid, 'charge_op' => 'charge'));
            $this->assertNotEmpty($planLine, 'upfront plan line was not created');
            $this->assertEqualsWithDelta(100 * 17 / 31, $planLine['aprice'], $this->epsilon);
            $this->assertEquals(strtotime('2026-08-15 00:00:00'), $planLine['prorated_start_date']->toDateTime()->getTimestamp());

            // the account decides not to activate any plan after all - the activation is cancelled
            $cancelled = json_decode($original, true);
            foreach ($cancelled['data'] as &$entry) {
                if ($entry['type'] == 'subscriber') {
                    $entry['to'] = $entry['deactivation_date'] = $entry['plan_deactivation'] = '2026-08-15 00:00:00';
                }
            }
            file_put_contents($fixturePath, json_encode($cancelled, JSON_PRETTY_PRINT));

            // the next cycle expects no August charge - the advance one is fully refunded
            $this->defaultOptions['stamp'] = '202609';
            $this->tester->runCycle($this->defaultOptions);
        } finally {
            file_put_contents($fixturePath, $original);
        }

        $refundLine = $this->tester->grabFromCollection('lines', array('billrun' => '202609', 'type' => 'flat', 'charge_op' => 'refund', 'aid' => $aid));
        $this->assertNotEmpty($refundLine, 'refund line was not created');
        $this->assertEqualsWithDelta(-100 * 17 / 31, $refundLine['aprice'], $this->epsilon);
        $chargeLine = $this->tester->grabFromCollection('lines', array('billrun' => '202609', 'type' => 'flat', 'charge_op' => 'charge', 'aid' => $aid));
        $this->assertEmpty($chargeLine, 'no new upfront charge was expected');
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202609', 'aid' => $aid));
        $this->assertEqualsWithDelta(-100 * 17 / 31, $billrun['totals']['before_vat'], $this->epsilon);
    }

    /**
     * 18. An upfront plan known to start only beyond the next (upfront paid) cycle - the
     *     activation (2026-08-15) is after the prepaid cycle end (cycle 202607 pays July
     *     upfront), so the 202607 run charges nothing in advance and has nothing to reconcile
     *     (the mid cycle activation passes the recurrence gate of getRefund, but the expected
     *     charge of the reconciled cycle is empty). The activation becomes relevant only two
     *     runs ahead - cycle 202608 (paying August upfront) charges it in advance, prorated
     *     from the activation (100 * 17/31).
     */
    public function testUpfrontPlanActivationBeyondPrepaidCycleIsNotChargedInAdvance()
    {
        $aid = 41013;
        $planName = 'UPFRONT_ADV_PLAN13';
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => $planName, 'upfront' => 1]);

        // cycle 202607 (paying July upfront) - the activation is beyond the prepaid July, so
        // there is no advance charge and no reconciliation line
        $this->defaultOptions['stamp'] = '202607';
        $this->tester->runCycle($this->defaultOptions);
        $line = $this->tester->grabFromCollection('lines', array('billrun' => '202607', 'aid' => $aid));
        $this->assertEmpty($line, 'no line was expected while the activation is beyond the prepaid cycle');
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202607', 'aid' => $aid));
        $this->assertEmpty($billrun, 'no invoice was expected while the activation is beyond the prepaid cycle');

        // cycle 202608 (paying August upfront) - the activation is within the prepaid August,
        // charged in advance prorated from the activation (17 of 31 days)
        $this->defaultOptions['stamp'] = '202608';
        $this->tester->runCycle($this->defaultOptions);
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => '202608', 'type' => 'flat', 'name' => $planName, 'aid' => $aid, 'charge_op' => 'charge'));
        $this->assertNotEmpty($planLine, 'upfront plan line was not created');
        $this->assertEqualsWithDelta(100 * 17 / 31, $planLine['aprice'], $this->epsilon);
        $this->assertEquals(strtotime('2026-08-15 00:00:00'), $planLine['prorated_start_date']->toDateTime()->getTimestamp());
        $refundLine = $this->tester->grabFromCollection('lines', array('billrun' => '202608', 'aid' => $aid, 'charge_op' => 'refund'));
        $this->assertEmpty($refundLine, 'nothing was charged before - no reconciliation line was expected');
    }

    /**
     * 19. The same upfront plan held twice within the prepaid cycle with a gap in between
     *     (deactivation 2026-07-10, re-activation 2026-07-20, cycle 202607 paying July upfront):
     *     - each revision pays its own part of the prepaid July (100 * 9/31 + 100 * 12/31)
     *     - both revision dates then change retroactively before the next run - the gap moves to
     *       [Jul 5, Jul 12) - cycle 202608 reconciles each paid part against its revision's new
     *       dates: the first part is credited 5 days (100 * -5/31), the re-activated part is
     *       charged 8 more days (100 * 8/31), and August is paid upfront in full
     */
    public function testUpfrontPlanGapBetweenRevisionsAndRetroactiveDateChange()
    {
        $aid = 41018;
        $planName = 'UPFRONT_ADV_PLAN18';
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => $planName, 'upfront' => 1]);

        // June was charged correctly by the previous cycle run - nothing to reconcile in 202607
        $this->defaultOptions['stamp'] = '202607';
        $this->runCycleWithPrevious($this->defaultOptions);

        // each revision pays its own part of the prepaid July
        $chargeLines = iterator_to_array(\Billrun_Factory::db()->linesCollection()
                ->query(array('billrun' => '202607', 'aid' => $aid, 'type' => 'flat', 'charge_op' => 'charge'))
                ->cursor());
        $this->assertCount(2, $chargeLines, 'each plan revision pays its own part of the prepaid month');
        usort($chargeLines, function ($a, $b) { return $a['prorated_start_date']->sec - $b['prorated_start_date']->sec; });
        // [Jul 1, Jul 10) - the arrears convention - the deactivation day is not charged
        $this->assertEquals(strtotime('2026-07-01 00:00:00'), $chargeLines[0]['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime('2026-07-10 00:00:00'), $chargeLines[0]['end']->toDateTime()->getTimestamp());
        $this->assertEqualsWithDelta(100 * 9 / 31, $chargeLines[0]['aprice'], $this->epsilon);

        // [Jul 20, Aug 1) - the re-activated revision part
        $this->assertEquals(strtotime('2026-07-20 00:00:00'), $chargeLines[1]['start']->toDateTime()->getTimestamp());
        $this->assertEqualsWithDelta(100 * 12 / 31, $chargeLines[1]['aprice'], $this->epsilon);

        $reconcileLine = $this->tester->grabFromCollection('lines', array('billrun' => '202607', 'aid' => $aid, 'charge_op' => 'refund'));
        $this->assertEmpty($reconcileLine, 'June was charged correctly - no reconciliation line was expected');
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202607', 'aid' => $aid));
        $this->assertEqualsWithDelta(100 * 21 / 31, $billrun['totals']['before_vat'], $this->epsilon);

        // both revision dates change retroactively before the next run - the deactivation moves
        // back to 2026-07-05 and the re-activation moves back to 2026-07-12
        $fixturePath = __DIR__ . '/../../../../docker/billrun-docker/mockup-servers/crm_data/' . $aid . '.json';
        $original = file_get_contents($fixturePath);
        try {
            $changed = str_replace(
                ['2026-07-10 00:00:00', '2026-07-20 00:00:00'],
                ['2026-07-05 00:00:00', '2026-07-12 00:00:00'],
                $original);
            file_put_contents($fixturePath, $changed);

            // cycle 202608 - each prepaid July part is reconciled against its revision's new dates,
            // and August is paid upfront
            $this->defaultOptions['stamp'] = '202608';
            $this->tester->runCycle($this->defaultOptions);
        } finally {
            file_put_contents($fixturePath, $original);
        }

        $upfrontLine = $this->tester->grabFromCollection('lines', array('billrun' => '202608', 'aid' => $aid, 'type' => 'flat', 'charge_op' => 'charge'));
        $this->assertNotEmpty($upfrontLine, 'August upfront line was not created');
        $this->assertEqualsWithDelta(100, $upfrontLine['aprice'], $this->epsilon);
        $this->assertEquals(strtotime('2026-08-01 00:00:00'), $upfrontLine['start']->toDateTime()->getTimestamp());

        // the reconciliation corrects each paid July part to its revision's new dates:
        // [Jul 1, Jul 10) -> [Jul 1, Jul 5) credits 5 days, [Jul 20, Aug 1) -> [Jul 12, Aug 1)
        // charges 8 more days
        $reconcileLines = iterator_to_array(\Billrun_Factory::db()->linesCollection()
                ->query(array('billrun' => '202608', 'aid' => $aid, 'charge_op' => 'refund'))
                ->cursor());
        $reconciled = array_sum(array_map(function ($line) { return $line['aprice']; }, $reconcileLines));
        $this->assertEqualsWithDelta(100 * (8 - 5) / 31, $reconciled, $this->epsilon);

        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202608', 'aid' => $aid));
        $this->assertEqualsWithDelta(100 + 100 * (8 - 5) / 31, $billrun['totals']['before_vat'], $this->epsilon);
    }

    /**
     * 20. Like 19. but the period between the two revisions is held by another upfront plan
     *     instead of a gap (plan A until 2026-07-10, plan B until 2026-07-20, then plan A again,
     *     cycle 202607 paying July upfront). The plans are priced differently (100 / 62) so a
     *     reconciliation against the wrong plan's line cannot come out right by accident:
     *     - each plan revision pays its own part of the prepaid July, by its own price
     *       (100 * 9/31 + 62 * 10/31 + 100 * 12/31)
     *     - both change dates then move retroactively before the next run - the middle plan
     *       period moves to [Jul 5, Jul 12) - cycle 202608 reconciles each plan only against its
     *       own lines and price: the middle plan is credited the [Jul 12, Jul 20) it no longer
     *       holds (62 * -3/31), the held plan nets the same 3 days back at its own price
     *       (100 * 3/31), and August is paid upfront in full by the held plan
     */
    public function testAnotherPlanBetweenRevisionsAndRetroactiveDateChange()
    {
        $aid = 41020;
        $heldPlan = 'UPFRONT_ADV_PLAN20A';
        $middlePlan = 'UPFRONT_ADV_PLAN20B';
        $middlePlanPrice = 62;
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => $heldPlan, 'upfront' => 1]);
        $this->tester->generatePlan(['name' => $middlePlan, 'upfront' => 1, 'price' => [['price' => $middlePlanPrice, 'from' => 0, 'to' => 'UNLIMITED']]]);

        // June was charged correctly by the previous cycle run - nothing to reconcile in 202607
        $this->defaultOptions['stamp'] = '202607';
        $this->runCycleWithPrevious($this->defaultOptions);

        // each revision of the held plan pays its own part of the prepaid July
        $heldPlanLines = iterator_to_array(\Billrun_Factory::db()->linesCollection()
                ->query(array('billrun' => '202607', 'aid' => $aid, 'type' => 'flat', 'name' => $heldPlan, 'charge_op' => 'charge'))
                ->cursor());
        $this->assertCount(2, $heldPlanLines, 'each revision of the held plan pays its own part of the prepaid month');
        usort($heldPlanLines, function ($a, $b) { return $a['prorated_start_date']->sec - $b['prorated_start_date']->sec; });
        $this->assertEquals(strtotime('2026-07-01 00:00:00'), $heldPlanLines[0]['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime('2026-07-10 00:00:00'), $heldPlanLines[0]['end']->toDateTime()->getTimestamp());
        $this->assertEqualsWithDelta(100 * 9 / 31, $heldPlanLines[0]['aprice'], $this->epsilon);
        $this->assertEquals(strtotime('2026-07-20 00:00:00'), $heldPlanLines[1]['start']->toDateTime()->getTimestamp());
        $this->assertEqualsWithDelta(100 * 12 / 31, $heldPlanLines[1]['aprice'], $this->epsilon);
        // and the middle plan pays its [Jul 10, Jul 20), by its own price
        $middlePlanLine = $this->tester->grabFromCollection('lines', array('billrun' => '202607', 'aid' => $aid, 'type' => 'flat', 'name' => $middlePlan, 'charge_op' => 'charge'));
        $this->assertNotEmpty($middlePlanLine, 'middle plan upfront line was not created');
        $this->assertEquals(strtotime('2026-07-10 00:00:00'), $middlePlanLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime('2026-07-20 00:00:00'), $middlePlanLine['end']->toDateTime()->getTimestamp());
        $this->assertEqualsWithDelta($middlePlanPrice * 10 / 31, $middlePlanLine['aprice'], $this->epsilon);

        $reconcileLine = $this->tester->grabFromCollection('lines', array('billrun' => '202607', 'aid' => $aid, 'charge_op' => 'refund'));
        $this->assertEmpty($reconcileLine, 'June was charged correctly - no reconciliation line was expected');
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202607', 'aid' => $aid));
        $this->assertEqualsWithDelta(100 * 21 / 31 + $middlePlanPrice * 10 / 31, $billrun['totals']['before_vat'], $this->epsilon);

        // both change dates move retroactively before the next run - the middle plan period
        // moves to [Jul 5, Jul 12)
        $fixturePath = __DIR__ . '/../../../../docker/billrun-docker/mockup-servers/crm_data/' . $aid . '.json';
        $original = file_get_contents($fixturePath);
        try {
            $changed = str_replace(
                ['2026-07-10 00:00:00', '2026-07-20 00:00:00'],
                ['2026-07-05 00:00:00', '2026-07-12 00:00:00'],
                $original);
            file_put_contents($fixturePath, $changed);

            // cycle 202608 - each prepaid July part is reconciled against its plan's new dates,
            // and August is paid upfront
            $this->defaultOptions['stamp'] = '202608';
            $this->tester->runCycle($this->defaultOptions);
        } finally {
            file_put_contents($fixturePath, $original);
        }

        // August belongs to the held (re-activated) plan only
        $upfrontLine = $this->tester->grabFromCollection('lines', array('billrun' => '202608', 'aid' => $aid, 'type' => 'flat', 'charge_op' => 'charge'));
        $this->assertNotEmpty($upfrontLine, 'August upfront line was not created');
        $this->assertEquals($heldPlan, $upfrontLine['name']);
        $this->assertEqualsWithDelta(100, $upfrontLine['aprice'], $this->epsilon);
        $this->assertEquals(strtotime('2026-08-01 00:00:00'), $upfrontLine['start']->toDateTime()->getTimestamp());

        // the middle plan reconciles against its own single line - it is credited the
        // [Jul 12, Jul 20) it no longer holds, by its own price
        $middlePlanRefund = $this->tester->grabFromCollection('lines', array('billrun' => '202608', 'aid' => $aid, 'name' => $middlePlan, 'charge_op' => 'refund'));
        $this->assertNotEmpty($middlePlanRefund, 'middle plan reconciliation line was not created');
        $this->assertEqualsWithDelta(-$middlePlanPrice * 3 / 31, $middlePlanRefund['aprice'], $this->epsilon);
        $this->assertEquals(strtotime('2026-07-12 00:00:00'), $middlePlanRefund['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime('2026-07-20 00:00:00'), $middlePlanRefund['end']->toDateTime()->getTimestamp());

        // the held plan reconciles only against its own two lines (see 19. - the first record
        // consumes them: [Jul 1, Jul 10) is corrected to [Jul 1, Jul 5), the unmatched
        // [Jul 20, Aug 1) is charged back, and the re-activated [Jul 12, Aug 1) is charged as
        // is) - a net of the same 3 days (-5/31 - 12/31 + 20/31)
        $heldPlanRefunds = iterator_to_array(\Billrun_Factory::db()->linesCollection()
                ->query(array('billrun' => '202608', 'aid' => $aid, 'name' => $heldPlan, 'charge_op' => 'refund'))
                ->cursor());
        $this->assertCount(3, $heldPlanRefunds);
        $reconciled = array_sum(array_map(function ($line) { return $line['aprice']; }, $heldPlanRefunds));
        $this->assertEqualsWithDelta(100 * 3 / 31, $reconciled, $this->epsilon);

        // August (100) + the moved 3 days - regained by the held plan at 100, credited back by
        // the middle plan at its own 62
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202608', 'aid' => $aid));
        $this->assertEqualsWithDelta(100 + (100 - $middlePlanPrice) * 3 / 31, $billrun['totals']['before_vat'], $this->epsilon);
    }

    /**
     * 17. A single discount over both an arrears plan (one subscriber) and an upfront plan
     *     (another subscriber) with simultaneous_limit = 1 - the discount CDRs of a run are capped
     *     across its two passes: the current cycle (arrears, December) CDR consumes the limit
     *     first, so the upfront pass (the January line of the upfront plan) counts it and creates
     *     none. Without the cross pass counting the run would create two CDRs.
     */
    public function testDiscountSimultaneousLimitOverArrearsAndUpfront()
    {
        $aid = 41014;
        $arrearsSid = 51014;
        $upfrontSid = 51015;
        $arrearsPlan = 'ARREARS_ADV_PLAN14';
        $upfrontPlan = 'UPFRONT_ADV_PLAN14';
        $this->defaultOptions['stamp'] = '202601';
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => $arrearsPlan]);
        $this->tester->generatePlan(['name' => $upfrontPlan, 'upfront' => 1]);
        $this->tester->generateDiscount([
            'key' => 'DIS_SIMULTANEOUS_' . time(),
            'from' => new Mongodloid_Date(strtotime('2025-12-01 00:00:00')),
            'to' => new Mongodloid_Date(strtotime('2027-01-01 00:00:00')),
            'simultaneous_limit' => 1,
            'params' => [
                'conditions' => [
                    [
                        'subscriber' => [
                            [
                                'fields' => [
                                    [
                                        'field' => 'plan',
                                        'op' => 'in',
                                        'value' => [$arrearsPlan, $upfrontPlan],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'subject' => [
                'plan' => [
                    $arrearsPlan => ['value' => 10],
                    $upfrontPlan => ['value' => 10],
                ],
            ],
        ]);
        $this->runCycleWithPrevious($this->defaultOptions);

        // the previous run discounted the upfront (December) line - the only line the discount
        // (starting 2025-12-01) could reach there, so its limit was free
        $previousDiscount = $this->tester->grabFromCollection('lines', array('billrun' => '202512', 'type' => 'credit', 'usaget' => 'discount', 'aid' => $aid));
        $this->assertNotEmpty($previousDiscount, 'previous run upfront discount CDR was not created');
        $this->assertEquals($upfrontSid, $previousDiscount['sid']);
        $this->assertEqualsWithDelta(-10, $previousDiscount['aprice'], $this->epsilon);

        // the upfront (January) line exists - only the simultaneous limit keeps it undiscounted
        $upfrontLine = $this->tester->grabFromCollection('lines', array('billrun' => '202601', 'type' => 'flat', 'name' => $upfrontPlan, 'aid' => $aid, 'is_upfront' => true));
        $this->assertNotEmpty($upfrontLine, 'upfront plan line was not created');

        // exactly one discount CDR in the whole run - the current cycle (December) discount of the
        // arrears line, the upfront pass created none
        $discountCdrs = iterator_to_array(\Billrun_Factory::db()->linesCollection()
                ->query(array('billrun' => '202601', 'type' => 'credit', 'usaget' => 'discount', 'aid' => $aid))
                ->cursor());
        $this->assertCount(1, $discountCdrs, 'exactly one discount CDR was expected (simultaneous_limit = 1)');
        $discountCdr = reset($discountCdrs);
        $this->assertEquals($arrearsSid, $discountCdr['sid']);
        $this->assertEqualsWithDelta(-10, $discountCdr['aprice'], $this->epsilon);

        // arrears December (100) + upfront January (100) - the single December discount (10). the
        // December discount of the upfront plan was already given by the previous run, and the
        // reconciliation found it matching the expected one - no correction
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202601', 'aid' => $aid));
        $this->assertEqualsWithDelta(190, $billrun['totals']['before_vat'], $this->epsilon);
    }

    /**
     * 21. Like 17. but without a simultaneous limit - the same discount over both the arrears
     *     plan (one subscriber) and the upfront plan (another subscriber) creates both CDRs in
     *     one run: the current cycle (arrears, December) one and the upfront (January) one.
     */
    public function testDiscountOverArrearsAndUpfrontWithoutSimultaneousLimit()
    {
        $aid = 41014;
        $arrearsSid = 51014;
        $upfrontSid = 51015;
        $arrearsPlan = 'ARREARS_ADV_PLAN14';
        $upfrontPlan = 'UPFRONT_ADV_PLAN14';
        $this->defaultOptions['stamp'] = '202601';
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => $arrearsPlan]);
        $this->tester->generatePlan(['name' => $upfrontPlan, 'upfront' => 1]);
        $this->tester->generateDiscount([
            'key' => 'DIS_NO_LIMIT_' . time(),
            'from' => new Mongodloid_Date(strtotime('2025-12-01 00:00:00')),
            'to' => new Mongodloid_Date(strtotime('2027-01-01 00:00:00')),
            'params' => [
                'conditions' => [
                    [
                        'subscriber' => [
                            [
                                'fields' => [
                                    [
                                        'field' => 'plan',
                                        'op' => 'in',
                                        'value' => [$arrearsPlan, $upfrontPlan],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'subject' => [
                'plan' => [
                    $arrearsPlan => ['value' => 10],
                    $upfrontPlan => ['value' => 10],
                ],
            ],
        ]);
        $this->runCycleWithPrevious($this->defaultOptions);

        // the previous run discounted the upfront (December) line - the only line the discount
        // (starting 2025-12-01) could reach there
        $previousDiscount = $this->tester->grabFromCollection('lines', array('billrun' => '202512', 'type' => 'credit', 'usaget' => 'discount', 'aid' => $aid));
        $this->assertNotEmpty($previousDiscount, 'previous run upfront discount CDR was not created');
        $this->assertEquals($upfrontSid, $previousDiscount['sid']);
        $this->assertEqualsWithDelta(-10, $previousDiscount['aprice'], $this->epsilon);

        // with no limit both passes create their CDR - the current cycle (December) discount of
        // the arrears line and the upfront (January) discount of the upfront line
        $discountCdrs = iterator_to_array(\Billrun_Factory::db()->linesCollection()
                ->query(array('billrun' => '202601', 'type' => 'credit', 'usaget' => 'discount', 'aid' => $aid))
                ->cursor());
        $this->assertCount(2, $discountCdrs, 'two discount CDRs were expected (no simultaneous limit)');
        usort($discountCdrs, function ($a, $b) { return $a['sid'] - $b['sid']; });
        $this->assertEquals($arrearsSid, $discountCdrs[0]['sid']);
        $this->assertEqualsWithDelta(-10, $discountCdrs[0]['aprice'], $this->epsilon);
        $this->assertEquals(strtotime('2025-12-01 00:00:00'), $discountCdrs[0]['discount_start']->toDateTime()->getTimestamp());//arrears
        $this->assertEquals($upfrontSid, $discountCdrs[1]['sid']);
        $this->assertEqualsWithDelta(-10, $discountCdrs[1]['aprice'], $this->epsilon);
        // the upfront CDR discounts the prepaid January, not December again
        $this->assertEquals(strtotime('2026-01-01 00:00:00'), $discountCdrs[1]['discount_start']->toDateTime()->getTimestamp());//upfront

        // arrears December (100) + upfront January (100) - both discounts (10 + 10). the December
        // discount of the upfront plan was already given by the previous run and matches the
        // expected one - no correction
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202601', 'aid' => $aid));
        $this->assertEqualsWithDelta(180, $billrun['totals']['before_vat'], $this->epsilon);
    }
}

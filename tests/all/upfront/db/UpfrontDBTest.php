<?php

class UpfrontDBTest extends \Codeception\Test\Unit
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
        $this->tester->setTimezone('UTC');
        $this->tester->enableDBModeSettings();
        $this->tester->cleanDB();
        \Billrun_Plans_Charge_Upfront::resetReconciliationCache();
        // the discounts static cache is keyed by cycle - tests sharing cycle keys must not see
        // each other's (cleaned) discounts
        \Billrun_DiscountManager::resetDiscountsCache();
    }

    protected function _after()
    {
       $this->tester->restoreTimezone();
    }

    public function testDiscountFinishPreviousMonthOnUpfronInheritedPlan_DB_1()
    {
        /*
        upfront plan  discount with "proration": "inherited" and plan not finish
        but discount finish in the previous month  (for both Prorate charge on termination = false /true)
        -> expected proration charge from the termination of the discount + not discount on the current cycle 0
        */
        $this->tester->createAccountWithAllMandatoryCustomFields();
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $aid = $account['aid'];
        $this->defaultOptions['stamp'] = '202512';
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = "UPFRONT_PLAN_PORATED_BRCD_5055";
        $this->tester->generatePlan(['name' => $planName, "upfront" => 1, 'price'=>[["price" => 100, "from" => 0, "to" => "UNLIMITED"]]]);//Prorate charge on termination = true
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];

        $discount_name = "DIS_B2C_" . time();
        $this->tester->generateDiscount([
            "from" => new Mongodloid_Date(strtotime("2025-08-01T21:00:00Z")),
            "to" => new Mongodloid_Date(strtotime("2025-11-06T05:00:00Z")),
            "params" => [
              "conditions" => [
                      [
                          "subscriber" => [
                              [
                                  "fields" => [
                                      [
                                          "field" => "plan",
                                          "op" => "in",
                                          "value" => [$plan['name']]
                                      ]
                                  ]
                              ]
                          ]
                      ]
              ]],
              "subject" => [
                  "plan" => [
                      $plan['name'] => ["value" => 20]
                  ]
              ],
              'key'=> $discount_name,

          ]);
          $this->tester->generateSubscriber([
            'aid' => $account['aid'],
            "from" => "2018-07-04T21:00:00Z",
            "plan" => $plan['name'],

        ]);
        $subscriber = json_decode($this->tester->grabResponse(), true)['entity'];


        $this->tester->runCycleWithPrevious($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "flat", "name"=> $planName, 'aid' => $aid));
        $discountLineUpfront = $this->tester->grabFromCollection('lines', array('billrun' => $this->defaultOptions['stamp'], 'type' => "credit", "usaget" => "discount", 'aid' => $aid, 'charge_op' => 'refund'));
        //flat-100 discount(16)(finish in 2025-11-06T05:00:00Z) - 6/30*20 
        $this->assertEqualsWithDelta(116, $billrun['totals']['before_vat'],$this->epsilon);
        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2026-01-01 00:00:00"), $planLine['end']->toDateTime()->getTimestamp());
        
        $this->assertEquals(strtotime("2025-11-06T05:00:00Z"), $discountLineUpfront['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $discountLineUpfront['discount_end']->toDateTime()->getTimestamp());
    }

    /**
     * BRCD-5421 - an upfront plan known to start in the middle of the next (upfront paid) cycle
     * (2026-08-15, cycle 202608 paying August upfront) is charged in advance, prorated from the
     * activation (100 * 17/31). The activation is then cancelled (the account decided not to
     * activate any plan after all) before the next cycle runs - cycle 202609 expects no August
     * charge and fully refunds the advance one.
     * NOTE - the account must be included in the cycle runs (getBillable) although no plan is
     * active within their own windows - here the subscriber revision starts within the 202608
     * window (only the plan activation is scheduled to 2026-08-15) so the billable query picks it
     * up, and the cancelled revision still overlaps the 202609 window.
     */
    public function testUpfrontPlanCancelledAfterChargedInAdvance_DB()
    {
        $this->tester->createAccountWithAllMandatoryCustomFields();
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $aid = $account['aid'];
        $this->defaultOptions['force_accounts'] = [$aid];
        $planName = 'UPFRONT_ADV_PLAN_DB2';
        $this->tester->generatePlan(['name' => $planName, 'upfront' => 1, 'price' => [["price" => 100, "from" => 0, "to" => "UNLIMITED"]]]);
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->tester->generateSubscriber([
            'aid' => $aid,
            'from' => '2026-07-01T00:00:00Z',
            'plan' => $plan['name'],
        ]);
        // the plan activation is known in advance - scheduled to 2026-08-15
        $activation = new Mongodloid_Date(strtotime('2026-08-15 00:00:00'));
        $subscribersCollection = \Billrun_Factory::db()->subscribersCollection();
        $subscriberQuery = array('aid' => $aid, 'type' => 'subscriber');
        $subscribersCollection->update($subscriberQuery, array('$set' => array('plan_activation' => $activation)), array('multiple' => true));

        // cycle 202608 (paying August upfront) charges the plan in advance, prorated from the
        // activation (17 of 31 days)
        $this->defaultOptions['stamp'] = '202608';
        $this->tester->runCycle($this->defaultOptions);
        $planLine = $this->tester->grabFromCollection('lines', array('billrun' => '202608', 'type' => 'flat', 'name' => $planName, 'aid' => $aid, 'charge_op' => 'charge'));
        $this->assertNotEmpty($planLine, 'upfront plan line was not created');
        $this->assertEqualsWithDelta(100 * 17 / 31, $planLine['aprice'], $this->epsilon);
        $this->assertEquals(strtotime('2026-08-15 00:00:00'), $planLine['prorated_start_date']->toDateTime()->getTimestamp());

        // the account decides not to activate any plan after all - the activation is cancelled
        $subscribersCollection->update($subscriberQuery, array('$set' => array(
            'to' => $activation,
            'deactivation_date' => $activation,
            'plan_deactivation' => $activation,
        )), array('multiple' => true));

        // the next cycle expects no August charge - the advance one is fully refunded
        $this->defaultOptions['stamp'] = '202609';
        $this->tester->runCycle($this->defaultOptions);
        $refundLine = $this->tester->grabFromCollection('lines', array('billrun' => '202609', 'type' => 'flat', 'charge_op' => 'refund', 'aid' => $aid));
        $this->assertNotEmpty($refundLine, 'refund line was not created');
        $this->assertEqualsWithDelta(-100 * 17 / 31, $refundLine['aprice'], $this->epsilon);
        $chargeLine = $this->tester->grabFromCollection('lines', array('billrun' => '202609', 'type' => 'flat', 'charge_op' => 'charge', 'aid' => $aid));
        $this->assertEmpty($chargeLine, 'no new upfront charge was expected');
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202609', 'aid' => $aid));
        $this->assertEqualsWithDelta(-100 * 17 / 31, $billrun['totals']['before_vat'], $this->epsilon);
    }

    /**
     * An account holding upfront plan A until 2026-08-15 and upfront plan B from then on (the
     * change is recorded in advance), cycle 202608 paying August upfront. The plan B revision
     * only starts within the next (upfront paid) cycle.
     * @return int the aid
     */
    protected function preparePlanChangeMidNextCycleAccount()
    {
        $this->tester->createAccountWithAllMandatoryCustomFields();
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $aid = $account['aid'];
        $this->defaultOptions['force_accounts'] = [$aid];
        $this->tester->generatePlan(['name' => 'UPFRONT_ADV_PLAN_DB3A', 'upfront' => 1, 'price' => [["price" => 100, "from" => 0, "to" => "UNLIMITED"]]]);
        $this->tester->generatePlan(['name' => 'UPFRONT_ADV_PLAN_DB3B', 'upfront' => 1, 'price' => [["price" => 200, "from" => 0, "to" => "UNLIMITED"]]]);
        $this->tester->generateSubscriber([
            'aid' => $aid,
            'from' => '2026-07-01T00:00:00Z',
            'plan' => 'UPFRONT_ADV_PLAN_DB3A',
        ]);
        // the plan change is recorded in advance - the plan A revision closes at 2026-08-15 and a
        // plan B revision opens from then on
        $changeDate = new Mongodloid_Date(strtotime('2026-08-15 00:00:00'));
        $subscribersCollection = \Billrun_Factory::db()->subscribersCollection();
        $subscriberQuery = array('aid' => $aid, 'type' => 'subscriber');
        $revA = $subscribersCollection->query($subscriberQuery)->cursor()->current()->getRawData();
        $subscribersCollection->update($subscriberQuery, array('$set' => array(
            'to' => $changeDate,
            'plan_deactivation' => $changeDate,
        )), array('multiple' => true));
        $revB = $revA;
        unset($revB['_id']);
        $revB['from'] = $changeDate;
        $revB['plan_activation'] = $changeDate;
        $revB['plan'] = 'UPFRONT_ADV_PLAN_DB3B';
        $revB['to'] = new Mongodloid_Date(strtotime('2200-01-01 00:00:00'));
        $subscribersCollection->insert($revB);
        return $aid;
    }

    /**
     * BRCD-5421 - by default the billable window is the current cycle only, so the plan B
     * revision (starting in the middle of the next cycle) is not returned by getBillable - the
     * run only knows plan A ends at 2026-08-15 and pays its part of August in advance.
     */
    public function testPlanChangeMidPrepaidCycleIsNotSeenWithoutNextCycleBillable_DB()
    {
        $aid = $this->preparePlanChangeMidNextCycleAccount();

        $this->defaultOptions['stamp'] = '202608';
        $this->tester->runCycle($this->defaultOptions);

        $planALine = $this->tester->grabFromCollection('lines', array('billrun' => '202608', 'type' => 'flat', 'name' => 'UPFRONT_ADV_PLAN_DB3A', 'aid' => $aid, 'charge_op' => 'charge', 'is_upfront' => true));
        $this->assertNotEmpty($planALine, 'plan A upfront line was not created');
        $this->assertEqualsWithDelta(100 * 14 / 31, $planALine['aprice'], $this->epsilon);

        // the plan B revision only exists in the next cycle - the billable query does not return
        // it, so its part of August is not charged (it will be settled by the next cycle
        // reconciliation)
        $planBLine = $this->tester->grabFromCollection('lines', array('billrun' => '202608', 'type' => 'flat', 'name' => 'UPFRONT_ADV_PLAN_DB3B', 'aid' => $aid));
        $this->assertEmpty($planBLine, 'plan B was not expected to be known to the run');

        // no previous run paid July upfront - the reconciliation charges plan A's July as missed
        $reconcileLine = $this->tester->grabFromCollection('lines', array('billrun' => '202608', 'type' => 'flat', 'charge_op' => 'refund', 'aid' => $aid));
        $this->assertNotEmpty($reconcileLine, 'the missed July reconciliation line was not created');
        $this->assertEqualsWithDelta(100, $reconcileLine['aprice'], $this->epsilon);

        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202608', 'aid' => $aid));
        $this->assertEqualsWithDelta(100 + 100 * 14 / 31, $billrun['totals']['before_vat'], $this->epsilon);
    }

    /**
     * BRCD-5421 - like the previous test, with billrun.upfront.billable_next_cycle set: the
     * billable window is extended by one cycle, the plan B revision is returned, and its part of
     * the prepaid August is charged in advance as well.
     */
    public function testBillableNextCycleChargesPlanChangeMidPrepaidCycleInAdvance_DB()
    {
        $aid = $this->preparePlanChangeMidNextCycleAccount();

        \Billrun_Factory::config()->setConfigValue('billrun.upfront.billable_next_cycle', true);
        try {
            $this->defaultOptions['stamp'] = '202608';
            $this->tester->runCycle($this->defaultOptions);
        } finally {
            \Billrun_Factory::config()->setConfigValue('billrun.upfront.billable_next_cycle', false);
        }

        $planALine = $this->tester->grabFromCollection('lines', array('billrun' => '202608', 'type' => 'flat', 'name' => 'UPFRONT_ADV_PLAN_DB3A', 'aid' => $aid, 'charge_op' => 'charge', 'is_upfront' => true));
        $this->assertNotEmpty($planALine, 'plan A upfront line was not created');
        $this->assertEqualsWithDelta(100 * 14 / 31, $planALine['aprice'], $this->epsilon);

        // the extended window returns the plan B revision - its [Aug 15, Sep 1) part of the
        // prepaid August is charged in advance, prorated from the activation
        $planBLine = $this->tester->grabFromCollection('lines', array('billrun' => '202608', 'type' => 'flat', 'name' => 'UPFRONT_ADV_PLAN_DB3B', 'aid' => $aid, 'charge_op' => 'charge', 'is_upfront' => true));
        $this->assertNotEmpty($planBLine, 'plan B upfront line was not created');
        $this->assertEqualsWithDelta(200 * 17 / 31, $planBLine['aprice'], $this->epsilon);
        $this->assertEquals(strtotime('2026-08-15 00:00:00'), $planBLine['prorated_start_date']->toDateTime()->getTimestamp());

        // no previous run paid July upfront - the reconciliation charges plan A's July as missed
        // (plan B holds nothing within July - the extended window does not affect the
        // reconciliation)
        $reconcileLine = $this->tester->grabFromCollection('lines', array('billrun' => '202608', 'type' => 'flat', 'charge_op' => 'refund', 'aid' => $aid));
        $this->assertNotEmpty($reconcileLine, 'the missed July reconciliation line was not created');
        $this->assertEqualsWithDelta(100, $reconcileLine['aprice'], $this->epsilon);

        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202608', 'aid' => $aid));
        $this->assertEqualsWithDelta(100 + 100 * 14 / 31 + 200 * 17 / 31, $billrun['totals']['before_vat'], $this->epsilon);
    }
}
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

    /**
     * BRCD-5421 - the upfront charges are reconciled against the previous cycle run lines. the
     * legacy expectations assume the previous run did NOT know the changes in advance - so it runs
     * with the full fraction (legacy) upfront behavior, and the tested cycle runs with the default
     * (knowing the changes in advance) behavior.
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
        // keep only the previous run lines the reconciliation reads, and drop their name - the
        // tests (written for a single run) grab lines loosely and must match the tested run only
        $linesCollection = \Billrun_Factory::db()->linesCollection();
        $previousLinesQuery = array('billrun' => $previousOptions['stamp'], 'source' => 'billrun');
        $linesCollection->remove(array_merge($previousLinesQuery, array('$or' => array(
            array('is_upfront' => array('$ne' => true)),
            array('charge_op' => 'refund'),
        ))));
        $linesCollection->update($previousLinesQuery, array('$unset' => array('name' => '')), array('multiple' => true));
        $this->tester->runCycle($options);
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


        $this->runCycleWithPrevious($this->defaultOptions);
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
}
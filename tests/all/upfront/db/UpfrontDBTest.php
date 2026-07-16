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
}
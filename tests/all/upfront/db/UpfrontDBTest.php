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


        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => $this->defaultOptions['stamp'], 'aid' => $aid));
        $planLine = $this->tester->grabFromCollection('lines', array('type' => "flat", "name"=> $planName, 'aid' => $aid));
        $discountLineUpfront = $this->tester->grabFromCollection('lines', array('type' => "credit", "usaget" => "discount", 'aid' => $aid, 'is_upfront' => true));
        //flat-100 discount(16)(finish in 2025-11-06T05:00:00Z) - 6/30*20 
        $this->assertEqualsWithDelta(116, $billrun['totals']['before_vat'],$this->epsilon);
        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $planLine['start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2026-01-01 00:00:00"), $planLine['end']->toDateTime()->getTimestamp());
        
        $this->assertEquals(strtotime("2025-11-06T05:00:00Z"), $discountLineUpfront['discount_start']->toDateTime()->getTimestamp());
        $this->assertEquals(strtotime("2025-12-01 00:00:00"), $discountLineUpfront['discount_end']->toDateTime()->getTimestamp());
    }
}
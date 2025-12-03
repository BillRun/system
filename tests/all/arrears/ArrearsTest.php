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
        $planName = 'PLAN_5080';
        $discount_name = 'DIS_PLAN_5080';
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
        $discountLine1 = $this->tester->grabFromCollection('lines', array('type' => "credit", "usaget" => "discount", 'aid' => $aid, 'key'=>'SUBSCRIBER_DISCOUNT_1_PLAN_5076'));
        $discountLine2 = $this->tester->grabFromCollection('lines', array('type' => "credit", "usaget" => "discount", 'aid' => $aid, 'key'=>'SUBSCRIBER_DISCOUNT_2_PLAN_5076'));

  
        $this->assertEqualsWithDelta(22.403333333, $planLine['aprice'],$this->epsilon);
        $this->assertEqualsWithDelta(-11.204, $discountLine1['aprice'],$this->epsilon);
        $this->assertEqualsWithDelta(-2.801, $discountLine2['aprice'],$this->epsilon);
       
    }
}
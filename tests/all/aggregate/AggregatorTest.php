<?php

class AggregatorTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
        ini_set('error_reporting', E_ALL & ~E_WARNING & ~E_NOTICE);
        $this->tester->enableExternalModeSettings();
        $this->tester->cleanDB();
    }

    protected function _after()
    {
      //  $this->tester->enableDBModeSettings();
    }

    
    public $defaultOptions = array(
        "type" => "customer",
        "stamp" => "202410",
        "page" => 0,
        "size" => 100,
        'fetchonly' => true,
        'generate_pdf' => 0,
        "force_accounts" => array(123)
    );

    protected $epsilon = 0.00001;
    public function test2DiffOverridesFor2DifferentSidsUnderSameAccount()
    {
        $this->tester->generatePlan(['name' => 'PLAN_A']);
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202410', 'aid' => 123));
        $this->assertEqualsWithDelta(117058.5, $billrun['totals']['after_vat'],$this->epsilon);
    }
    public function testDiscountEndsWithinMonthAndStartsAgainInTheSameMonthWithoutAGap()
    {
        $this->tester->generatePlan([
            'name' => 'PLAN_B',
            "price" => [
                [
                    "price" => 100,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ]
        ]);
        $this->defaultOptions['force_accounts'] = [125];
        $this->defaultOptions["stamp"] = "202411";
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202411', 'aid' => 125));
        $this->assertEqualsWithDelta(105.3, $billrun['totals']['after_vat'],$this->epsilon);
    }


    public function testDiscountEndsWithinMonthAndStartsAgainInTheSameMonthWithAGap()
    {
        $this->tester->generatePlan([
            'name' => 'PLAN_B',
            "price" => [
                [
                    "price" => 100,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ]
        ]);
        $this->defaultOptions['force_accounts'] = [126];
        $this->defaultOptions["stamp"] = "202411";
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202411', 'aid' => 126));
        $this->assertEqualsWithDelta(106.054838709, $billrun['totals']['after_vat'],$this->epsilon);

    }

    public function CheckDiscountOnOverriddenPlanAmountInExternalCalls()
    {
        $this->tester->generatePlan([
            'name' => 'PLAN_B',
            "price" => [
                [
                    "price" => 100,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ]
        ]);
        $this->defaultOptions['force_accounts'] = [127];
        $this->defaultOptions["stamp"] = "202411";
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202411', 'aid' => 127));
        $this->assertEqualsWithDelta(5.85, $billrun['totals']['after_vat'],$this->epsilon);
    }



    public function testkDiscountOnOverriddenPlanAmountInExternalCallsWhenThereAreTwoDifferentOverridesForThePlan()
    {
        $this->tester->generatePlan([
            'name' => 'PLAN_B',
            "price" => [
                [
                    "price" => 100,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ]
        ]);
        $this->defaultOptions['force_accounts'] = [128];
        $this->defaultOptions["stamp"] = "202411";
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202411', 'aid' => 128));
        $this->assertEqualsWithDelta(52.65, $billrun['totals']['after_vat'],$this->epsilon);
    }



    public function testWhetherGBADiscountsFieldCanIncludeTheProrationField()
    {
        $this->tester->generatePlan([
            'name' => 'PLAN_A',
            "price" => [
                [
                    "price" => 100,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ]
        ]);
        $this->tester->generatePlan([
            'name' => 'PLAN_B',
            "price" => [
                [
                    "price" => 100,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ]
        ]);
        $this->defaultOptions['force_accounts'] = [129];
        $this->defaultOptions["stamp"] = "202411";
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202411', 'aid' => 129));
        $this->assertEqualsWithDelta(105.3, $billrun['totals']['after_vat'],$this->epsilon);
    }


public function testDifferentDiscountAmountsForTheSamePlanDifferentSubs()
    {
        $this->tester->generatePlan([
            'name' => 'PLAN_A',
            "price" => [
                [
                    "price" => 100,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ]
        ]);
        
        $this->defaultOptions['force_accounts'] = [130];
        $this->defaultOptions["stamp"] = "202411";
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202411', 'aid' => 130));
         $this->assertEqualsWithDelta(216.45, $billrun['totals']['after_vat'],$this->epsilon);
    }
    public function testDifferentDiscountAmountsForTheSamePlanSameSubSameMonthSubsDiscountReducesDuringTheMonth()
    {
        $this->tester->generatePlan([
            'name' => 'PLAN_B',
            "price" => [
                [
                    "price" => 100,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ]
        ]);
        $this->defaultOptions['force_accounts'] = [131];
        $this->defaultOptions["stamp"] = "202411";
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202411', 'aid' => 131));
        $this->assertEqualsWithDelta(107.94193548387096, $billrun['totals']['after_vat'],$this->epsilon);
    }



    public function testDiscountEndsWithinMonthAndStartsAgainInTheSameMonthWithoutAGapTestCreate2DiscountLins()
    {
        $this->tester->generatePlan([
            'name' => 'PLAN_B',
            "price" => [
                [
                    "price" => 100,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ]
        ]);
        $this->defaultOptions['force_accounts'] = [132];
        $this->defaultOptions["stamp"] = "202411";
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202411', 'aid' => 132));
        $this->tester->seeNumElementsInCollection('lines', 2, ['type' => 'credit','aid'=>132]);
        $this->assertEqualsWithDelta(105.3, $billrun['totals']['after_vat'],$this->epsilon);
    }


    public function testDiscountOnAnAccountLevelService()
    {
        $this->tester->generatePlan([
            'name' => 'PLAN_B',
            "price" => [
                [
                    "price" => 100,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ]
        ]);
        $this->tester->generateService([
            'name' => 'SERVICE_A',
            "price" => [
                [
                    "price" => 100,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ]
        ]);
        $this->defaultOptions['force_accounts'] = [133];
        $this->defaultOptions["stamp"] = "202411";
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202411', 'aid' => 133));
        $this->assertEqualsWithDelta(210.6, $billrun['totals']['after_vat'],$this->epsilon);
    }

    public function testMultipleServicesForOneSubscriberOneOfThemHasADiscountOf50Presentage(){
        $this->tester->generatePlan([
            'name' => 'PLAN_B',
            "price" => [
                [
                    "price" => 100,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ]
        ]);
        $this->tester->generateService([
            'name' => 'SERVICE_123',
            "price" => [
                [
                    "price" => 100,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ]
        ]);
        $this->tester->generateService([
            'name' => 'SERVICE_12345',
            "price" => [
                [
                    "price" => 200,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ]
        ]);
        $this->defaultOptions['force_accounts'] = [134];
        $this->defaultOptions["stamp"] = "202411";
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202411', 'aid' => 134));
        $this->assertEqualsWithDelta(351, $billrun['totals']['after_vat'],$this->epsilon);
    }
    public function testTwoDiscountsForTheSameSubscribersPlan()
    {
        $this->tester->generatePlan([
            'name' => 'PLAN_A',
            "price" => [
                [
                    "price" => 100,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ]
        ]);
        $this->defaultOptions['force_accounts'] = [135];
        $this->defaultOptions["stamp"] = "202411";
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202411', 'aid' => 135));
        $this->assertEqualsWithDelta(0, $billrun['totals']['after_vat'],$this->epsilon);
    }

    public function testTwoDiscountsForTheSameSubscribersService()
    {
        $this->tester->generatePlan([
            'name' => 'PLAN_A',
            "price" => [
                [
                    "price" => 100,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ]
        ]);
        $this->tester->generateService([
            'name' => 'SERVICE_770',
            "price" => [
                [
                    "price" => 100,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ]
        ]);
        $this->defaultOptions['force_accounts'] = [136];
        $this->defaultOptions["stamp"] = "202411";
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202411', 'aid' => 136));
        $this->assertEqualsWithDelta(117, $billrun['totals']['after_vat'],$this->epsilon);
    }


      /**
       * Summary of testServiceOverridesWith2Revisions
       * overide service price only on the first revision ,the service exists in both revisions 
       * @return void
       */
      public function testServiceOverridesWith2Revisions()
  {

        foreach (['A', 'B'] as $planName) {
            $this->tester->generatePlan([
                'name' => $planName,
                "price" => [
                    [
                        "price" => 100,
                        "from" => 0,
                        "to" => "UNLIMITED"
                    ]
                ]
            ]);
        }

        foreach (['SERVICE_A', 'SERVICE_B', 'SERVICE_C', 'SERVICE_D'] as $serviceName) {
            $this->tester->generateService([
                'name' => $serviceName,
                'description' => "Service $serviceName",
                "price" => [
                    [
                        "price" => 100,
                        "from" => 0,
                        "to" => "UNLIMITED"
                    ]
                ]
            ]);
        }
        $this->defaultOptions['force_accounts'] = [5472];
        $this->defaultOptions["stamp"] = "202608";
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202608', 'aid' => 5472));
        $this->assertEqualsWithDelta(21.451612903225808, $billrun['totals']['before_vat'], $this->epsilon);
  }
    
 
      /**
       * Summary of testServiceOverridesWith2RevisionsServicesOnlyInFirstRevision
       * overide service price only on the first revision ,the service exists in firstrevisions 
       * @return void
       */
      public function testServiceOverridesWith2RevisionsServicesOnlyInFirstRevision()
  {

        foreach (['A', 'B'] as $planName) {
            $this->tester->generatePlan([
                'name' => $planName,
                "price" => [
                    [
                        "price" => 100,
                        "from" => 0,
                        "to" => "UNLIMITED"
                    ]
                ]
            ]);
        }

        foreach (['SERVICE_A', 'SERVICE_B', 'SERVICE_C', 'SERVICE_D'] as $serviceName) {
            $this->tester->generateService([
                'name' => $serviceName,
                'description' => "Service $serviceName",
                "price" => [
                    [
                        "price" => 100,
                        "from" => 0,
                        "to" => "UNLIMITED"
                    ]
                ]
            ]);
        }
        $this->defaultOptions['force_accounts'] = [5473];
        $this->defaultOptions["stamp"] = "202608";
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202608', 'aid' => 5473));
        $this->assertEqualsWithDelta(21.451612903225808, $billrun['totals']['before_vat'], $this->epsilon);
  }



      /**
       * Summary of testServiceOverridesWith2RevisionServicesOnBothRevisionsOverrideOnlyOne
       * overide 2 services price on the first revision and in second revision override only one,
       * the service exists in both revisions 
       * @return void
       */
      public function testServiceOverridesWith2RevisionServicesOnBothRevisionsOverrideOnlyOne()
  {

        foreach (['A', 'B'] as $planName) {
            $this->tester->generatePlan([
                'name' => $planName,
                "price" => [
                    [
                        "price" => 100,
                        "from" => 0,
                        "to" => "UNLIMITED"
                    ]
                ]
            ]);
        }

        foreach (['SERVICE_A', 'SERVICE_B', 'SERVICE_C', 'SERVICE_D'] as $serviceName) {
            $this->tester->generateService([
                'name' => $serviceName,
                'description' => "Service $serviceName",
                "price" => [
                    [
                        "price" => 100,
                        "from" => 0,
                        "to" => "UNLIMITED"
                    ]
                ]
            ]);
        }
        $this->defaultOptions['force_accounts'] = [5474];
        $this->defaultOptions["stamp"] = "202608";
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202608', 'aid' => 5474));
        $this->assertEqualsWithDelta(26.451612903225808, $billrun['totals']['before_vat'], $this->epsilon);
  }
    
}
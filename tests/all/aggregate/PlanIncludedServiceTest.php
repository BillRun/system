<?php

/**
 * BRCD-5614 - services included in a plan (plan "include.services") vs the
 * customer.aggregator.charge_included_service config (default true).
 */
class PlanIncludedServiceTest extends \Codeception\Test\Unit
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
        $this->tester->enableDBModeSettings();
    }

    public $defaultOptions = array(
        "type" => "customer",
        "stamp" => "202411",
        "page" => 0,
        "size" => 100,
        'fetchonly' => true,
        'generate_pdf' => 0,
        "force_accounts" => array(5614)
    );

    protected $epsilon = 0.00001;

    protected function generateIncludedServiceFixtures()
    {
         $this->tester->generateService([
            'name' => 'SERVICE_5614',
            "price" => [
                [
                    "price" => 50,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ]
        ]);
        $this->tester->generatePlan([
            'name' => 'PLAN_5614',
            "price" => [
                [
                    "price" => 100,
                    "from" => 0,
                    "to" => "UNLIMITED"
                ]
            ],
            "include" => [
                "services" => ["SERVICE_5614"]
            ]
        ]);
       
    }

    /**
     * charge_included_service = true (the default): the service included in the plan
     * is charged like a regular service (full price, marked included_in_plan).
     */
    public function testPlanIncludedServiceIsChargedWhenChargeIncludedServiceIsTrue()
    {
        $this->generateIncludedServiceFixtures();
        \Billrun_Factory::config()->setConfigValue('customer.aggregator.charge_included_service', true);
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202411', 'aid' => 5614));
        $this->assertEqualsWithDelta(175.5, $billrun['totals']['after_vat'], $this->epsilon);
        $this->tester->seeNumElementsInCollection('lines', 1, ['type' => 'service', 'aid' => 5614, 'included_in_plan' => 1]);
        $this->tester->seeNumElementsInCollection('lines', 1, ['type' => 'flat', 'aid' => 5614]);
        $serviceLine = $this->tester->grabFromCollection('lines', ['type' => 'service', 'aid' => 5614]);
        $this->assertNotEquals(0, $serviceLine['aprice']);
        $this->assertEqualsWithDelta(50, $serviceLine['aprice'], $this->epsilon);
    }

    /**
     * charge_included_service = false: the plan-included service must not be charged -
     * only the plan flat line is created, and the cycle must not crash
     * (PHP 8: count()/array_merge() TypeError on the int charge that
     * Billrun_Cycle_Data_Service::getCharges returned before the fix).
     */
    public function testPlanIncludedServiceIsNotChargedAndCycleDoesNotCrashWhenChargeIncludedServiceIsFalse()
    {
        $this->generateIncludedServiceFixtures();
        \Billrun_Factory::config()->setConfigValue('customer.aggregator.charge_included_service', false);
        try {
            $this->tester->runCycle($this->defaultOptions);
        } finally {
            \Billrun_Factory::config()->setConfigValue('customer.aggregator.charge_included_service', true);
        }
        $billrun = $this->tester->grabFromCollection('billrun', array('billrun_key' => '202411', 'aid' => 5614));
        $this->assertEqualsWithDelta(117, $billrun['totals']['after_vat'], $this->epsilon);
        $this->tester->seeNumElementsInCollection('lines', 1, ['type' => 'flat', 'aid' => 5614]);
        $this->tester->seeNumElementsInCollection('lines', 0, ['type' => 'service', 'aid' => 5614]);
    }
}

<?php

class CreditguardTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    
    protected function _before()
    {
    }

    protected function _after()
    {
    }

    public function testEnable()
    {
        $this->tester->enableCreditGuardPGWithSettings();
    }
}
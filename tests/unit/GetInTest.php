<?php

class GetInTest extends \Codeception\Test\Unit
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

    // tests
    public function testGetInArrayKey()
    {
        $gotField = Billrun_Util::getIn(array('a' => 'b'), 'a', '');
        $this->assertEquals('b', $gotField);
    }
}
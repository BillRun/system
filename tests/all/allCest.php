<?php

Use Codeception\Module\Cli;
require_once 'library/Billrun/Util.php';

class MyCest
{

    public function myTest(\ApiTester $I, AcceptanceTester $a)
    {
        $result = $I->canSeeNumElementsInCollection('blabla', 0);
    }

    public function myTest2(\ApiTester $I, AcceptanceTester $a)
    {
       $I->assertEquals(1-0, 1);
    }

    public function myTest3(\ApiTester $I, AcceptanceTester $a)
    {
        $I->sendGet('/api');
        $I->seeResponseCodeIsSuccessful();
    }
    
    public function myTest4(CLI $c)
    {
        $c->runShellCommand('php public/index.php');
    }
    
    public function myTest5(\ApiTester $I)
    {
        $gotField = Billrun_Util::getIn(array('a' => 'b'), 'a', '');
        $I->assertEquals('b', $gotField);
    }

    public function myTest6(AcceptanceTester $I) {
        $I->amOnPage('/');
        $I->waitForText('Please Sign In', 1);
    }
}
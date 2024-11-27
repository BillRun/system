<?php
namespace Helper\BillRun\Mockups;

// here you can define custom actions
// all public methods declared in helper class will be available in $I
use Codeception\Module\REST;

class Mockup extends \Codeception\Module
{
  public function getDomain() {
    return 'http://mockup:8081/';
  }
}

<?php

namespace Helper;

use AcceptanceTester;
use Codeception\Lib\ModuleContainer;
use Codeception\Module\Cli;

class Aggregate extends \Codeception\Module
{
   private $cli;
    

public function __construct() {
   $moduleContainer = new ModuleContainer(new \Codeception\Lib\Di(), []);
   $this->cli = new Cli($moduleContainer);
}
   public $defaultOptions = array(
      "type" => "customer",
      "stamp" => "202410",
      "page" => 0,
      "size" => 100,
      'fetchonly' => true,
      'generate_pdf' => 0,
      "force_accounts" => array()
  );

  public function runCycle($options = []) {
      $options = array_merge($this->defaultOptions, $options);
      $aggregator = \Billrun_Aggregator::getInstance($options);
      $aggregator->load();
      $aggregator->aggregate();
  }

public function confirmInvoices($options = []) {
   $command = 'php public/index.php --env container --generate --type billrunToBill';
   
   foreach($options as $key => $value) {
      if ($key === 'stamp') {
          $command .= " --{$key} {$value}";
      } else {
          $command .= " {$key}={$value}";
      }
   }
   
   $this->cli->runShellCommand($command);
   return $this->cli;
   }
   }

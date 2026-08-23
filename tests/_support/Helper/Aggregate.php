<?php

namespace Helper;

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

  /**
   * BRCD-5421 - the upfront charges are reconciled against the previous cycle run lines, so the
   * previous cycle runs first to create them (as it would in production). the legacy expectations
   * assume the previous run did NOT know the changes in advance - so by default it runs with the
   * full fraction (legacy) upfront behavior, and the tested cycle runs with the default (knowing
   * the changes in advance) behavior.
   *
   * @param array $options the tested cycle options
   * @param boolean $fullFraction run the previous cycle with the full fraction (legacy) upfront
   * behavior - pass false to run it with the knowing in advance behavior as well
   */
  public function runCycleWithPrevious($options = [], $fullFraction = true) {
      $options = array_merge($this->defaultOptions, $options);
      $previousOptions = $options;
      $previousOptions['stamp'] = \Billrun_Billingcycle::getPreviousBillrunKey($options['stamp']);
      \Billrun_Factory::config()->setConfigValue('billrun.upfront.full_fraction', $fullFraction);
      try {
          $this->runCycle($previousOptions);
      } finally {
          \Billrun_Factory::config()->setConfigValue('billrun.upfront.full_fraction', false);
      }
      $this->runCycle($options);
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

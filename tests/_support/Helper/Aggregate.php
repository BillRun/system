<?php

namespace Helper;


class Aggregate extends \Codeception\Module
{

   public $defaultOptions = array(
      "type" => "customer",
      "stamp" => "202410",
      "page" => 0,
      "size" => 100,
      'fetchonly' => true,
      'generate_pdf' => 0,
      "force_accounts" => array()
  );

   public function aggregator($options = []) {
      $options = array_merge($this->defaultOptions, $options);
      $aggregator = \Billrun_Aggregator::getInstance($options);
      $aggregator->load();
      $aggregator->aggregate();
  }

   }

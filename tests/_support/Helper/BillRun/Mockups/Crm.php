<?php
namespace Helper\BillRun\Mockups;

// here you can define custom actions
// all public methods declared in helper class will be available in $I
use Codeception\Module\REST;

class Crm extends \Helper\BillRun\Mockups\Mockup
{
  public function getUrl() {
    return $this->getDomain() . 'crm';
  }

  public function enableExternalModeSettings($data = []) {
    $model = new \ConfigModel();
    $model->updateConfig('subscribers', $this->getExternalConfiguration());
    \Billrun_Config::getInstance()->loadDbConfig();

  }

  public function enableDBModeSettings($data = []) {
    $model = new \ConfigModel();
    $model->updateConfig('subscribers', $this->getDBConfiguration());
    \Billrun_Config::getInstance()->loadDbConfig();

  }

  protected function getSampleConfiguration2() {

  }

  public function getExternalConfiguration() {
    return [
        "subscriber" => [
            "type" => "external",
            "external_url" => $this->getUrl()."/gsd",
            "timeout" => 20
        ],
        "account" => [
            "type" => "external",
            "external_url" => $this->getUrl()."/gad",
            "timeout" => 20
        ],
        "billable" => [
            "url" => $this->getUrl()."/billable"
        ],
        
    ];
}

public function getDBConfiguration() {
  return [
      "subscriber" => [
          "type" => "db",
          "external_url" => "",
          "timeout" => 20
      ],
      "account" => [
          "type" => "db",
          "external_url" => "",
          "timeout" => 20
      ],
      "billable" => [
          "url" => ""
      ],
      
  ];
}
}

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

  /**
   * The log-line prefix of an outgoing external CRM request, derived from the
   * configured url of the entity - 'account' (gad), 'subscriber' (gsd) or
   * 'billable' (gba). Use with seeCountInLogFile to count the CRM requests.
   */
  public function getExternalRequestLog($entity = 'account') {
    $configKey = $entity === 'billable' ? 'subscribers.billable.url' : "subscribers.{$entity}.external_url";
    return 'Sending request to ' . \Billrun_Factory::config()->getConfigValue($configKey, '');
  }

  public function enableExternalModeSettings($data = [], $pluginName = '') {
    $model = new \ConfigModel();
    $model->updateConfig('subscribers', $this->getExternalConfiguration($pluginName));
    \Billrun_Config::getInstance()->loadDbConfig();

  }

  public function enableDBModeSettings($data = []) {
    $model = new \ConfigModel();
    $model->updateConfig('subscribers', $this->getDBConfiguration());
    \Billrun_Config::getInstance()->loadDbConfig();

  }

  protected function getSampleConfiguration2() {

  }

  public function getExternalConfiguration($pluginName) {
    return [
        "subscriber" => [
            "type" => "external",
            "external_url" => !empty($pluginName) ? $this->getUrl()."/". $pluginName."/gsd" : $this->getUrl()."/gsd",
            "timeout" => 20
        ],
        "account" => [
            "type" => "external",
            "external_url" => !empty($pluginName) ? $this->getUrl()."/". $pluginName."/gad" : $this->getUrl()."/gad",
            "timeout" => 20
        ],
        "billable" => [
            "url" => !empty($pluginName) ? $this->getUrl()."/". $pluginName."/billable" : $this->getUrl()."/billable",
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

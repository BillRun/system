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

  /**
   * URL of the collection "state change" receiver mock (mockup-servers/collectionStateChange.php),
   * to configure as a collection process change_state_url.
   *
   * @param string $run isolates the requests recorded for this test from other runs
   */
  public function getCollectionStateChangeUrl($run) {
    return $this->getDomain() . 'collection-state-change/' . $run;
  }

  /**
   * forgets the state change requests recorded for $run
   */
  public function resetCollectionStateChangeRequests($run) {
    $this->sendMockupRequest('DELETE', $this->getCollectionStateChangeUrl($run));
  }

  /**
   * the state change requests the mock received for $run, in the order they arrived, as the CRM sees them:
   * each has 'post' (the parsed form fields: step_code, step_type, extra_params, creation_time) and 'raw' (the body as sent)
   *
   * @return array
   */
  public function grabCollectionStateChangeRequests($run) {
    $requests = json_decode($this->sendMockupRequest('GET', $this->getCollectionStateChangeUrl($run)), true);
    return is_array($requests) ? $requests : [];
  }

  protected function sendMockupRequest($method, $url) {
    $context = stream_context_create(['http' => ['method' => $method, 'ignore_errors' => true, 'timeout' => 10]]);
    $response = file_get_contents($url, false, $context);
    if ($response === false) {
      $this->fail("Mockup request failed: {$method} {$url}");
    }
    return $response;
  }
}

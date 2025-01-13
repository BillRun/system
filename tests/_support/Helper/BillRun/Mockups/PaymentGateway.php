<?php
namespace Helper\BillRun\Mockups;

// here you can define custom actions
// all public methods declared in helper class will be available in $I
use Codeception\Module\REST;

class PaymentGateway extends \Helper\BillRun\Mockups\Mockup
{
  public function getUrl() {
    return $this->getDomain() . 'payment-gateways';
  }

  public function enableCreditGuardPGWithSettings($data = []) {
    $model = new \ConfigModel();
    $model->updateConfig('payment_gateways', $this->getSampleConfiguration());
  }

  protected function getSampleConfiguration2() {

  }

  public function getSampleConfiguration() {
    return [
        'name' => 'CreditGuard',
        'params' => [
            'custom_style' => " ",
//            'endpoint_url' => "https://cguat2.creditguard.co.il/xpo/Relay",
            'endpoint_url' => $this->getUrl() . '/creditguard/xpo/Relay',
            'mid' => "13092",
            'onetime_terminal' => "0882828013",
            'charging_terminal' => "0882828013",
            'user' => "yossi",
            'ancestor_urls' => " ",
            "tokenize_on_single_payment"=>true,
            'version' => "2000",
            'custom_text' => " ",
            'password' => "123",
            'redirect_terminal' => "0882828013"
    ]
];
}

public function iframe($params = []){
  // Get the REST module to send requests
         /** @var REST $rest */
         $rest = $this->getModule('REST');
         $rest->_setConfig(['url' => 'http://mockup:8081']);
         $ret =  $rest->sendGet("/payment-gateways/creditguard/iframe", $params);
         $rest->_setConfig(['url' => 'http://web']);

        //  $ret = $rest->sendGet("/paymentgateways/getRequest/iframe");          
         return json_decode($ret, true);
     }


}
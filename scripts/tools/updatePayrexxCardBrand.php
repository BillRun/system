<?php
//Example call - docker exec -w /billrun billrun-app  php /billrun/scripts/tools/updatePayrexxCardBrand.php --env container --dir /billrun/ 
$options = getopt('', array('env:', 'dir:'));
$dir = $options['dir'];

defined('APPLICATION_PATH') || define('APPLICATION_PATH', $dir);
require_once(APPLICATION_PATH . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'config.php');
$app = new Yaf_Application(BILLRUN_CONFIG_PATH);
$app->bootstrap();
Yaf_Loader::getInstance(APPLICATION_PATH . '/application/modules/Billapi')->registerLocalNamespace("Models");


echo 'Running Update Card brand for payrexx customers...' . PHP_EOL;
$configColl = Billrun_Factory::db()->configCollection();
$paymentGateways = Billrun_Factory::config()->getConfigValue('payment_gateways');
$subscribersColl = Billrun_Factory::db()->subscribersCollection();

foreach($paymentGateways as $paymentGateway){
	if ($paymentGateway['name'] === 'Payrexx'){
		$gatewayParams = $paymentGateway['params'];
      	break;
	}
}
if(!isset($gatewayParams)){
	echo '❌ Not found Payrexx parameters.' . PHP_EOL;
	exit;
}
$instance = $gatewayParams['instance_name'];
$secret = $gatewayParams['instance_api_secret'];
$apiDomain = $gatewayParams['custom_api_domain'];
$signature = base64_encode(hash_hmac('sha256', '', $secret, true));
$query = [
	"type" => "account",
   "payment_gateway.active.name" => "Payrexx",
   "payment_gateway.active.card_token" => ['$exists' => 1],
   "payment_gateway.active.card_brand" => ['$exists' => 0],
];
$accounts = $subscribersColl->query($query)->cursor();
$numberOfRevisions = count($accounts);
echo 'found ' . $numberOfRevisions .' Payrexx customer revisions.' . PHP_EOL; 
$params = [
    'instance' => $instance,
    'ApiSignature' => $signature
];
$headers = ['Accept-encoding' => 'deflate'];
foreach($accounts as $account){
	$id = $account['_id'];
	$aid = $account['aid'];
	$token = $account["payment_gateway"]["active"]["card_token"];
	echo "Fetching transaction for AID=$aid, token=$token, id=$id.\n";
	$apiUrl = "https://api.{$apiDomain}/v1.0/Transaction/{$token}/";
	$response = Billrun_Util::sendRequest($apiUrl, $params, Zend_Http_Client::GET, $headers);
	if ($response !== false) {
		$responseData = json_decode($response, true);
		if($responseData['status'] !== 'success'){
			echo "❌ API call failed.\n  with massage: ".$responseData['massage'].".\n";	
		}else{
			// print_r($responseData['data']);
			$brand = $responseData['data'][0]['payment']['brand'];
			if(!isset($brand)){
				echo "❌ API call response not include card brand.\n";
			}else{
				echo "Updating brand=$brand for id=$id.\n";
				$updateQuery = [
					"_id" => $id,
				];
				$update = [
					'$set' => array(
						"payment_gateway.active.card_brand" => $brand
					)
				];
				$res = $subscribersColl->update($updateQuery, $update);
				if (!isset($res['ok']) || !$res['ok']) {
					echo "❌ failed to update brand=$brand for id=$id\n";
				}else{
					echo "✅ success to update brand=$brand for id=$id.\n";
				}
			}
		}
	} else {
		echo "❌ API call failed for aid $aid.\n";
	}
}

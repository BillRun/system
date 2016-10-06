 <?php
 
 /**
  * @package         Billing
  * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
  * @license         GNU Affero General Public License Version 3; see LICENSE.txt
  */
 require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';
 require_once APPLICATION_PATH . '/library/vendor/autoload.php';
 
 /**
  * This class returns the available payment gateways in Billrun.
  *
  * @package     Controllers
  * @subpackage  Action
  * @since       5.2
  */
 class PaymentGatewaysController extends ApiController {
 
	 
 	public function init() {
		parent::init();
 		
 	}
	
	public function listAction(){
	 $gateways = Billrun_Factory::config()->getConfigValue('PaymentGateways');
 		$settings = array();
 		foreach ($gateways as $name => $properties) {
			$setting['name'] = $name;
 			foreach($properties as $property => $value){
 				$setting[$property] = $value;
				if ($property == 'omnipay_supported' && $value == true){
					$gateway = Omnipay\Omnipay::create($name);	 
					$fields = $gateway->getParameters();
					$setting['params'] = $fields;
				}
				else if ($name == 'CreditGuard'){  // TODO: make more generic when there's generic payment gateways class.
					$setting['params'] = array("user" => "", "password" => "", 'terminal_id' => "");
				}
 			}
 			$settings[] = $setting;
 		}
 		$this->setOutput(array(
 					'status' => !empty($settings) ? 1 : 0,
 					'desc' => !empty($settings) ? 'success' : 'error',
 					'details' => empty($settings) ? array() : $settings,
 			));
	}
	
	protected function render($tpl, array $parameters = array()) {
		return parent::render('index', $parameters);
	}
	
	
	
	public function getRequestAction(){
		$request = $this->getRequest();
		
		// Validate the data.
	//	$data = $this->validateData($request);
//		if($data === null) {
//			return $this->setError("Failed to authenticate", $request);
//		}
//		
		
		$data = $request->get("data");
		$jsonData = json_decode($data, true);
		
		if (!isset($jsonData['aid']) || is_null(($aid = $jsonData['aid'])) || !Billrun_Util::IsIntegerValue($aid)) {
			return $this->setError("need to pass numeric aid", $request);
		}
		
		if (!isset($jsonData['name'])) {
			return $this->$setError("need to pass payment gateway name", $request);
		}
		
		$name = $jsonData['name'];
		$aid = $jsonData['aid'];
		
//		if(!isset($jsonData['t']) || is_null(($timestamp = $jsonData['t']))) {
//			return $this->setError("Invalid arguments", $request);			
//		}
//		
		// TODO: Validate timestamp 't' against the $_SERVER['REQUEST_TIME'], 
		// Validating that not too much time passed.
		
		$returnUrl = $jsonData['return_url'];
		if(empty($returnUrl)) {
			$returnUrl = Billrun_Factory::config()->getConfigValue('cg_return_url');
		}
		
//		$this->getToken($aid, $return_url);
//		$url_array = parse_url($this->url);
//		$str_response = array();
//		parse_str($url_array['query'], $str_response);
//		$this->CG_transaction_id = $str_response['txId'];	
//		
//		// Signal starting process.
//		$this->signalStartingProcess($aid, $timestamp);
		
		
		$paymentGateway = Billrun_PaymentGateway::getInstance($name);
	//	$paymentGateway->makePayment();
		$paymentGateway->redirectForToken($aid, $returnUrl);
		
	}

	
	public function OkPageAction(){
		
		$request = $this->getRequest();
		$name = $request->get("name");
		if (is_null($name)) {
			return $this->setError("Missing payment gateway name", $request);
		}
		$paymentGateway = Billrun_PaymentGateway::getInstance($name);
		$transactionName = $paymentGateway->getTransactionIdName();
		$transactionId = $request->get($transactionName);
		if (is_null($transactionId)) {
			return $this->setError("Operation Failed. Try Again...", $request);
		}
	
		$paymentGateway->saveTransactionDetails($transactionId);
		
	
		
		
		
		
		// Validate the process.
//		if(!$this->validateCreditGuardProcess($transaction_id)) {
//			return $this->setError("Operation Failed. Try Again...", $request);			
//		}
		
		
//		$today = new MongoDate();
//		$this->subscribers = Billrun_Factory::db()->subscribersCollection();
//		$this->subscribers->update(array('aid' => (int) $this->aid, 'from' => array('$lte' => $today), 'to' => array('$gte' => $today), 'type' => "account"), array('$set' => array('card_token' => (string) $this->card_token, 'card_expiration' => (string) $this->card_expiration, 'personal_id' => (string) $this->personal_id, 'transaction_exhausted' => true)));
//		$this->forceRedirect($this->return_url);
		
		
	}
	
	
 
 }
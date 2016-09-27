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
 
 }
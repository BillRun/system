 <?php
 
 /**
  * @package         Billing
  * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
  * @license         GNU Affero General Public License Version 3; see LICENSE.txt
  */
 require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';
 
 /**
  * This class returns the available payment gateways in Billrun.
  *
  * @package     Controllers
  * @subpackage  Action
  * @since       5.2
  */
 class PaymentGatewaysController extends ApiController {
 
	 
 	public function init() {
 		$path = APPLICATION_PATH . '/library/vendor/';
 		require_once $path . 'autoload.php';
 		
 	}
	
	public function listAction(){
	 $gateways = Billrun_Factory::config()->getConfigValue('Gateways');
 		$settings = array();
 		foreach ($gateways as $name => $properties) {
 			foreach($properties as $property => $value){
 				$setting['name'] = $name;
 				$setting[$property] = $value;
 				$gateway = Omnipay\Omnipay::create($name);	 
 				$fields = $gateway->getParameters();
 				$setting['params'] = $fields;
 			}
 			$settings[] = $setting;
 		}
 		echo json_encode(array(
 					'status' => !empty($settings) ? 1 : 0,
 					'desc' => !empty($settings) ? 'success' : 'error',
 					'details' => empty($settings) ? array() : $settings,
 			), JSON_FORCE_OBJECT);
	}
 
 }
 <?php
 
 /**
  * @package         Billing
  * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
  * @license         GNU Affero General Public License Version 3; see LICENSE.txt
  */
 require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';
 
 /**
  * This class holds the api logic for the settings.
  *
  * @package     Controllers
  * @subpackage  Action
  * @since       5.2
  */
 class PaymentGatewaysAction extends ApiAction {
 
 	/**
 	 * The logic to be executed when this API plugin is called.
 	 */
 	public function execute() {
 		$path = APPLICATION_PATH . '/library/vendor/';
 		require_once $path . 'autoload.php';
 		
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
 		$output = json_encode($settings, JSON_FORCE_OBJECT);	
 		$this->getController()->setOutput(array(array(
 					'status' => !empty($output) ? 1 : 0,
 					'desc' => !empty($output) ? 'success' : 'error',
 					'details' => empty($output) ? array() : $output,
 			)));
 	}
 
 }
<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/library/vendor/autoload.php';

/**
 * This class represents a payment gateway
 *
 * @since    5.2
 */
abstract class Billrun_PaymentGateway {
	use Billrun_Traits_Api_PageRedirect;
	
	protected $omnipayName;
	protected $omnipayGateway;
	protected static $paymentGateways;
	protected $redirectUrl;
	protected $successUrl;
	protected $failUrl;

	private function __construct() {
		
		if ($this->supportsOmnipay()) {
			$this->omnipayGateway = Omnipay\Omnipay::create($this->getOmnipayName());
		}
		
	}

	abstract function getSessionTransactionId();

	public function __call($name, $arguments) {
		if ($this->supportsOmnipay()) {
			return call_user_func_array(array($this->omnipayGateway, $name), $arguments);
		}
		throw new Exception('Method ' . $name . ' is not supported');
	}

	/**
	 * 
	 * @param string $name the payment gateway name
	 * @return Billrun_PaymentGateway
	 */
	public static function getInstance($name) {
		if (isset(self::$paymentGateways[$name])) {
			$paymentGateway = self::$paymentGateways[$name];
		} else {
			$subClassName = __CLASS__ . '_' . $name;
			if (@class_exists($subClassName)) {
				$paymentGateway = new $subClassName();
				self::$paymentGateways[$name] = $paymentGateway;
			}
		}
		return isset($paymentGateway)? $paymentGateway : NULL;
	}

	public function supportsOmnipay() {
		return !is_null($this->omnipayName);
	}

	public function getOmnipayName() {
		return $this->omnipayName;
	}
	
	public function redirect(){
		$this->forceRedirect($this->redirectUrl);
	}
	
	public function isSupportedGateway(){
		
	}
	
	abstract public function charge();
	

	public function getToSuccessPage(){  // TODO: Meantime there's another function named getOkPage who does the same function.
		$this->forceRedirect($this->successUrl);
	}
	
	public function getToFailurePage(){
		$this->forceRedirect($this->failUrl);
	}
	
	
	abstract public function getToken($aid, $returnUrl);
	
	
	
 	public function getOkPage() {
		$okTemplate = Billrun_Factory::config()->getConfigValue('CG.conf.ok_page');
		$request = $this->getRequest();
		$pageRoot = $request->getServer()['HTTP_HOST'];
		$protocol = empty($request->getServer()['HTTPS'])? 'http' : 'https';
		$okPageUrl = sprintf($okTemplate, $protocol, $pageRoot);
		return $okPageUrl;
	}
	
}

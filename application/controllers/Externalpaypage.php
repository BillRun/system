<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing paypage controller class
 *
 * @package  Controller
 * @since    5.0
 */
class ExternalPaypageController extends Yaf_Controller_Abstract { // CAUTIOUS WHEN ENABLING THIS CLASS
	public function init() {
		Billrun_Factory::db();
	}

	public function indexAction() {
		$view = new Yaf_View_Simple(Billrun_Factory::config()->getConfigValue('application.directory') . '/views/paypage');
		$request = $this->getRequest()->getRequest();
		$action = $this->getRequest()->get('action', 'create');
		$query = array(
			'type' => 'account',
			'aid' => intval($request['aid'])
		);
		$query = array_merge($query, Billrun_Utils_Mongo::getDateBoundQuery());
		if (isset($request['plan'])) {
			$selectedPlan = Billrun_Util::filter_var($request['plan'], FILTER_SANITIZE_STRING);
		} else {
			$selectedPlan = "";
		}
		$account = Billrun_Factory::db()->subscribersCollection()->query($query)->cursor()->current()->getRawData();
		$config = Billrun_Factory::db()->configCollection()->query()->cursor()->sort(array('_id' => -1))->limit(1)->current()->getRawData();
		$plans = $this->getEntityActiveRows('plan');
		$services = $this->getEntityActiveRows('service');
		$planNames = $this->getEntityActiveRows('plan', 'name');
		$serviceNames = $this->getEntityActiveRows('service', 'name');
		if (isset($config['tenant']['logo'])) {
			$logoFileName = $config['tenant']['logo'];
		} else {
			$logoFileName = '';
		}
		$imageQuery = array(
			'filename' => $logoFileName,
		);
		$gfsFile = Billrun_Factory::db()->getGridFS()->findOne($imageQuery);
		if (!empty($gfsFile)) {
			$imageEncode = 'data:image/' . pathinfo($logoFileName, PATHINFO_EXTENSION) . ';base64,' . base64_encode($gfsFile->getBytes());
		} else {
			$imageEncode = '/img/web-logo.png';
		}
		$this->getView()->assign('account', $account);
		$this->getView()->assign('tenant', $config['tenant']);
		$this->getView()->assign('company_image', $imageEncode);
		$this->getView()->assign('account_config', $config['subscribers']['account']['fields']);
		$this->getView()->assign('subscriber_config', $config['subscribers']['subscriber']['fields']);
		$this->getView()->assign('payment_gateways', $this->getPaymentGatewaysInfo($config['payment_gateways']));
		$this->getView()->assign('planNames', $planNames);
		$this->getView()->assign('serviceNames', $serviceNames);
		$this->getView()->assign('plans', $plans);
		$this->getView()->assign('services', $services);
		$this->getView()->assign('plan', $selectedPlan);
		$this->getView()->assign('return_url', $request['return_url']);
		$this->getView()->assign('action', $action);
		$this->getView()->assign('currency_symbol', Billrun_Rates_Util::getCurrencySymbol($config['pricing']['currency']));
//		return $view->render();
	}
	
	protected function render(string $tpl, array $parameters = null): string {
		// this trick for forward compatibility
		if ($this->getRequest()->get('tpl') == 'index2') {
			return parent::render('index2', $parameters);
		}
		return parent::render($tpl, $parameters);
	}
	public function createAction() {
		$request = $this->getRequest()->getRequest();
		$create = new Billrun_ActionManagers_Subscribers_Create();
		$type = empty($request['aid']) ? 'account' : 'subscriber';
		if (empty($request['aid'])) {
			unset($request['aid']);
		} else {
			$request['aid'] = intval($request['aid']);
		}
		if (isset($request['services']) && is_array($request['services'])) {
			$request['services'] = array_map(function($srv) { return array('name'=>$srv);}, $request['services']);
		}
		$query = array(
			"type" => $type,
			"subscriber" => json_encode($request)
		);
		$jsonObject = new Billrun_AnObj($query);
		if (!$create->parse($jsonObject)) {
			/* TODO: HANDLE ERROR! */
			return false;
		}
		if (!($res = $create->execute())) {
			/* TODO: HANDLE ERROR! */
			return false;
		}
		$secret = Billrun_Factory::config()->getConfigValue("shared_secret.key");
		$data = array(
			"aid" => $res['details']['aid'],
			Billrun_Utils_Security::TIMESTAMP_FIELD => time()
		);
		$hashResult = hash_hmac("sha512", json_encode($data), $secret);
		$sendData = array(
			"data" => $data,
			"signature" => $hashResult
		);

		header("Location: /creditguard/creditguard?signature=".$hashResult."&data=".json_encode($data));
		return false;
	}
	
	protected function getEntityActiveRows($entityName, $field = null) {
//		$data = Billrun_Factory::db()->plansCollection()->query(Billrun_Utils_Mongo::getDateBoundQuery())->cursor();
		$collection = Billrun_Factory::db()->{$entityName . 'sCollection'}();
		$data = $collection->query(Billrun_Utils_Mongo::getDateBoundQuery())->cursor();;

		$ret = array();
		foreach ($data as $row) {
			if (!is_null($field)) {
				$ret[] = $row->getRawData()[$field];
			} else {
				$ret[] = $row->getRawData();
			}
		}
		return $ret;

	}

	/**
	 * @param array $configPaymentGateways
	 * @return array list of items containing fields
	 * 		'name' - gateway name or instance name
	 * 		'title' - title
	 * 		'image_url' - logo URL
	 */
	private function getPaymentGatewaysInfo(array $configPaymentGateways): array {
		$allowedGateways = Billrun_Factory::config()->getConfigValue('PaymentGateways.potential');
		$imageUrls = Billrun_Factory::config()->getConfigValue('PaymentGateways.images');

		// get list of payment gateways that are set both in DB and ini config
		$allowedConfigPaymentGateways = array_filter($configPaymentGateways, function ($gateway) use ($allowedGateways) {
			return in_array($gateway['name'], $allowedGateways);
		});

		return array_map(function($gatewayConfig) use ($imageUrls) {
			$paymentGateway = Billrun_Factory::paymentGateway($gatewayConfig['name']);

			return [
				'name' => $gatewayConfig['name'],
				'title' => $paymentGateway ? $paymentGateway->getTitle() : $gatewayConfig['name'],
				'image_url' => $imageUrls[$gatewayConfig['name']] ?? ''
			];
		}, $allowedConfigPaymentGateways);
	}

}

<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Plugin to handle webhooks for external workflow engine
 * Any billapi can trigger any change action with before/after of the change
 *
 * @package  Application
 * @subpackage Plugins
 * @since    5.12
 */
class webhooksPlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'webhooks';

	/**
	 * webhooks config
	 * 
	 * @var string
	 */
	protected $config;

	/**
	 * plugin constructor
	 * 
	 * @param void
	 */
	public function __construct($options = array()) {
		$flatConfig = $options['config'] ?? [];
		
		foreach ($flatConfig as $webhook) {
			if (!isset($this->config[$webhook['webhook_module']])) {
				$this->config[$webhook['webhook_module']] = array();
			}
			if (!isset($this->config[$webhook['webhook_module']][$webhook['webhook_action']])) {
				$this->config[$webhook['webhook_module']][$webhook['webhook_action']] = array();
			}
			$this->config[$webhook['webhook_module']][$webhook['webhook_action']][] = $webhook['webhook_url'];
			$this->config['_byid_'][$webhook['webhook_id']] = $webhook;
		}
	}
	
	protected function triggerWebhook($data, $urls) {
		$ret = [];
		foreach ($urls as $url) {
			$url = urldecode($url);
			$ret[] = Billrun_Util::sendRequest($url, $data) !== FALSE;
		}
		
		return $ret;
	}

	/**
	 * method to send billapi change to web-hooks or any other workflow
	 * 
	 * @param array $before entity before change
	 * @param array $after entity after change
	 * @param Models_Entity $model the entity model
	 * 
	 * @return boolean true if success to trigger request, else false
	 */
	public function trackChanges($before, $after, $entityName, $action, $modifiedUser) {
		if ($entityName == 'users') {
			unset($before['password']);
			unset($after['password']);
		}

		if (!isset($this->config[$entityName][$action])) {
			return true;
		}
		$data = array(
			'module' => $entityName,
			'action' => $action,
			'before' => $before,
			'after' => $after,
			'modifiedUser' => $modifiedUser['name'] ?? '',
		);
		return $this->triggerWebhook($data, $this->config[$entityName][$action]);
	}
	
	/**
	 * afterChargeSuccess webhook
	 * 
	 * @param array $bill the bill paid successfully
	 * 
	 * @return boolean true if success to trigger request, else false
	 */
	public function afterChargeSuccess($bill) {
		if (!isset($this->config['bills'][__METHOD__])) {
			return true;
		}
		$data = array(
			'module' => 'bills',
			'action' => 'afterChargeSuccess',
			'bill' => $bill,
		);
		return $this->triggerWebhook($data, $this->config['bills'][__METHOD__]);
	}
	
	/**
	 * afterPaymentAdjusted webhook
	 * 
	 * @param array $bill the bill paid successfully
	 * 
	 * @return boolean true if success to trigger request, else false
	 */
	public function afterPaymentAdjusted($oldAmount, $newAmount, $aid) {
		if (!isset($this->config['bills'][__METHOD__])) {
			return true;
		}
		$data = array(
			'module' => 'bills',
			'action' => 'afterPaymentAdjusted',
			'old' => $oldAmount,
			'new' => $newAmount,
			'aid' => $aid,
		);
		return $this->triggerWebhook($data, $this->config['bills'][__METHOD__]);
	}
	
	/**
	 * afterRefundSuccess webhook
	 * 
	 * @param array $refund the refund triggered
	 * 
	 * @return boolean true if success to trigger request, else false
	 */
	public function afterRefundSuccess($refund) {
		if (!isset($this->config['bills'][__METHOD__])) {
			return true;
		}
		$data = array(
			'module' => 'bills',
			'action' => 'afterRefundSuccess',
			'bill' => $refund,
		);
		return $this->triggerWebhook($data, $this->config['bills'][__METHOD__]);
	}
	
	/**
	 * afterInvoiceConfirmed webhook
	 * 
	 * @param array $invoice the invoice confirmed
	 * 
	 * @return boolean true if success to trigger request, else false
	 */
	public function afterInvoiceConfirmed($invoice) {
		if (!isset($this->config['bills'][__METHOD__])) {
			return true;
		}
		$data = array(
			'module' => 'bills',
			'action' => 'afterInvoiceConfirmed',
			'bill' => $invoice,
		);
		return $this->triggerWebhook($data, $this->config['bills'][__METHOD__]);
	}
	
	/**
	 * afterRejection webhook
	 * 
	 * @param array $bill the bill rejected
	 * 
	 * @return boolean true if success to trigger request, else false
	 */
	public function afterRejection($bill) {
		if (!isset($this->config['bills'][__METHOD__])) {
			return true;
		}
		$data = array(
			'module' => 'bills',
			'action' => 'afterRejection',
			'bill' => $bill,
		);
		return $this->triggerWebhook($data, $this->config['bills'][__METHOD__]);
	}
	
	/**
	 * afterDenial webhook
	 * 
	 * @param array $denial the bill get denial
	 * 
	 * @return boolean true if success to trigger request, else false
	 */
	public function afterDenial($denial) {
		if (!isset($this->config['bills'][__METHOD__])) {
			return true;
		}
		$data = array(
			'module' => 'bills',
			'action' => 'afterDenial',
			'bill' => $denial,
		);
		return $this->triggerWebhook($data, $this->config['bills'][__METHOD__]);
	}
	
	/**
	 * afterBalanceUpdate event
	 * 
	 * @param type $before
	 * @param type $after
	 * 
	 * @return boolean true if success to trigger request, else false
	 */
	public function afterBalanceUpdate($before, $after) {
		if (!isset($this->config['balances'][__METHOD__])) {
			return true;
		}
		$data = array(
			'module' => 'balances',
			'action' => 'tx_update',
			'before' => $before,
			'after' => $after,
		);
		return $this->triggerWebhook($data, $this->config['balances'][__METHOD__]);
	}
	
	/**
	 * beforeEventSave event
	 * 
	 * @return boolean true if success to trigger request, else false
	 */
	public function beforeEventSave($event, $entityBefore, $entityAfter, $eventsManager) {
		if (!isset($this->config['events'][__METHOD__])) {
			return true;
		}
		$data = array(
			'module' => 'events',
			'action' => 'create',
			'event' => $event,
			'before' => $entityBefore,
			'after' => $entityAfter,
		);
		return $this->triggerWebhook($data, $this->config['events'][__METHOD__]);
	}

	/**
	 * afterEventNotify event
	 * 
	 * @return boolean true if success to trigger request, else false
	 */
	public function afterEventNotify($event) {
		if (!isset($this->config['events'][__METHOD__])) {
			return true;
		}
		$data = array(
			'module' => 'events',
			'action' => 'notify',
			'event' => $event,
		);
		return $this->triggerWebhook($data, $this->config['events'][__METHOD__]);
	}

	/**
	 * beforeCollectionStepRun event
	 * 
	 * @return boolean true if success to trigger request, else false
	 */
	public function beforeCollectionStepRun($step) {
		$action = $this->getCollectionAction($step);
		if (!isset($this->config['collection'][$action])) {
			return true;
		}
		
		$data = array(
			'module' => 'collection',
			'action' => $action,
			'step' => $step,
		);
		
		return $this->triggerWebhook($data, $this->config['collection'][$action]);
	}
	
	
	/**
	 * method to get action name
	 * 
	 * @param array $step collection step details
	 * 
	 * @return string the action name: in_collection, out_of_collection or {custom step name}
	 */
	protected function getCollectionAction($step) {
		$action = $step['step_code']; // collection step name
		if ($action === 'collection state change') {
			$action = $step['extra_params']['state']; // in_collection/out_of_collection
		}

		return $action;
	}
	
	/**
	 * method to define the api response
	 * 
	 * @param mixed $ret array or object to response
	 * @param Yaf_Response_Abstract $response the response object; setBody & setHeader to define body and headers
	 * 
	 * @return null
	 */
	protected function apiResponse($ret, $response) {
		$response->setBody(json_encode($ret));
		$response->setHeader('Content-Type', 'application/json');
	}

	/**
	 * apiCreate triggered from url 
	 * 
	 * @param array $params url params http://host/plugins/{plugin}/{action}/{id}
	 * @param Yaf_Request_Http $request yaf request object
	 * @param Yaf_Response_Abstract $response the response object; setBody & setHeader to define body and headers
	 * 
	 * @return void
	 */
	public function apiCreate($params, $request, $response) {
		if ($params['plugin'] != $this->getName()) {
			return;
		}
		
		$webhook = array(
			'webhook_module' => $request->get('webhook_module'),
			'webhook_action' => $request->get('webhook_action'),
			'webhook_url' => $request->get('webhook_url'),
			'webhook_id' => $request->get('webhook_id') ?? uniqid(),
		);
		
		$this->config['_byid_'][$webhook['webhook_id']] = $webhook;
		
		$this->saveWebhooksConfig();
		
		$ret = array(
			'status' => 1,
			'webhook' => $webhook,
		);
		
		$response->setBody(json_encode($ret));
	}

	/**
	 * apiRead triggered from url 
	 * 
	 * @param array $params url params http://host/plugins/{plugin}/{action}/{id}
	 * @param Yaf_Request_Http $request yaf request object
	 * @param Yaf_Response_Abstract $response the response object; setBody & setHeader to define body and headers
	 * 
	 * @return void
	 */
	public function apiRead($params, $request, $response) {
		if ($params['plugin'] != $this->getName()) {
			return;
		}
		
		$id = $params['id'] ?? $request->get('webhook_id');
		if (isset($this->config['_byid_'][$id])) {
			$ret = array(
				'status' => 1, 
				'webhook' => $this->config['_byid_'][$id],
			);
		} else {
			$ret = array(
				'status' => 0
			);
		}
		
		$this->apiResponse($ret, $response);
	}

	/**
	 * apiUpdate triggered from url 
	 * 
	 * @param array $params url params http://host/plugins/{plugin}/{action}/{id}
	 * @param Yaf_Request_Http $request yaf request object
	 * @param Yaf_Response_Abstract $response the response object; setBody & setHeader to define body and headers
	 * 
	 * @return void
	 */
	public function apiUpdate($params, $request, $response) {
		if ($params['plugin'] != $this->getName()) {
			return;
		}
		
		$id = $params['id'] ?? $request->get('webhook_id');
		$webhook = array(
			'webhook_module' => $request->get('webhook_module'),
			'webhook_action' => $request->get('webhook_action'),
			'webhook_url' => $request->get('webhook__url'),
			'webhook_id' => $id,
		);
		
		if (isset($this->config['_byid_'][$id])) {
			$this->config['_byid_'][$id] = $webhook;

			$this->saveWebhooksConfig();

			$ret = array(
				'status' => 1,
				'webhook' => $webhook,
			);
		} else {
			$ret = array(
				'status' => 0
			);
		}
		
		$this->apiResponse($ret, $response);
	}
	
	/**
	 * apiDelete triggered from url 
	 * 
	 * @param array $params url params http://host/plugins/{plugin}/{action}/{id}
	 * @param Yaf_Request_Http $request yaf request object
	 * @param Yaf_Response_Abstract $response the response object; setBody & setHeader to define body and headers
	 * 
	 * @return void
	 */
	public function apiDelete($params, $request, $response) {
		if ($params['plugin'] != $this->getName()) {
			return;
		}
		
		$id = $params['id'] ?? $request->get('webhook_id');
		
		if (isset($this->config['_byid_'][$id])) {
			unset($this->config['_byid_'][$id]);

			$this->saveWebhooksConfig();

			$ret = array(
				'status' => 1,
				'webhook_id' => $id,
			);
		} else {
			$ret = array(
				'status' => 0
			);
		}
		
		$this->apiResponse($ret, $response);
	}

	/**
	 * internal method to save webhooks config to the global config
	 * 
	 * @return boolean true if success else false
	 */
	protected function saveWebhooksConfig() {
		$configModel = new ConfigModel();
		$configData = array(
			'name' => get_class(),
			'configuration' => array(
				'values' => array(
					'config' => array_values($this->config['_byid_']),
				),
			),
		);
		$configModel->updateConfig('plugin', $configData);
		return true;
	}
	
	/**
	 * setup plugin input
	 * 
	 * @return void
	 */
	public function getConfigurationDefinitions() {
		return [
				[
					"type" => "json",
					"field_name" => "webhooks.config",
					"title" => "Webhook plugin",
					"editable" => true,
					"display" => true,
					"nullable" => false,
				],
			];
	}

}
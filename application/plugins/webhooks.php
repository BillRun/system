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
	 * webhooks collection
	 * 
	 * @var string
	 */
	protected $collection;

	/**
	 * plugin constructor
	 * 
	 * @param void
	 */
	public function __construct($options = array()) {
		$this->collection = Billrun_Factory::db()->getCollection('webhooks');
	}

	/**
	 * trigger webhook(s) to 3rd party (webhook based system)
	 * 
	 * @param array $data the data to be sent
	 * @param array $webhooks the webhooks details to trigger by http(s)
	 * @return array list of results of the webhooks triggered
	 */
	protected function triggerWebhook($data, $webhooks) {
		$ret = [];
		foreach ($webhooks as $webhook) {
			$url = urldecode($webhook['webhook_url']);
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

		$webhooks = $this->fetchWebhookByModuleAndAction($entityName, $action, true);
		if (!$webhooks) {
			return true;
		}
		$data = array(
			'module' => $entityName,
			'action' => $action,
			'before' => $before,
			'after' => $after,
			'modifiedUser' => $modifiedUser['name'] ?? '',
		);
		return $this->triggerWebhook($data, $webhooks);
	}

	/**
	 * afterChargeSuccess webhook
	 * 
	 * @param array $bill the bill paid successfully
	 * 
	 * @return boolean true if success to trigger request, else false
	 */
	public function afterChargeSuccess($bill) {
		$webhooks = $this->fetchWebhookByModuleAndAction('bills', __FUNCTION__, true);
		if (!$webhooks) {
			return true;
		}
		$data = array(
			'module' => 'bills',
			'action' => __FUNCTION__,
			'bill' => $bill,
		);
		return $this->triggerWebhook($data, $webhooks);
	}

	/**
	 * afterPaymentAdjusted webhook
	 * 
	 * @param array $bill the bill paid successfully
	 * 
	 * @return boolean true if success to trigger request, else false
	 */
	public function afterPaymentAdjusted($oldAmount, $newAmount, $aid) {
		$webhooks = $this->fetchWebhookByModuleAndAction('bills', __FUNCTION__, true);
		if (!$webhooks) {
			return true;
		}
		$data = array(
			'module' => 'bills',
			'action' => __FUNCTION__,
			'old' => $oldAmount,
			'new' => $newAmount,
			'aid' => $aid,
		);
		return $this->triggerWebhook($data, $webhooks);
	}

	/**
	 * afterRefundSuccess webhook
	 * 
	 * @param array $refund the refund triggered
	 * 
	 * @return boolean true if success to trigger request, else false
	 */
	public function afterRefundSuccess($refund) {
		$webhooks = $this->fetchWebhookByModuleAndAction('bills', __FUNCTION__, true);
		if (!$webhooks) {
			return true;
		}
		$data = array(
			'module' => 'bills',
			'action' => __FUNCTION__,
			'bill' => $refund,
		);
		return $this->triggerWebhook($data, $webhooks);
	}

	/**
	 * afterInvoiceConfirmed webhook
	 * 
	 * @param array $invoice the invoice confirmed
	 * 
	 * @return boolean true if success to trigger request, else false
	 */
	public function afterInvoiceConfirmed($invoice) {
		$webhooks = $this->fetchWebhookByModuleAndAction('bills', __FUNCTION__, true);
		if (!$webhooks) {
			return true;
		}
		$data = array(
			'module' => 'bills',
			'action' => __FUNCTION__,
			'bill' => $invoice,
		);
		return $this->triggerWebhook($data, $webhooks);
	}

	/**
	 * afterRejection webhook
	 * 
	 * @param array $bill the bill rejected
	 * 
	 * @return boolean true if success to trigger request, else false
	 */
	public function afterRejection($bill) {
		$webhooks = $this->fetchWebhookByModuleAndAction('bills', __FUNCTION__, true);
		if (!$webhooks) {
			return true;
		}
		$data = array(
			'module' => 'bills',
			'action' => __FUNCTION__,
			'bill' => $bill,
		);
		return $this->triggerWebhook($data, $webhooks);
	}

	/**
	 * afterDenial webhook
	 * 
	 * @param array $denial the bill get denial
	 * 
	 * @return boolean true if success to trigger request, else false
	 */
	public function afterDenial($denial) {
		$webhooks = $this->fetchWebhookByModuleAndAction('bills', __FUNCTION__, true);
		if (!$webhooks) {
			return true;
		}
		$data = array(
			'module' => 'bills',
			'action' => __FUNCTION__,
			'bill' => $denial,
		);
		return $this->triggerWebhook($data, $webhooks);
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
		$webhooks = $this->fetchWebhookByModuleAndAction('balances', __FUNCTION__, true);
		if (!$webhooks) {
			return true;
		}
		$data = array(
			'module' => 'balances',
			'action' => __FUNCTION__,
			'before' => $before,
			'after' => $after,
		);
		return $this->triggerWebhook($data, $webhooks);
	}

	/**
	 * beforeEventSave event
	 * 
	 * @return boolean true if success to trigger request, else false
	 */
	public function beforeEventSave($event, $entityBefore, $entityAfter, $eventsManager) {
		$webhooks = $this->fetchWebhookByModuleAndAction('events', 'create', true);
		if (!$webhooks) {
			return true;
		}
		$data = array(
			'module' => 'events',
			'action' => 'create',
			'event' => $event,
			'before' => $entityBefore,
			'after' => $entityAfter,
		);
		return $this->triggerWebhook($data, $webhooks);
	}

	/**
	 * afterEventNotify event
	 * 
	 * @return boolean true if success to trigger request, else false
	 */
	public function afterEventNotify($event) {
		$webhooks = $this->fetchWebhookByModuleAndAction('events', 'notify', true);
		if (!$webhooks) {
			return true;
		}
		$data = array(
			'module' => 'events',
			'action' => 'notify',
			'event' => $event,
		);
		return $this->triggerWebhook($data, $webhooks);
	}

	/**
	 * beforeCollectionStepRun event
	 * 
	 * @return boolean true if success to trigger request, else false
	 */
	public function beforeCollectionStepRun($step) {
		$action = $this->getCollectionAction($step);
		$webhooks = $this->fetchWebhookByModuleAndAction('collection', $action, true);
		if (!$webhooks) {
			return true;
		}

		$data = array(
			'module' => 'collection',
			'action' => $action,
			'step' => $step,
		);

		return $this->triggerWebhook($data, $webhooks);
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
		$body = json_encode($ret);
		Billrun_Factory::log()->debug('webhooks api response with: ' . $body);
		$response->setBody($body);
		$response->setHeader('Content-Type', 'application/json');
		return true;
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
			return true;
		}
		Billrun_Factory::log()->debug("REQUEST: " . print_R($request, 1));
		$webhook = array(
			'webhook_module' => $request->get('webhook_module'),
			'webhook_action' => $request->get('webhook_action'),
			'webhook_url' => $request->get('webhook_url'),
			'webhook_id' => $request->get('webhook_id') ?? uniqid(),
		);

		Billrun_Factory::log()->debug("WEBHOOK: " . print_R($webhook, 1));

		$this->saveWebhooksColl($webhook);

		$ret = array(
			'status' => 1,
			'webhook' => $webhook,
		);

		$response->setBody(json_encode($ret));
		return true;
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
			return true;
		}

		Billrun_Factory::log()->debug("REQUEST: " . print_R($request, 1));

		$id = $params['id'] ?? $request->get('webhook_id');

		$webhook = $this->fetchWebhookByWebhookId($id);

		Billrun_Factory::log()->debug("WEBHOOK: " . print_R($webhook, 1));

		if ($webhook) {
			$ret = array(
				'status' => 1,
				'webhook' => $webhook,
			);
		} else {
			$ret = array(
				'status' => 0,
			);
		}

		$this->apiResponse($ret, $response);
		return true;
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
			return true;
		}

		Billrun_Factory::log()->debug("REQUEST: " . print_R($request, 1));

		$id = $params['id'] ?? $request->get('webhook_id');
		$webhook = array(
			'webhook_module' => $request->get('webhook_module'),
			'webhook_action' => $request->get('webhook_action'),
			'webhook_url' => $request->get('webhook__url'),
			'webhook_id' => $id,
		);

		Billrun_Factory::log()->debug("WEBHOOK: " . print_R($webhook, 1));

		$this->deleteWebhookByWebhookId($id);

		if ($this->saveWebhooksColl($webhook)) {
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
		return true;
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
			return true;
		}

		Billrun_Factory::log()->debug("REQUEST: " . print_R($request, 1));

		$id = $params['id'] ?? $request->get('webhook_id');

		Billrun_Factory::log()->debug("WEBHOOK ID: " . print_R($id, 1));

		if ($this->deleteWebhookByWebhookId($id)) {
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
		return true;
	}

	/**
	 * internal method to save webhooks config to the webhooks collection
	 * 
	 * @return boolean true if success else false
	 */
	protected function saveWebhooksColl($data) {
		if (is_array($data)) {
			$entity = new Mongodloid_Entity($data);
		} elseif ($data instanceof Mongodloid_Entity) {
			$entity = $data;
		} else {
			return false;
		}
		$this->collection->save($entity);
		return true;
	}

	/**
	 * method to fetch webhook by webhook id
	 * 
	 * @param string $webhookid the id to fetch by
	 * 
	 * @return mixed webhook record if found, else false
	 */
	protected function fetchWebhookByWebhookId($webhookid) {
		$query = array(
			'webhook_id' => $webhookid,
		);
		return $this->fetchWebhookByQuery($query, false);
	}

	/**
	 * method to delete webhook by by webhook id
	 * 
	 * @param string $webhookid the id to fetch by
	 * 
	 * @return mixed the record if found, else false
	 */
	protected function deleteWebhookByWebhookId($webhookid) {
		$webhook = $this->fetchWebhookByWebhookId($webhookid);
		if (!$webhook) {
			return false;
		}
		return $this->collection->removeEntity($webhook);
	}

	/**
	 * find webhook(s) by module and action
	 * 
	 * @param string $module module name
	 * @param string $action action name
	 * @param bool $multiple find multiple records or only one record
	 * 
	 * @return type
	 */
	protected function fetchWebhookByModuleAndAction($module, $action, $multiple = true) {
		$query = array(
			'webhook_module' => $module,
			'webhook_action' => $action,
		);
		return $this->fetchWebhookByQuery($query, $multiple);
	}

	/**
	 * method to query webhooks collection
	 * 
	 * @param array $query query to run
	 * @param bool $multiple find multiple records or only one record
	 * 
	 * @return boolean
	 */
	protected function fetchWebhookByQuery($query, $multiple = true) {
		$cursor = $this->collection->query($query)->cursor();
		if ($multiple) {
			$webhooks = array();
			foreach ($cursor as $webhook) {
				$webhooks[] = $webhook;
			}
			if (!count($webhooks)) {
				return false;
			}
		} else {
			$webhooks = $cursor->current();
			if ($webhooks->isEmpty()) {
				return false;
			}
		}
		return $webhooks;
	}

}

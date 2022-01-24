<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

require_once APPLICATION_PATH . '/application/helpers/Portal/Actions.php';

/**
 * Customer Portal controller
 *
 * @package  Controller
 * @since    5.14
 */
class PortalController extends Yaf_Controller_Abstract {

	const ERROR_STATUS_CODE = 400;
	const RESPONSE_CONTENT_TYPE = 'application/json';

	/**
	 * yaf request object
	 *
	 * @var Yaf_Request_Http
	 */
	protected $request;
	
	/**
	 * holds request's raw body
	 *
	 * @var array
	 */
	protected $requestBody = [];
	
	/**
	 * holds request's query
	 *
	 * @var array
	 */
	protected $query = [];
	
	/**
	 * holds request's update
	 *
	 * @var array
	 */
	protected $update = [];
		
	/**
	 * the response object
	 *
	 * @var Yaf_Response_Abstract
	 */
	protected $response;
	
	/**
	 * Portal settings (plugin settings)
	 *
	 * @var mixed
	 */
	protected $settings;
	
	/**
	 * action to run
	 *
	 * @var string
	 */
	protected $action;
	
	/**
	 * OAuth2 token data
	 *
	 * @var array
	 */
	protected $tokenData = [];
        
         /**
	 * holds request's page - the index of the page use for pagination
	 *
	 * @var int
	 */
	protected $page;
        
        /**
	 * holds request's size -the size of the page retrieved
	 *
	 * @var int
	 */
	protected $size;

	public function init() {
		$this->settings = Billrun_Factory::config()->getPluginSettings('portal');
		if (!$this->isOn()) {
			return $this->forward('PortalError', 'notFound');
		}

		$this->request = $this->getRequest();
		$this->requestBody = json_decode(file_get_contents('php://input'), JSON_OBJECT_AS_ARRAY) ?? [];
		$this->update = $this->requestBody['update'] ?? json_decode($this->request->get('update', '[]'), JSON_OBJECT_AS_ARRAY);
		$this->query = json_decode($this->request->get('query', '[]'), JSON_OBJECT_AS_ARRAY);
                $this->page = $this->request->getRequest()['page'] ?? -1;
                $this->size = $this->request->getRequest()['size'] ?? -1;
		$this->response = $this->getResponse();
		$requestParams = $this->request->getParams();
		$this->action = !empty($requestParams) ? array_keys($requestParams)[0] :
			($this->request->getMethod() === 'GET' ? 'get' : 'update');
	
		if (!$this->authenticate('selfcare')) {
			return $this->forward('PortalError', 'unauthenticated');
		}
	
		$this->setUser();
	}
	
	/**
	 * is the controller enabled
	 *
	 * @return boolean
	 */
	protected function isOn() {
		return $this->settings['enabled'] ?? true;
	}

	/**
	 * method to define the api response
	 * 
	 * @param array includes the following fields:
	 * 					- status: 1/0 for success/failure response (default is 1)
	 * 					- code: success/failure error code (optional)
	 * 					- details: response additional details (optional)
	 * 					- error_status_code: HTTP status code in case of an error (default is 400)
	 * 					- content_type: HTTP response Content-Type header value (default is application/json)
	 */
	protected function setResponse($params) {
		$status = $params['status'] ?? 1;
		if (empty($status)) {
			$errorStatusCode = $params['error_status_code'] ?? self::ERROR_STATUS_CODE;
			$this->response->setHeader($this->request->getServer('SERVER_PROTOCOL'), $errorStatusCode);
		}

		$contentType = $params['content_type'] ?? self::RESPONSE_CONTENT_TYPE;
		$this->response->setHeader('Content-Type', $contentType);
		
		$ret = [
			'status' => $status,
		];

		if (!empty($params['code'])) {
			$ret['code'] = $params['code'];
		}
		
		if (!empty($params['details'])) {
			$ret['details'] = $params['details'];
		}
                if (!empty($params['total_pages'])) {
			$ret['total_pages'] = $params['total_pages'];
		}
		
		$this->response->setBody(json_encode($ret));
	}
	
	/**
	 * Account entry point
	 *
	 * @return void
	 */
	public function accountAction() {
		$params = array_merge($this->requestBody, [
			'query' => $this->query,
			'update' => $this->update
		]);
               
		$module = Portal_Actions::getInstance(array_merge($this->getDefaultParams(), ['type' => 'account']));
		$res = $module->run($this->action, $params);
		$this->setResponse($res);
	}
	
	/**
	 * Subscriber entry point
	 *
	 * @return void
	 */
	public function subscriberAction() {
		$params = array_merge($this->requestBody, [
			'query' => $this->query,
			'update' => $this->update,
                        'page' => $this ->page,
                        'size' => $this ->size
		]);

		$module = Portal_Actions::getInstance(array_merge($this->getDefaultParams(), ['type' => 'subscriber']));
		$res = $module->run($this->action, $params);
		$this->setResponse($res);
	}

	/**
	 * Registration entry point
	 *
	 * @return void
	 */
	public function registrationAction() {
		$params = $this->requestBody;
		$module = Portal_Actions::getInstance(array_merge($this->getDefaultParams(), ['type' => 'registration']));
		$res = $module->run($this->action, $params);
		$this->setResponse($res);
	}
        
        /**
	 * Settings entry point
	 *
	 * @return void
	 */
	public function settingsAction() {
		$params = array(
                    'categories' => json_decode($this->request->getRequest()['categories'], false) ?? []
                );
		$module = Portal_Actions::getInstance(array_merge($this->getDefaultParams(), ['type' => 'settings']));
		$res = $module->run($this->action, $params);
		$this->setResponse($res);
	}

	/**
	 * set the user performing the action
	 *
	 * @todo implement based on OAuth2 client_id (need to associate Oauth2 secret to a user)
	 */
	protected function setUser() {
	}
	
	/**
	 * Authenticate the reqeust using OAuth2
	 *
	 * @return boolean
	 */
	protected function authenticate($scope) {
		$oauth = Billrun_Factory::oauth2();
		$oauthRequest = OAuth2\Request::createFromGlobals();
		$oauth->getResourceController();

		switch ($this->request->getActionName()) {
			case 'account':
				$verify = $oauth->verifyResourceRequest($oauthRequest, null, "{$scope} account");
				break;
			case 'subscriber':
				$verify = $oauth->verifyResourceRequest($oauthRequest, null, "{$scope} account")
					|| $oauth->verifyResourceRequest($oauthRequest, null, "{$scope} subscriber");
				break;
			case 'registration':
			case 'login':
			default:
				$verify = $oauth->verifyResourceRequest($oauthRequest, null, $scope);
		}

		if (!$verify) {
			return false;
		}

		$this->tokenData = $oauth->getAccessTokenData($oauthRequest);
		return true;
	}

	protected function getDefaultParams() {
		return array_merge(Billrun_Util::getIn($this->settings, 'configuration.values', []), ['token_data' => $this->tokenData]);
	}
    
    /**
     * get setting (from plugin settings) value
     *
     * @param  mixed $path - array or dot separated stirng
     * @param  mixed $defaulValue
     * @return mixed
     */
    protected function getSetting($path, $defaulValue = null) {
        $pathArr = is_array($path) ? $path : explode('.', $path);
        $settingPath = array_merge(['configuration', 'values'], $pathArr);
        return Billrun_Util::getIn($this->settings, $settingPath, $defaulValue);
    }

	protected function render(string $tpl, array $parameters = null): string {
		return $this->getView()->render('api/index.phtml', $parameters);
	}

}

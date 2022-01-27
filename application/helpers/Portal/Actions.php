<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

require_once APPLICATION_PATH . '/application/helpers/Portal/Actions/Registration.php';
require_once APPLICATION_PATH . '/application/helpers/Portal/Actions/Account.php';
require_once APPLICATION_PATH . '/application/helpers/Portal/Actions/Subscriber.php';
require_once APPLICATION_PATH . '/application/helpers/Portal/Actions/Settings.php';

/**
 * Customer Portal actions
 * 
 * @package  Billing
 * @since    5.14
 */
abstract class Portal_Actions {
    const DATETIME_FORMAT = 'Y-m-d H:i:s';
    const LOGIN_LEVEL_ACCOUNT = 'account';
    const LOGIN_LEVEL_SUBSCRIBER = 'subscriber';
    
    /**
     * action general parameters
     *
     * @var array
     */
    protected $params = [];

    /**
	 * holds unique ID for each request for logging purposes
	 *
	 * @var string
	 */
	protected $loggerID;

    protected $loginLevel = '';
    protected $loggedInEntity = '';

    public function __construct($params = []) {
        $this->params = $params;
    }
    
    /**
     * get actions handler
     *
     * @param  array $params
     * @return Portal_Actions
     */
    public static function getInstance($params = []) {
        $type = ucfirst($params['type'] ?? '');
        $className = "Portal_Actions_{$type}";
        return new $className($params);
    }
    
    /**
     * run customer portal action 
     *
     * @param  string $action
     * @param  array $params
     * @return array
     */
    public function run($action, $params = []) {
        $this->log("Starting action {$action}", Billrun_Log::DEBUG);
        $this->loggerID = uniqid();
        try {
            if (!$this->authorize($action, $params)) {
                throw new Portal_Exception('authorization_failed');
            }
            
            if (!$this->actionExists($action)) {
				$this->log("Invalid action {$action}. Params: " . print_R($params, 1), Billrun_Log::ERR);
				throw new Portal_Exception('permission_denied');
			}                
			$ret = call_user_func([$this, $action], $params);
			$this->log("Got response: " . print_R($ret, 1), Billrun_Log::DEBUG);
			return $this->response(1, 1000, $ret);
        } catch (Portal_Exception $ex) {
			$this->log("{$action} got Error: {$ex->getError()}", Billrun_Log::ERR);
			return $this->response(0, $ex->getCode(), $ex->getMessage());
        } catch (Throwable $ex) {
            $this->log("{$action} got Exception: {$ex->getCode()} - {$ex->getMessage()}", Billrun_Log::ERR);
            return $this->response(0, 1500, 'General Error');
		}
    }
    
    /**
     * check if the given action exists and allowed
     *
     * @param  string $action
     * @return void
     */
    protected function actionExists($action) {
        if (!method_exists($this, $action)) {
            return false;
        }
        
        $reflection = new ReflectionMethod($this, $action);
        return $reflection->isPublic();
    }
    
    /**
     * get response
     *
     * @param  boolean $status
     * @param  string $code
     * @param  mixed $details
     * @return array
     */
    protected function response($status, $code, $details = []) {
        $ret = [
            'status' => $status,
            'code' => $code,
        ];

        if (!empty($details)) {
            $ret['details'] = $details;
        }
        $ret = $this->addToResponse($ret);

        return $ret;
    }
    
    /**
     * internal actions log
     *
     * @param  string $message
     * @param  int $priority
     * @return void
     */
    protected function log($message, $priority) {
		$message = "{$this->loggerID} - {$message}";
		Billrun_Factory::log($message, $priority);
    }
    
    /**
     * Authorize the request
	 *
     * @param  string $action
     * @param  array $params
     * @return boolean
	 */
    protected function authorize($action, &$params = []) {
        $accountModel = new Portal_Actions_Account($this->params);
        $account = $accountModel->get(['query' => $this->getLoggedInEntityQuery()]);
        if ($account) {
            $this->loggedInEntity = $account;
            $this->loginLevel = self::LOGIN_LEVEL_ACCOUNT;
            return true;
        }

        $subscriberModel = new Portal_Actions_Subscriber($this->params);
        $subscriber = $subscriberModel->get(['query' => $this->getLoggedInEntityQuery()]);
        if ($subscriber) {
            $this->loggedInEntity = $subscriber;
            $this->loginLevel = self::LOGIN_LEVEL_SUBSCRIBER;
            return true;
        }
		
        return false;
	}
    
    /**
     * get account's identification query based on the authentication
     *
     * @return array
     */
    protected function getLoggedInEntityQuery() {
		$authField = $this->params['authentication_field'] ?? '';		
        $authValue = $this->params['token_data']['user_id'] ?? '';

        if (empty($authField) || empty($authValue)) {
            return false;
        }
		
        return [
			$authField => $authValue,
		];
	}

    /**
	 * run BillApi action
	 *
	 * @param  array $params
	 * @return mixed
	 */
	protected function runBillApi($params) {
		try {
			$action = $params['request']['action'];
			switch ($action) {
				case 'uniqueget':
				case 'get':
                    $params['force_reload'] = true;
					$modelAction = Models_Action::getInstance($params);
					return $modelAction->execute();
				default:
					$entityModel = Models_Entity::getInstance($params);
					return $entityModel->{$action}();
			}
		} catch (Exception $ex) {
            Billrun_Factory::log("Portal_Actions::runBillApi got Error: {$ex->getCode()} - {$ex->getMessage()}", Billrun_Log::ERR);
            return false;
		}
	}
	
	/**
	 * get parameters required to run BillApi
	 *
	 * @param  mixed $action
	 * @param  mixed $query
	 * @param  mixed $update
	 * @return void
	 */
	protected function getBillApiParams($module, $action, $query = [], $update = [], $sort = []) {
		br_yaf_register_autoload("Models", APPLICATION_PATH . '/application/modules/Billapi');
		Billrun_Factory::config()->addConfig(APPLICATION_PATH . "/conf/modules/billapi/{$module}.ini");
		
        $ret = [
			'collection' => $module,
			'request' => [
				'collection' => $module,
				'action' => $action,
			],
			'settings' => Billrun_Factory::config()->getConfigValue("billapi.{$module}.{$action}", []),
		];

		if (!empty($query)) {
			$ret['request']['query'] = json_encode($query);
		}

		if (!empty($update)) {
			$ret['request']['update'] = json_encode($update);
		}
                if (!empty($sort)) {
			$ret['request']['sort'] = json_encode($sort);
		}

		return $ret;
	}

    /**
	 * format entity details to return
	 *
	 * @param  array $entity
	 * @return array
	 */
	protected function getDetails($entity) {
		if (empty($entity)) {
			return false;
		}

        foreach ($entity as $field => $value) {
            if ($value instanceof Mongodloid_Date) {
                $entity[$field] = Billrun_Utils_Mongo::convertMongoDatesToReadable($value);
            }
        }

        return $entity;
    }

    /**
     * add fields to response
     *
     * @param  array response
     * @return array the updated response
     */
    protected function addToResponse($response) {

        return $response;
    }
}

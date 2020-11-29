<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

require_once APPLICATION_PATH . '/application/helpers/Portal/Actions/Registration.php';
require_once APPLICATION_PATH . '/application/helpers/Portal/Actions/Account.php';

/**
 * Customer Portal actions
 * 
 * @package  Billing
 * @since    5.14
 */
abstract class Portal_Actions {
    
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
            if (!$this->authenticate($params)) {
                throw new Portal_Exception('authentication_failed');
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
     * Authenticate the request
	 *
     * @param  array $params
     * @return boolean
	 */    
    protected abstract function authenticate($params = []);
}

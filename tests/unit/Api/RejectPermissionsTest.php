<?php

// The suite bootstrap re-points the Yaf local-namespace path to the Billapi module
// (yaf < 3.2), so Action_Base can no longer be autoloaded - load it explicitly.
require_once APPLICATION_PATH . '/application/helpers/Action/Base.php';
require_once APPLICATION_PATH . '/application/controllers/Action/Reject.php';

/**
 * The reject API (RejectAction) requires the write permission (BRCD-5536). The
 * check runs centrally in ApiController::enforceActionPermission() before the
 * action executes (admin unless the action states a lower level in
 * getPermissionLevel(); reject states write). That runs only for web requests,
 * so the permission gate (allowed()) at reject's level is exercised directly:
 * no logged-in user or the read role is refused with
 * Billrun_Exceptions_NoPermission, a write or admin user passes.
 */
class RejectPermissionsTest extends \Codeception\Test\Unit {

	protected $originalStorage;

	protected function _before() {
		$_REQUEST = array();
		$_POST = array();
		$this->originalStorage = Billrun_Factory::auth()->getStorage();
		Billrun_Factory::auth()->setStorage(new Zend_Auth_Storage_NonPersistent());
	}

	protected function _after() {
		Billrun_Factory::auth()->setStorage($this->originalStorage);
	}

	/**
	 * label => [roles of the logged-in user (null - not logged in), expected exception class (null - allowed)]
	 */
	protected function getPermissionTests() {
		return array(
			'not logged in - refused' => array(null, 'Billrun_Exceptions_NoPermission'),
			'read user - refused' => array(array('read'), 'Billrun_Exceptions_NoPermission'),
			'write user - allowed' => array(array('write'), null),
			'admin - allowed' => array(array('admin'), null),
		);
	}

	public function testRejectRequiresWritePermission() {
		foreach ($this->getPermissionTests() as $label => $test) {
			list($roles, $expectedClass) = $test;
			$this->login($roles);
			$exception = null;
			try {
				$allowed = new ReflectionMethod('RejectAction', 'allowed');
				$allowed->setAccessible(true);
				$allowed->invoke($this->getRejectAction());
			} catch (Exception $e) {
				$exception = $e;
			}
			if (is_null($expectedClass)) {
				$this->assertNull($exception, $label . ($exception ? ': ' . $exception->getMessage() : ''));
			} else {
				$this->assertInstanceOf($expectedClass, $exception, $label);
			}
		}
	}

	protected function login($roles) {
		$storage = Billrun_Factory::auth()->getStorage();
		$storage->clear();
		if ($roles !== null) {
			$storage->write(array('current_user' => array('username' => 'unit_reject_' . implode('_', $roles), 'roles' => $roles)));
		}
	}

	protected function getRejectAction() {
		$request = new Yaf_Request_Simple('POST', 'Index', 'api', 'reject', array());
		$view = new Yaf_View_Simple(APPLICATION_PATH . '/application/views');
		return new RejectAction($request, new Yaf_Response_Cli(), $view);
	}

}

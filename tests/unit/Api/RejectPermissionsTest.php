<?php

require_once APPLICATION_PATH . '/application/controllers/Action/Reject.php';

/**
 * The reject API (RejectAction) is available to admin users only (BRCD-5536):
 * without a logged-in user or without the admin role the call is refused with
 * Billrun_Exceptions_NoPermission; an admin call reaches the action body, which
 * fails on the missing "rejections" input (Billrun_Exceptions_Api) before any
 * payment is touched.
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
	 * label => [roles of the logged-in user (null - not logged in), expected exception class]
	 */
	protected function getPermissionTests() {
		return array(
			'not logged in - refused' => array(null, 'Billrun_Exceptions_NoPermission'),
			'write user - refused' => array(array('write'), 'Billrun_Exceptions_NoPermission'),
			'admin - reaches the action body' => array(array('admin'), 'Billrun_Exceptions_Api'),
		);
	}

	public function testRejectIsAllowedToAdminsOnly() {
		foreach ($this->getPermissionTests() as $label => $test) {
			list($roles, $expectedClass) = $test;
			$this->login($roles);
			$exception = null;
			try {
				$this->getRejectAction()->execute();
			} catch (Exception $e) {
				$exception = $e;
			}
			$this->assertInstanceOf($expectedClass, $exception, $label);
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

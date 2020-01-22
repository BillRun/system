<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Test action class
 *
 * @package  Action
 * 
 * @since    4.0
 */
class CreditInstallmentsAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;
	
	/**
	 * method to execute the credit installments requested action
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		$this->allowed();
		$request = $this->getRequest();
		try {
			switch ($request->get('action')) {
				case 'prepone' :
					$response = $this->preponeCreditInstallments($request->get('query'));
					break;
			}

			if ($response !== FALSE) {
				$this->getController()->setOutput(array(array(
						'status' => 1,
						'desc' => 'success',
						'input' => $request->getPost(),
						'details' => $response,
				)));
			}
		} catch (Exception $ex) {
			$this->setError($ex->getMessage(), $request->getPost());
			return;
		}
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
	}
	
	protected function preponeCreditInstallments(){
		
	}
}

<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2017 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * This class deals with password retrieval.
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       5.5
 */
class PasswordRetrievalAction extends ApiAction {
	
	/**
	 * The logic to be executed when this API plugin is called.
	 */
	public function execute() {
		$request = $this->getRequest();
		$email = $request->get('email');
		$user = Billrun_Factory::db()->usersCollection()->query(array('username' => $email))->cursor()->current();
		if ($user->isEmpty()) {
			return $this->setError("The user is not exists, please try again", $request);
		}
		$id = $user->getRawData()['_id']->{'$id'};
		$action = $request->get('action');
		if ($action == 'sendForm') {
			$data = array(
				'_id' => $id,
			);
			$secrets = Billrun_Factory::config()->getConfigValue("shared_secret");
			if (!is_array(current($secrets))) {  //for backward compatibility 
				$secrets = array($secrets);
			}
			$today = time();
			foreach ($secrets as $shared) {
				if (!isset($shared['from']) && !isset($shared['to'])) {  //for backward compatibility 
					$secret = $shared;
					break;
				}
				if ($shared['from']->sec < $today && $shared['to']->sec > $today) {
					$secret = $shared;
					break;
				}
			}
			$signed = Billrun_Utils_Security::addSignature($data, $secret['key']);
			$params = array(
				'sig' => $signed['_sig_'],
				't' => $signed['_t_']
			);
			$url = $this->buildPasswordChangeUrl($request, $id, $params);
			$resetMessage = $this->buildResetMessage($url);
			Billrun_Util::sendMail("BillRun Reset Password", $resetMessage, array($email));
		}
	
		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'input' => $request->getRequest(),
		)));
	}

	protected function buildResetMessage($url) {
		return "Hello,\n\n" . "Click here to reset yout password:" . $url;
	}
	
	protected function buildPasswordChangeUrl($request, $id, $data) {
		$urlTemplate = Billrun_Factory::config()->getConfigValue('billrun.changepassword.url');
		$pageRoot = $request->getServer()['HTTP_HOST'];
		$protocol = empty($request->getServer()['HTTPS']) ? 'http' : 'https';
		$returnPage = sprintf($urlTemplate, $protocol, $pageRoot, $id, $data['sig'], $data['t']);

		return $returnPage;
	}

}

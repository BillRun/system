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
		if (!$user->isEmpty()) {
			$id = $user->getRawData()['_id']->{'$id'};
			$action = $request->get('action');
			if ($action == 'sendForm') {
				$data = array(
					'_id' => $id,
				);
				$secret = Billrun_Utils_Security::getValidSharedKey();
				$signed = Billrun_Utils_Security::addSignature($data, $secret['key']);
				$params = array(
					'sig' => $signed['_sig_'],
					't' => $signed['_t_'],
					'user' => $email,
				);
				$url = $this->buildPasswordChangeUrl($request, $id, $params);
				$resetMessage = $this->buildResetMessage($url);
				Billrun_Factory::log("Request to change password from " . $email, Zend_Log::INFO);
				Billrun_Util::sendMail("BillRun(R) Cloud Password Reset", $resetMessage, array($email), array(), true);
			}
		}

		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'input' => $request->getRequest(),
		)));
	}

	protected function buildResetMessage($url) {
		$linkTimeLimit = Billrun_Factory::config()->getConfigValue('changepassword.email.link_expire', '24 hours');
		$logoLocation = Billrun_Factory::config()->getConfigValue('changepassword.email.logo', '24 hours');
		return '<img src="' . $logoLocation . '" style="vertical-align:bottom">' .
				"<br><br>Hello,<br><br>" . "You recently requested a password reset.<br><br>" .
				"To change your BillRun® Cloud password, please click here:<br><br>" . $url .
				"<br><br>The link will expire in " . $linkTimeLimit .
				"<br><br>If you ignore this message, your password won't be changed." .
				"<br><br>If you didn't request a password reset, " .
				'<a href="mailto:<cloud_support@billrun.com>?subject=<I suspect someone is trying to steal my password>">let us know</a>.' .
				"<br><br>Thanks for using BillRun® Cloud! The BillRun® team";
	}

	protected function buildPasswordChangeUrl($request, $id, $data) {
		$urlTemplate = Billrun_Factory::config()->getConfigValue('billrun.changepassword.url');
		$pageRoot = $request->getServer()['HTTP_HOST'];
		$protocol = empty($request->getServer()['HTTPS']) ? 'http' : 'https';
		$returnPage = sprintf($urlTemplate, $protocol, $pageRoot, $id, $data['sig'], $data['t'], $data['user']);

		return $returnPage;
	}

}

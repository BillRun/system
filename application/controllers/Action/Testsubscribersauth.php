<?php

require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

class TestsubscribersauthAction extends ApiAction
{
	use Billrun_Traits_Api_UserPermissions;

	public function execute()
	{
		$this->allowed();

		$authentication = Billrun_Factory::config()->getConfigValue(
			'subscribers.external_authentication',
			array()
		);

		if (
			empty($authentication)
			|| empty($authentication['type'])
			|| empty($authentication['access_token_url'])
			|| empty($authentication['data']['client_id'])
			|| empty($authentication['data']['client_secret'])
		) {
			$this->setError('Missing external subscribers authentication configuration');
			return false;
		}

		$authentication['cache'] = false;

		try {
			$request = new Billrun_Http_Request();

			$authenticator = Billrun_Http_Authentication_Base::getInstance(
				$request,
				$authentication
			);

			if (!$authenticator) {
				throw new Exception('Unsupported authentication type');
			}

			$authenticator->authenticate();

			$this->setSuccess();
			return true;
		} catch (Throwable $e) {
			$this->setError($e->getMessage());
			return false;
		} catch (Exception $e) {
			$this->setError($e->getMessage());
			return false;
		}
	}

	protected function getPermissionLevel()
	{
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_ADMIN;
	}
}

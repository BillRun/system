<?php

require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

class TestsmsAction extends ApiAction
{
	use Billrun_Traits_Api_UserPermissions;

	public function execute()
	{
		$this->allowed();

		$request = $this->getRequest();
		$recipient = trim($request->get('recipient'));
		$smserConfig = Billrun_Factory::config()->getConfigValue('smser', array());

		if (empty($recipient)) {
			$this->setError('Missing SMS recipient', $request->getPost());
			return false;
		}

		if (empty($smserConfig) || empty($smserConfig['type'])) {
			$this->setError('Missing SMS configuration', $request->getPost());
			return false;
		}

		try {
			$smser = Billrun_Factory::smser($smserConfig);

			if (!$smser) {
				$this->setError(
					'Unsupported SMS provider type: ' . $smserConfig['type'],
					$request->getPost()
				);
				return false;
			}

			$message = 'This is a test SMS from BillRun. Current date time: '
				. date('Y-m-d H:i:s');

			$result = $smser
				->setTo($recipient)
				->setBody($message)
				->send();

			if ($result === false || $result === null) {
				$this->setError('Failed to send test SMS', $request->getPost());
				return false;
			}

			$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'input' => $request->getPost(),
				'details' => array(),
			)));

			return true;
		} catch (Throwable $e) {
			$this->setError($e->getMessage(), $request->getPost());
			return false;
		} catch (Exception $e) {
			$this->setError($e->getMessage(), $request->getPost());
			return false;
		}
	}

	protected function getPermissionLevel()
	{
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_ADMIN;
	}
}

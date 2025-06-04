<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2024 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing queue controller class
 * 
 * @package  Controller
 * @since    5.3
 */
class QueueController extends ApiController {

	use Billrun_Traits_Api_UserPermissions;
	
	public function init() {
		$this->allowed();
		return parent::init();
	}
	
	public function indexAction() {
		$this->getView()->outputMethod = array('Zend_Json', 'encode');
		$this->setOutput([['status' => 1, 'desc' => 'do nothing']]);
	}

	public function pushAction() {
		$request = $this->getRequest();
		$job_type = (string) $request->get('job_type');
		if (empty($job_type)) {
			$this->setOutput([['status' => 0, 'desc' => 'missing input: job_type']]);
			return;
		}
		$config = json_decode($request->get('config'), true);
		$this->getView()->outputMethod = array('Zend_Json', 'encode');
		$schedule = $request->get('schedule');
		$messageQueue = Billrun_Jobsmanager::getInstance()->push($job_type, $config, null, $schedule);
		$ret = [
			'status' => !empty($messageQueue) ? 1 : 0,
			'details' => $messageQueue,
			'description' => Billrun_Jobsmanager::getInstance()->getLastError(),
		];
		$this->setOutput([$ret]);
	}
	
	public function parentjobstatsAction() {
		$request = $this->getRequest();
		$job_md5 = (string) $request->get('job_md5');
		$model = new JobsqueueModel();
		$data = $model->getParentStats($job_md5);
		$ret = [
			'status' => 1,
			'details' => $data,
		];
		$this->setOutput([$ret]);
	}
	
	public function jobqueuetypesAction() {
		$default_job_types = ['Cycle', 'Cycle_Account', 'Confirm', 'Confirm_Account', 'Charging', 'Charging_Account'];
		$job_types = Billrun_Factory::config()->getConfigValue('worker.job_types', $default_job_types);
		$this->setOutput([['status' => 1, 'details' => $job_types]]);
	}
	
	public function latestjobAction() {
		$request = $this->getRequest();
		$job_type = (string) $request->get('job_type');
		if (empty($job_type)) {
			$this->setOutput([['status' => 0, 'desc' => 'missing input: job_type']]);
			return;
		}
		$limit = (int) $request->get('limit', 1000);
		if ($limit < 1 || $limit > 1000) {
			$this->setOutput([['status' => 0, 'desc' => 'limit should be in range of 1 to 1000']]);
			return;
		}
		$model = new JobsqueueModel();
		$data = $model->getLatestJob($job_type, $limit);
		$ret = [
			'status' => 1,
			'details' => $data,
		];
		$this->setOutput([$ret]);
	}
	
	public function cycleaccountsleftAction() {
		$request = $this->getRequest();
		$billrun_key = (string) $request->get('billrun_key');
		if (empty($billrun_key)) {
			$this->setOutput([['status' => 0, 'desc' => 'missing input: billrun_key']]);
			return;
		}
		$model = new JobsqueueModel();
		$data = $model->getCycleAccountsLeft($billrun_key);
		$ret = [
			'status' => 1,
			'details' => $data,
		];
		$this->setOutput([$ret]);
	}
	
	public function canceljobAction() {
		$request = $this->getRequest();
		$job_md5 = (string) $request->get('job_md5');
		if (empty($job_md5)) {
			$ret = [
				'status' => 0,
				'desc' => 'job_md5 input is empty or not string',
			];
			$this->setOutput([$ret]);
		}
		$model = new JobsqueueModel();
		$status = $model->cancelJob($job_md5);
		if ($status['ok'] == 1 && $status['nModified'] === 1) {
			$status = 1;
			$desc = 'job cancelled';
		} else {
			$status = 0;
			$desc = 'job did not found or already done/cancelled';
		}
		$ret = [
			'status' => $status,
			'desc' => $desc,
		];
		$this->setOutput([$ret]);

	}
	
	protected function render(string $tpl, array $parameters = null): string {
		return parent::render('index', $parameters);
	}
	
	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
	}

}
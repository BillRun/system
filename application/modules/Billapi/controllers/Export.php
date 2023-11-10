<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/modules/Billapi/controllers/Billapi.php';

/**
 * Billapi controller for getting BillRun entities
 *
 * @package  Billapi
 * @since    5.5
 */
class ExportController extends BillapiController {

	protected function runOperation() {
		$this->action = Models_Action::getInstance($this->params);
		if (!$this->action) {
			throw new Billrun_Exceptions_Api(999999, [], 'Action cannot be found');
		}
		$this->output->status = 1;
		try {
			$results = $this->action->execute();
			$this->output->details = $results;
		} catch (Exception $ex) {
			$this->output->status = 0;
			$this->output->errorCode = $ex->getCode();
			$this->output->desc = $ex->getMessage();
			Billrun_Factory::log($this->output->errorCode . ': ' . $this->output->desc, Zend_Log::ERR);
		}
	}

			/**
	 *
	 * @param string $tpl the default tpl the controller used; this will be override to use the general admin layout
	 * @param array $parameters parameters of the view
	 *
	 * @return string the render layout including the page (component)
	 */
	protected function render(string $tpl, array $parameters = null): string {
		$filename = !empty($this->params['request']['file_name']) ? json_decode($this->params['request']['file_name']) : 'export_' . date('Ymd');
		if (isset($this->params['options']['delimiter'])) {
			$this->getView()->delimiter = $this->params['request']['delimiter'];
		} else if (isset($this->settings['delimiter'])) {
			$this->getView()->delimiter = $this->settings['delimiter'];
		} else {
			$this->getView()->delimiter = ',';
		}
		$resp = $this->getResponse();
		$resp->setHeader("Cache-Control", "max-age=0");
		$resp->setHeader("Content-type",  "application/csv; charset=UTF-8");
		$resp->setHeader('Content-disposition', 'inline; filename="' . $filename . '.csv"');
		return $this->getView()->render('csv.phtml', $parameters);
	}



}
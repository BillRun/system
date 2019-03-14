<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

class GenerateExpectedAction extends ApiAction {

	use Billrun_Traits_Api_UserPermissions;

	protected $request = null;
	protected $aggregator = null;
	protected $params = [];
	protected $stamp = null;

	public function execute() {
		$this->allowed();
		$this->request = $this->getRequest();
		try {
			$this->params = array_merge($this->request->getRequest(), $this->request->getParams());
			Billrun_Factory::dispatcher()->trigger('beforeGenerateExpected', array($this));
			$details = $this->generateExpected();
			Billrun_Factory::dispatcher()->trigger('afterGenerateExpected', array($this, $details));
			$this->setSuccess($details);
		} catch (Exception $ex) {
			$this->setError($ex->getMessage(), $this->request->getPost());
			Billrun_Factory::log(print_r(array('error' => $ex->getMessage(), 'input' => $this->request->getPost()), 1), Zend_Log::ERR);
			return;
		}
	}

	protected function shouldGeneratePdf() {
		return Billrun_Util::getIn($this->params, 'generate_pdf', true);
	}

	protected function shouldDownloadPdf() {
		if (!$this->shouldGeneratePdf()) {
			return false;
		}
		return Billrun_Util::getIn($this->params, 'download_pdf', true);
	}

	protected function isFakeCycle() {
		return Billrun_Util::getIn($this->params, 'fake_cycle', true);
	}

	protected function getStamp() {
		if (empty($this->stamp)) {
			$this->stamp = Billrun_Util::getIn($this->params, 'billrun_key', Billrun_Billingcycle::getBillrunKeyByTimestamp());;
		}
		return $this->stamp;
	}

	protected function getAggregatorOptions() {
		$options = [
			'type' => 'customer',
			'stamp' => $this->getStamp(),
			'fake_cycle' => $this->isFakeCycle(),
			'generate_pdf' => $this->shouldGeneratePdf(),
		];
		
		if (!empty($this->params['data'])) {
			$options['type'] = 'customernondb';
			$options['data'] = $this->params['data'];
			$options['aid'] = $this->params['aid'] ?: null;
		} else if (isset($this->params['aid'])) {
			$aids = is_array($this->params['aid']) ? $this->params['aid'] : [$this->params['aid']];
			$options['force_accounts'] = $aids;
		}
		
		return $options;
	}

	protected function generateExpected() {
		$options = $this->getAggregatorOptions();
		Billrun_Factory::dispatcher()->trigger('beforeGenerateExpectedAggregatorLoad', array($this, $options));
		$this->aggregator = Billrun_Aggregator::getInstance($options);
		$this->aggregator->load();
		
		if (!$this->aggregator->aggregate()) {
			throw new Exception("Failed to generate expected", 0);
		}

		return $this->setOutput();
	}

	protected function setOutput() {
		switch ($this->getOutputMethod()) {
			case 'download_pdf':
				return $this->downloadPdf();
			case 'pdf_path':
				return $this->getPdfPath();
			case 'discounts':
				return $this->getDiscounts();
			case 'invoice_meta_data':
				return $this->getInvoiceMetaData();
			default:
				return true;
		}
	}

	protected function getOutputMethod() {
		if (isset($this->params['output'])) {
			return $this->params['output'];
		}

		if ($this->shouldGeneratePdf()) {
			return $this->shouldDownloadPdf() ? 'download_pdf' : 'pdf_path';
		}
		
		return 'default';
	}

	protected function getBillable() {
		if (isset($this->params['billable'])) {
			return $this->params['billable'];
		}

		//TODO: return aggregate data
	}
	
	protected function downloadPdf() {
		$pdfPath = $this->getPdfPath();
		return $this->downloadFile($pdfPath);
	}
	
	protected function getPdfPath() {
		$aid = $this->params['aid'];
		return Generator_WkPdf::getTempDir($this->stamp) . "/pdf/{$this->stamp}_{$aid}_0.pdf";
	}

	protected function downloadFile($filePath, $fileType = 'pdf', $fileName = '') {
		$cont = file_get_contents($filePath);

		if (!$cont) {
			$this->setError('Cannot get content from file ' . $filePath);
		}

		if (empty($fileName)) {
			$fileName = basename($filePath);
		}

		header('Content-disposition: inline; filename="' . $fileName . '"');
		header('Cache-Control: public, must-revalidate, max-age=0');
		header('Pragma: public');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Content-Type: application/' . $fileType);
		Billrun_Factory::log('Transfering file content from : ' . $filePath . ' to http connection');
		echo $cont;
		die();
	}

	protected function getDiscounts() {
		$dm = new Billrun_DiscountManager();
		$eligibilityOnly = $this->params['eligible_only'] ?: false;
		$invoice = $this->getInvoiceMetaData(false);
		$types = ['monetary', 'percentage'];
		
		//Get all the eligible discounts for  this  billing cycle
		return array_map(function($dis) {
			return $dis->getRawData();
		}, $dm->getEligibleDiscounts($invoice, $types, $eligibilityOnly));
	}

	protected function getInvoiceMetaData($rawData = true) {
		$data = $this->aggregator->getData();
		$accountData = Billrun_Util::getIn($data, 0);
		if (empty($accountData)) {
			return [];
		}
		$ret = $accountData->getInvoice();
		if ($ret && $rawData) {
			$ret = $ret->getRawData();
		}
		
		return $ret;

	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
	}

}

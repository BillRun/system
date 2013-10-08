<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing Golan Csv generator class
 * require to generate csvs for comparison with older billing systems / charge using credit guard
 *
 * @todo this class should inherit from abstract class Generator_Golan
 * @package  Billing
 * @since    0.5
 */
class Generator_Golancsv extends Billrun_Generator {

	/**
	 * The VAT value (TODO get from outside/config).
	 */
	const VAT_VALUE = 1.17;

	static protected $type = 'golancsv';
	protected $csvContent = '';
	protected $csvPath;

	/**
	 *
	 * @var int number of accounts to write to the csv file(s) at once
	 */
	protected $blockSize = 5000;

	public function __construct($options) {
		parent::__construct($options);

		if (isset($options['csv_filename'])) {
			$this->csvPath = $this->export_directory . '/' . $options['csv_filename'] . '.csv';
		} else {
			$this->csvPath = $this->export_directory . '/' . $this->getStamp() . '.csv';
		}

		$this->loadCsv();
	}

	/**
	 * load csv file to write the generating info into
	 */
	protected function loadCsv() {
		if (file_exists($this->csvPath)) {
			$this->csvContent = file_get_contents($this->csvPath);
		}
	}

	/**
	 * write row to csv file for generating info into in
	 * 
	 * @param string $row the row to write into
	 * 
	 * @return boolean true if succes to write info else false
	 */
	protected function csv($row) {
		return file_put_contents($this->csvPath, $row, FILE_APPEND);
	}

	/**
	 * load the container the need to be generate
	 */
	public function load() {
		$billrun = Billrun_Factory::db()->billrunCollection();

		$this->data = $billrun
				->query('billrun_key', $this->stamp)
				->exists('invoice_id')
				->cursor();

		Billrun_Factory::log()->log("aggregator entities loaded: " . $this->data->count(), Zend_Log::INFO);

		Billrun_Factory::dispatcher()->trigger('afterGeneratorLoadData', array('generator' => $this));
	}

	/**
	 * execute the generate action
	 */
	public function generate() {
		// generate xml
		if (!$this->data->isEmpty()) {
			$this->writeHeader();
		}
		$this->create();

		// generate csv
//		$this->csv();
	}

	protected function create() {
		// use $this->export_directory
		$short_format_date = 'd/m/Y';
		foreach ($this->data as $account) {
			$vat = isset($account['vat']) ? $account['vat'] : floatval(Billrun_Factory::config()->getConfigValue('pricing.vat', 0.18));
			$acc_row = array();
			$acc_row['TotalFlat'] = 0;
			$acc_row['TotalChargeVat'] = $this->getAccountTotalChargeVat($account);
			foreach ($account['subs'] as $subscriber) {
				$acc_row['GTSerialNumber'] = $sub_row['GTSerialNumber'] = $account['aid'];
				$sub_row['subscriber_id'] = $subscriber['sid'];
				$sub_row['TotalChargeVat'] = $this->getSubscriberTotalChargeVat($subscriber);
				$acc_row['XmlIndicator'] = $sub_row['XmlIndicator'] = $this->getXmlIndicator($account);
				$acc_row['TotalFlat'] += $sub_row['TotalFlat'] = $this->getTotalFlat($subscriber);
				$acc_row['TotalExtraOverPackage'] += $sub_row['TotalExtraOverPackage'] = $this->getTotalExtraOverPackage($subscriber);
//				$acc_row['TotalExtraOutOfPackage'] += $sub_row['TotalExtraOutOfPackage'] = $this->getTotalExtraOutOfPackage($subscriber); // we don't have this value for instant retrieval
				$acc_row['ManualCorrectionCredit'] += $sub_row['ManualCorrectionCredit'] = $this->getManualCorrectionCredit($subscriber);
				$acc_row['ManualCorrectionCharge'] += $sub_row['ManualCorrectionCharge'] = $this->getManualCorrectionCharge($subscriber);
				$acc_row['ManualCorrection'] += $sub_row['ManualCorrection'] = $sub_row['ManualCorrectionCredit'] + $sub_row['ManualCorrectionCharge'];
				$acc_row['OutsidePackageNoVatTap3'] += $sub_row['OutsidePackageNoVatTap3'] = $this->getOutsidePackageNoVatTap3($subscriber);
				$acc_row['TotalVat'] += $sub_row['TotalVat'] = $this->getTotalVat($subscriber);
				$acc_row['TotalCharge'] += $sub_row['TotalCharge'] = $this->getTotalCharge($subscriber);
				$sub_row['isAccountActive'] = $this->getIsAccountActive($subscriber);
				$sub_row['curPackage'] = $this->getCurPackage($subscriber);
				$sub_row['nextPackage'] = $this->getNextPackage($subscriber);
				$sub_row['TotalChargeVatData'] = $this->getTotalChargeVatData($subscriber, $vat);
				$sub_row['CountOfKb'] = $this->getCountOfKb($subscriber);
			}
			$acc_row['CountActiveCli'] = count($account['subs']);
			Billrun_Factory::log()->log("invoice id created " . $invoice_id . " for the account", Zend_Log::INFO);

			$this->addRowToCsv($sub_row);
		}
	}

	protected function getXmlIndicator($account) {
		return isset($account['invoice_file']) ? "OK" : "SKIPPED";
	}

	protected function getTotalFlat($subscriber) {
		return $this->getVatableFlat($subscriber) + $this->getVatFreeFlat($subscriber);
	}

	protected function getTotalExtraOverPackage($subscriber) {
		return $this->getVatableOverPlan($subscriber) + $this->getVatFreeOverPlan($subscriber);
	}

	protected function getManualCorrectionCredit($subscriber) {
		return floatval(isset($subscriber['costs']['credit']['refund']['vatable']) ? $subscriber['costs']['credit']['refund']['vatable'] : 0) +
				floatval(isset($subscriber['costs']['credit']['refund']['vat_free']) ? $subscriber['costs']['credit']['refund']['vat_free'] : 0);
	}

	protected function getManualCorrectionCharge($subscriber) {
		return floatval(isset($subscriber['costs']['credit']['charge']['vatable']) ? $subscriber['costs']['credit']['charge']['vatable'] : 0) +
				floatval(isset($subscriber['costs']['credit']['charge']['vat_free']) ? $subscriber['costs']['credit']['charge']['vat_free'] : 0);
	}

	protected function getOutsidePackageNoVatTap3($subscriber) {
		return floatval(isset($subscriber['costs']['out_plan']['vat_free']) ? $subscriber['costs']['out_plan']['vat_free'] : 0);
	}

	protected function getTotalVat($subscriber) {
		return $this->getTotalAfterVat($subscriber) - $this->getTotalBeforeVat($subscriber);
		;
	}

	protected function getTotalCharge($subscriber) {
		return $this->getTotalBeforeVat($subscriber);
	}

	protected function getIsAccountActive($subscriber) {
		$is_active = -1;
		if (isset($subscriber['subscriber_status'])) {
			if ($subscriber['subscriber_status'] == "open") {
				$is_active = 1;
			} else if ($subscriber['subscriber_status'] == "closed") {
				$is_active = 0;
			}
		}
		return $is_active;
	}

	/**
	 * We cannot get the lines from balances as it's not necessarily the correct previous plan
	 * @param type $subscriber
	 * @return type
	 */
	protected function getCurPackage($subscriber) {
		return;
	}

	protected function getNextPackage($subscriber) {
		$plan_name = $this->getPlanName($subscriber);
		if ($plan_name == '') {
			$plan_name = 'NO_GIFT';
		}
		return $plan_name;
	}

	protected function getTotalChargeVatData($subscriber, $vat) {
		$price_before_vat = (isset($subscriber['breakdown']['in_plan']['base']['INTERNET_BILL_BY_VOLUME']['totals']['data']['cost']) ? $subscriber['breakdown']['in_plan']['base']['INTERNET_BILL_BY_VOLUME']['totals']['data']['cost'] : 0) +
				(isset($subscriber['breakdown']['over_plan']['base']['INTERNET_BILL_BY_VOLUME']['totals']['data']['cost']) ? $subscriber['breakdown']['over_plan']['base']['INTERNET_BILL_BY_VOLUME']['totals']['data']['cost'] : 0) +
				(isset($subscriber['breakdown']['out_plan']['base']['INTERNET_BILL_BY_VOLUME']['totals']['data']['cost']) ? $subscriber['breakdown']['out_plan']['base']['INTERNET_BILL_BY_VOLUME']['totals']['data']['cost'] : 0);
		return $price_before_vat * (1 + $vat);
	}

	/**
	 * 
	 * @param array $subscriber the subscriber billrun entry
	 */
	protected function getPlanName($subscriber) {
		$plan_name = '';
		if (isset($subscriber['lines']['flat']['refs'][0])) {
			$lines_coll = Billrun_Factory::db()->linesCollection();
			$flat_line = $lines_coll->getRef($subscriber['lines']['flat']['refs'][0]);
			if ($flat_line) {
				$flat_line->collection($lines_coll);
				$plan = $flat_line['plan_ref'];
				if (!$plan->isEmpty() && isset($plan['name'])) {
					$plan_name = $plan['name'];
				}
			}
		}
		return $plan_name;
	}

	protected function getTotalBeforeVat($subscriber) {
		return isset($subscriber['totals']['before_vat']) ? $subscriber['totals']['before_vat'] : 0;
	}

	protected function getTotalAfterVat($subscriber) {
		return isset($subscriber['totals']['after_vat']) ? $subscriber['totals']['after_vat'] : 0;
	}

	protected function getAccountTotalChargeVat($account) {
		return isset($account['totals']['after_vat']) ? $account['totals']['after_vat'] : 0;
	}

	protected function getSubscriberTotalChargeVat($subscriber) {
		return isset($subscriber['totals']['after_vat']) ? $subscriber['totals']['after_vat'] : 0;
	}

	protected function getVatableFlat($subscriber) {
		return isset($subscriber['costs']['flat']['vatable']) ? $subscriber['costs']['flat']['vatable'] : 0;
	}

	protected function getVatFreeFlat($subscriber) {
		return isset($subscriber['costs']['flat']['vat_free']) ? $subscriber['costs']['flat']['vat_free'] : 0;
	}

	protected function getVatableOverPlan($subscriber) {
		return isset($subscriber['costs']['over_plan']['vatable']) ? $subscriber['costs']['over_plan']['vatable'] : 0;
	}

	protected function getVatFreeOverPlan($subscriber) {
		return isset($subscriber['costs']['over_plan']['vat_free']) ? $subscriber['costs']['over_plan']['vat_free'] : 0;
	}

	protected function addRowToCsv($invoice_id, $aid, $total, $cost_ilds) {
		//empty costs for each of the providers
		foreach (array('012', '013', '014', '015', '018', '019') as $key) {
			if (!isset($cost_ilds[$key])) {
				$cost_ilds[$key] = 0;
			}
		}

		ksort($cost_ilds);
		$seperator = ',';
		$row = $invoice_id . $seperator . $aid . $seperator .
				$total . $seperator . ($total * self::VAT_VALUE) . $seperator . implode($seperator, $cost_ilds) . PHP_EOL;
		$this->csv($row);
	}

	protected function createXml($fileName, $xmlContent) {
		$path = $this->export_directory . '/' . $fileName . '.xml';
		return file_put_contents($path, $xmlContent);
	}

	protected function saveInvoiceId($aid, $invoice_id) {
		$billrun = Billrun_Factory::db()->billrunCollection();

		$resource = $billrun->query()
				->equals('stamp', $this->getStamp())
				->equals('aid', (string) $aid)
//			->notExists('invoice_id')
		;

		foreach ($resource as $billrun_line) {
			$data = $billrun_line->getRawData();
			if (!isset($data['invoice_id'])) {
				$data['invoice_id'] = $invoice_id;
				$billrun_line->setRawData($data);
				$billrun_line->save($billrun);
			} else {
				$invoice_id = $data['invoice_id'];
			}
		}

		return $invoice_id;
	}

	protected function createInvoiceId() {
		$invoices = Billrun_Factory::db()->billrunCollection();
		// @TODO: need to the level of the invoice type
		$resource = $invoices->query()->cursor()->sort(array('invoice_id' => -1))->limit(1);
		foreach ($resource as $e) {
			// demi loop
		}
		if (isset($e['invoice_id'])) {
			return (string) ($e['invoice_id'] + 1); // convert to string cause mongo cannot store bigint
		}
		return '3100000000';
	}

}

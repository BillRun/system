<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
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

	const BYTES_IN_KB = 1024;

	protected $accountsCsvPath;
	protected $subscribersCsvPath;
	protected $plans;

	/**
	 *
	 * @var array cache of subscribers rows to write to the file
	 */
	protected $subscribersRows = array();

	/**
	 *
	 * @var array cache of accounts rows to write to the file
	 */
	protected $accountRows = array();

	/**
	 *
	 * @var array the accounts file header
	 */
	protected $accountsFields = array();

	/**
	 *
	 * @var array the subscribers file header
	 */
	protected $subscribersFields = array();

	/**
	 *
	 * @var int number of accounts to write to the csv file(s) at once
	 */
	protected $blockSize = 5000;

	public function __construct($options) {
		self::$type = 'golancsv';
		parent::__construct($options);

		if (isset($options['accounts_csv_filename'])) {
			$this->accountsCsvPath = $this->export_directory . '/' . $options['account_csv_filename'] . '.csv';
		} else {
			$csv_day = $this->getFileNameDate();
			$this->accountsCsvPath = $this->export_directory . '/accounts' . $this->stamp . '_' . $csv_day . '.csv';
		}
		if (isset($options['subscribers_csv_filename'])) {
			$this->subscribersCsvPath = $this->export_directory . '/' . $options['subscriber_csv_filename'] . '.csv';
		} else {
			$this->subscribersCsvPath = $this->export_directory . '/subscribers' . $this->stamp . '.csv';
		}
		if (isset($options['blockSize'])) {
			$this->blockSize = $options['blockSize'];
		}
		if (!file_exists(dirname($this->accountsCsvPath))) {
			mkdir(dirname($this->accountsCsvPath), 0777, true);
		} else if (file_exists($this->accountsCsvPath)) {
			unlink($this->accountsCsvPath);
		}
		if (!file_exists(dirname($this->subscribersCsvPath))) {
			mkdir(dirname($this->subscribersCsvPath), 0777, true);
		} else if (file_exists($this->subscribersCsvPath)) {
			unlink($this->subscribersCsvPath);
		}
		$this->accountsFields = array(
			'billrun_key',
			'AccountNumber',
			'XmlIndicator',
			'TotalChargeVat',
			'InvoiceNumber',
			'TotalFlat',
			'TotalExtraOverPackage',
			'TotalExtraOutOfPackage',
			'ManualCorrection',
			'ManualCorrectionCredit',
			'ManualCorrectionCharge',
			'OutsidePackageNoVatTap3',
			'TotalChargeRefundNoVat',
			'TotalVat',
			'TotalCharge',
			'CountActiveCli',
			'kosherCount',
			'TotalChargeVatRounded',
		);
		$this->subscribersFields = array(
			'billrun_key',
			'AccountNumber',
			'subscriber_id',
			'TotalChargeVat',
			'XmlIndicator',
			'TotalFlat',
			'TotalExtraOverPackage',
			'TotalExtraOutOfPackage',
			'ManualCorrection',
			'ManualCorrectionCredit',
			'ManualCorrectionCharge',
			'OutsidePackageNoVatTap3',
			'TotalChargeRefundNoVat',
			'TotalVat',
			'TotalCharge',
			'isAccountActive',
			'curPackage',
			'nextPackage',			
			'TotalChargeVatData',
			'CountOfKb',
			'isKosher',
		);

		$this->loadPlans();
	}

	/**
	 * write row to csv file for generating info into in
	 * 
	 * @param string $path the path to append into
	 * @param string $str the content to write
	 * 
	 * @return boolean true if succes to write info else false
	 */
	protected function writeToFile($path, $str) {
		return file_put_contents($path, $str, FILE_APPEND);
	}

	protected function addSubscriberRow($row) {
		$this->subscribersRows[] = $row;
	}

	protected function addAccountRow($row) {
		$this->accountRows[] = $row;
	}

	protected function writeHeaders() {
		$accounts_header = implode($this->accountsFields, ",") . PHP_EOL;
		$subscribers_header = implode($this->subscribersFields, ",") . PHP_EOL;

		$this->writeToFile($this->accountsCsvPath, $accounts_header);
		$this->writeToFile($this->subscribersCsvPath, $subscribers_header);
	}

	protected function writeRowsToCsv() {
		$seperator = ',';
		$accounts_str = '';
		$subscribers_str = '';
		foreach ($this->accountRows as $row) {
			foreach ($this->accountsFields as $field_name) {
				$accounts_str.=$row[$field_name] . $seperator;
			}
			$accounts_str = trim($accounts_str, ",") . PHP_EOL;
		}
		foreach ($this->subscribersRows as $row) {
			foreach ($this->subscribersFields as $field_name) {
				$subscribers_str.=$row[$field_name] . $seperator;
			}
			$subscribers_str = trim($subscribers_str, ",") . PHP_EOL;
		}
		$this->writeToFile($this->accountsCsvPath, $accounts_str);
		$this->writeToFile($this->subscribersCsvPath, $subscribers_str);
		$this->accountRows = array();
		$this->subscribersRows = array();
	}

	/**
	 * load the container the need to be generate
	 */
	public function load() {
		$billrun = Billrun_Factory::db(array('name' => 'billrun'))->billrunCollection();

		$this->data = $billrun
				->query('billrun_key', $this->stamp)
				->exists('invoice_id')
				->cursor();

		Billrun_Factory::log()->log("generator entities loaded: " . $this->data->count(), Zend_Log::INFO);

		Billrun_Factory::dispatcher()->trigger('afterGeneratorLoadData', array('generator' => $this));
	}

	/**
	 * execute the generate action
	 */
	public function generate() {
		// generate xml
		if ($this->data->count()) {
			$this->writeHeaders();
		}
		$this->create();
	}

	protected function create() {
		// use $this->export_directory
		$num_accounts = $this->data->count();
		$accounts_counter = 0;
		foreach ($this->data as $account) {
			Billrun_Factory::log()->log("Creating data for account " . $account['aid'], Zend_Log::INFO);
			$accounts_counter++;
			$vat = isset($account['vat']) ? $account['vat'] : floatval(Billrun_Factory::config()->getConfigValue('pricing.vat', 0.18));
			$acc_row = array();
			$acc_row['TotalChargeVat'] = $this->getAccountTotalChargeVat($account);
			$acc_row['TotalChargeVatRounded'] = round($this->getAccountTotalChargeVat($account), 2);
			$acc_row['billrun_key'] = $this->stamp;
			$acc_row['InvoiceNumber'] = $account['invoice_id'];
			$acc_row['TotalCharge'] = $acc_row['TotalVat'] = $acc_row['OutsidePackageNoVatTap3'] = $acc_row['ManualCorrection'] = $acc_row['ManualCorrectionCharge'] = $acc_row['ManualCorrectionCredit'] = $acc_row['TotalExtraOverPackage'] = $acc_row['TotalExtraOutOfPackage'] = $acc_row['TotalFlat'] = $acc_row['kosherCount'] = $acc_row['TotalChargeRefundNoVat'] = 0;
			$acc_row['CountActiveCli'] = 0;
			foreach ($account['subs'] as $subscriber) {
				$sub_row['billrun_key'] = $this->stamp;
				$acc_row['AccountNumber'] = $sub_row['AccountNumber'] = $account['aid'];
				$sub_row['subscriber_id'] = $subscriber['sid'];
				$sub_row['TotalChargeVat'] = $this->getSubscriberTotalChargeVat($subscriber);
				$sub_row['TotalChargeRefundNoVat'] = $this->getSubscriberTotalChargeRefundNoVat($subscriber);
				$acc_row['TotalChargeRefundNoVat'] += $sub_row['TotalChargeRefundNoVat'];
				$acc_row['XmlIndicator'] = $sub_row['XmlIndicator'] = $this->getXmlIndicator($account);
				$acc_row['TotalFlat'] += $sub_row['TotalFlat'] = $this->getTotalFlat($subscriber);
				$acc_row['TotalExtraOverPackage'] += $sub_row['TotalExtraOverPackage'] = $this->getTotalExtraOverPackage($subscriber);
				$acc_row['TotalExtraOutOfPackage'] += $sub_row['TotalExtraOutOfPackage'] = $this->getTotalExtraOutOfPackage($subscriber);
				$acc_row['ManualCorrectionCredit'] += $sub_row['ManualCorrectionCredit'] = $this->getManualCorrectionCredit($subscriber);
				$acc_row['ManualCorrectionCharge'] += $sub_row['ManualCorrectionCharge'] = $this->getManualCorrectionCharge($subscriber);
				$acc_row['ManualCorrection'] += $sub_row['ManualCorrection'] = $sub_row['ManualCorrectionCredit'] + $sub_row['ManualCorrectionCharge'];
				$acc_row['OutsidePackageNoVatTap3'] += $sub_row['OutsidePackageNoVatTap3'] = $this->getOutsidePackageNoVatTap3($subscriber);
				$acc_row['TotalVat'] += $sub_row['TotalVat'] = $this->getTotalVat($subscriber);
				$acc_row['TotalCharge'] += $sub_row['TotalCharge'] = $this->getTotalCharge($subscriber);
				$acc_row['CountActiveCli'] += $sub_row['isAccountActive'] = $this->getIsAccountActive($subscriber);
				$acc_row['kosherCount'] += $sub_row['isKosher'] = $this->is_kosher($subscriber);
				$sub_row['curPackage'] = $this->getCurPackage($subscriber);
				$sub_row['nextPackage'] = '';
				$sub_row['TotalChargeVatData'] = $this->getTotalChargeVatData($subscriber, $vat);
				$sub_row['CountOfKb'] = $this->getCountOfKb($subscriber);
				$this->addSubscriberRow($sub_row);
			}
//			Billrun_Factory::log()->log("invoice id created " . $invoice_id . " for the account", Zend_Log::INFO);

			$this->addAccountRow($acc_row);
			if ((($accounts_counter % $this->blockSize) == 0) || ($accounts_counter >= $num_accounts)) {
				$this->writeRowsToCsv();
			}
		}
	}

	protected function getXmlIndicator($account) {
		return isset($account['invoice_file']) ? "OK" : "SKIPPED";
	}

	protected function getTotalFlat($subscriber) {
		return $this->getVatableFlat($subscriber) + $this->getVatFreeFlat($subscriber);
	}

	protected function getTotalExtraOverPackage($subscriber) {
//		return $this->getVatableOverPlan($subscriber) + $this->getVatFreeOverPlan($subscriber);
		return $this->getVatableOverPlan($subscriber);
	}

	/**
	 * 
	 * @param type $subscriber
	 */
	protected function getTotalExtraOutOfPackage($subscriber) {
		return floatval(isset($subscriber['costs']['out_plan']['vatable']) ? $subscriber['costs']['out_plan']['vatable'] : 0);
	}

	protected function getManualCorrectionCredit($subscriber) {
		return floatval(isset($subscriber['costs']['credit']['refund']['vatable']) ? $subscriber['costs']['credit']['refund']['vatable'] : 0) +
			floatval(isset($subscriber['costs']['credit']['refund']['vat_free']) ? $subscriber['costs']['credit']['refund']['vat_free'] : 0);
	}

	protected function getManualCorrectionCharge($subscriber) {
		return floatval(isset($subscriber['costs']['credit']['charge']['vatable']) ? $subscriber['costs']['credit']['charge']['vatable'] : 0) +
			floatval(isset($subscriber['costs']['credit']['charge']['vat_free']) ? $subscriber['costs']['credit']['charge']['vat_free'] : 0) +
			floatval(isset($subscriber['costs']['service']['vatable']) ? $subscriber['costs']['service']['vatable'] : 0) +
			floatval(isset($subscriber['costs']['service']['vat_free']) ? $subscriber['costs']['service']['vat_free'] : 0);
	}

	protected function getOutsidePackageNoVatTap3($subscriber) {
		return floatval(isset($subscriber['costs']['out_plan']['vat_free']) ? $subscriber['costs']['out_plan']['vat_free'] : 0) +
			floatval(isset($subscriber['costs']['over_plan']['vat_free']) ? $subscriber['costs']['over_plan']['vat_free'] : 0);
	}

	protected function getTotalVat($subscriber) {
		return $this->getTotalAfterVat($subscriber) - $this->getTotalBeforeVat($subscriber);
	}

	protected function getTotalCharge($subscriber) {
		return $this->getTotalBeforeVat($subscriber);
	}

	protected function getIsAccountActive($subscriber) {
		$is_active = 0;
		if ((isset($subscriber['breakdown']) || (isset($subscriber['subscriber_status']) && $subscriber['subscriber_status'] == "open")) && $subscriber['sid'] != 0) {
			$is_active = 1;
		}
		return $is_active;
	}

	protected function is_kosher($subscriber) {
		return (isset($subscriber['kosher']) && $subscriber['kosher'] == "true") ? 1 : 0;
	}

	/**
	 * We cannot get the lines from balances as it's not necessarily the correct previous plan
	 * @param type $subscriber
	 * @return type
	 * @todo use plans cache
	 */
	protected function getCurPackage($subscriber) {
		$lastOffer = $this->getLastOffer($subscriber['plans']);
		$current_plan_ref = !empty($lastOffer) ? $lastOffer['current_plan'] : null;
		if (MongoDBRef::isRef($current_plan_ref)) {
			$current_plan = $this->getPlanById(strval($current_plan_ref['$id']));
			$current_plan_name = $current_plan['name'];
		} else {
			$current_plan_name = 'NO_GIFT';
		}
		return $current_plan_name;
	}

	protected function getTotalChargeVatData($subscriber, $vat) {
		$price_before_vat = (isset($subscriber['breakdown']['in_plan']['base']['INTERNET_BILL_BY_VOLUME']['totals']['data']['cost']) ? $subscriber['breakdown']['in_plan']['base']['INTERNET_BILL_BY_VOLUME']['totals']['data']['cost'] : 0) +
			(isset($subscriber['breakdown']['over_plan']['base']['INTERNET_BILL_BY_VOLUME']['totals']['data']['cost']) ? $subscriber['breakdown']['over_plan']['base']['INTERNET_BILL_BY_VOLUME']['totals']['data']['cost'] : 0) +
			(isset($subscriber['breakdown']['out_plan']['base']['INTERNET_BILL_BY_VOLUME']['totals']['data']['cost']) ? $subscriber['breakdown']['out_plan']['base']['INTERNET_BILL_BY_VOLUME']['totals']['data']['cost'] : 0);
		return $price_before_vat * (1 + $vat);
	}

	protected function getCountOfKb($subscriber) {
		$countOfKb = 0;
		if (isset($subscriber['lines']['data']['counters']) && is_array($subscriber['lines']['data']['counters'])) {
			foreach ($subscriber['lines']['data']['counters'] as $data_by_day) {
					$countOfKb+=$data_by_day['usagev'];
			}
		}
		return $countOfKb / static::BYTES_IN_KB;
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
	
	protected function getSubscriberTotalChargeRefundNoVat($subscriber) {
		$refund = isset($subscriber['costs']['credit']['refund']['vat_free']) ? $subscriber['costs']['credit']['refund']['vat_free'] : 0;
		$charge = isset($subscriber['costs']['credit']['charge']['vat_free']) ? $subscriber['costs']['credit']['charge']['vat_free'] : 0;
		$amountWithoutVat = isset($subscriber['costs']['flat']['vat_free']) ? $subscriber['costs']['flat']['vat_free'] : 0;
		
		return $refund + $charge + $amountWithoutVat;
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

	/**
	 * Get a rate by hexadecimal id
	 * @param string $id hexadecimal id of rate (taken from Mongo ID)
	 * @return Mongodloid_Entity the corresponding rate
	 */
	protected function getPlanById($id) {
		if (!isset($this->plans[$id])) {
			$plans_coll = Billrun_Factory::db()->plansCollection();
			$this->plans[$id] = $plans_coll->findOne($id);
		}
		return $this->plans[$id];
	}

	/**
	 * Load all rates from db into memory
	 */
	protected function loadPlans() {
		$plans_coll = Billrun_Factory::db()->plansCollection();
		$plans = $plans_coll->query()->cursor();
		foreach ($plans as $plan) {
			$plan->collection($plans_coll);
			$this->plans[strval($plan->getId())] = $plan;
		}
	}
	
	protected function getFileNameDate() {
		$billrun_day = Billrun_Factory::config()->getConfigValue('billrun.charging_day',25);
		return date('Ym').$billrun_day;
	}
	
	protected function getLastOffer($offers) {
		if (empty($offers)) {
			return array();
		}
		$lastOffer = array();
		foreach ($offers as $offer) {
			if (empty($lastOffer)) {
				$lastOffer = $offer;
			}
			if ($offer['end_date'] > $lastOffer['end_date']) {
				$lastOffer = $offer;
			}
		}
		return $lastOffer;
	}

}

//
//create table accounts(billrun_key varchar(10) NOT NULL, 
//AccountNumber INT NOT NULL, 
//XmlIndicator varchar(100), 
//TotalChargeVat DECIMAL(64,25), 
//InvoiceNumber BIGINT, 
//TotalFlat DECIMAL(64,25), 
//TotalExtraOverPackage DECIMAL(64,25), 
//TotalExtraOutOfPackage DECIMAL(64,25), 
//ManualCorrection DECIMAL(64,25), 
//ManualCorrectionCredit DECIMAL(64,25), 
//ManualCorrectionCharge DECIMAL(64,25), 
//OutsidePackageNoVatTap3 DECIMAL(64,25), 
//TotalVat DECIMAL(64,25), 
//TotalCharge DECIMAL(64,25), 
//CountActiveCli INT, 
//PRIMARY KEY(billrun_key, 
//AccountNumber));
//
//create table subscribers(billrun_key varchar(10) NOT NULL, 
//AccountNumber INT NOT NULL, 
//subscriber_id INT NOT NULL, 
//TotalChargeVat DECIMAL(64,25), 
//XmlIndicator varchar(1024), 
//TotalFlat DECIMAL(64,25), 
// TotalExtraOverPackage DECIMAL(64,25), 
//TotalExtraOutOfPackage DECIMAL(64,25), 
//ManualCorrection DECIMAL(64,25), 
//ManualCorrectionCredit DECIMAL(64,25), 
//ManualCorrectionCharge DECIMAL(64,25), 
//OutsidePackageNoVatTap3 DECIMAL(64,25), 
//TotalVat DECIMAL(64,25), 
//TotalCharge DECIMAL(64,25), 
//isAccountActive TINYINT, 
//curPackage varchar(100), 
//nextPackage varchar(100), 
//TotalChargeVatData DECIMAL(64,25), 
//CountOfKb DECIMAL(64,25), 
//PRIMARY KEY(billrun_key, 
//AccountNumber, 
//subscriber_id));
//
//mysqlimport --ignore-lines=1 --fields-optionally-enclosed-by='"' --fields-terminated-by=',' --lines-terminated-by='\n' --local test /home/shani/Desktop/accounts.csv
//mysqlimport --ignore-lines=1 --fields-optionally-enclosed-by='"' --fields-terminated-by=',' --lines-terminated-by='\n' --local test /home/shani/Desktop/subscribers.csv
<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2022 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Metabase Reports plugin
 *
 * @package  Application
 * @subpackage Plugins
 * @since    5.14
 */
class metabaseReportsPlugin extends Billrun_Plugin_BillrunPluginBase {
	
	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'metabaseReports';
	
	/**
	 * Holds the file export details
	 * @var array 
	 */
	protected $export_details;
	
	/**
	 * Holds the metabase details
	 * @var array 
	 */
	protected $metabase_details;
	
	/**
	 * Holds the reports details
	 * @var array 
	 */
	protected $reports_details;
	
	/**
	 * Holds the domain of the metabase's APIs
	 * @var string
	 */
	protected $domain;
	
	/**
	 * Holds the plugin's configuration values
	 * @var array
	 */
	protected $values;
	
	protected static $type = 'ssh';
	
	protected $port = '22';
	protected $run_after_invoice_created;
	protected $run_after_invoice_confirmed;
	protected $run_after_cycle_finished;
	protected $run_after_cycle_confirmed;
	
	public function __construct($options = array()) {		
		$this->reports_details = isset($options['reports']) ? $options['reports'] : [];
		$this->metabase_details = isset($options['metabase_details']) ? $options['metabase_details'] : [];
		$this->export_details = isset($options['export']) ? $options['export'] : [];
		$this->values = isset($options['added_data']) ? $options['added_data'] : [];
		$this->run_after_invoice_created = Billrun_Util::getIn($options, "run_after_invoice_created", false);
		$this->run_after_invoice_confirmed = Billrun_Util::getIn($options, "run_after_invoice_confirmed", false);
		$this->run_after_cycle_finished = Billrun_Util::getIn($options, "run_after_cycle_finished", false);
		$this->run_after_cycle_confirmed = Billrun_Util::getIn($options, "run_after_cycle_confirmed", false);
	}
	
	/**
	 * Check if Metabase reports actions are needed.
	 */
	public function validateReportsConfStructure() {
		$reports_exist = !empty($this->reports_details);
		$all_disable = true;
		if ($reports_exist) {
			foreach ($this->reports_details as $report) {
				if(!isset($report['enable']) || ($report['enable'] == true)) {
					$all_disable = false;
				}
			}
		}
		$metabase_details = !empty($this->metabase_details);
		$export_details = !empty($this->export_details);
		return $reports_exist && !$all_disable && $metabase_details && $export_details;
	}

	/**
	 * Function to create any MB report that should run after aggregating account data
	 * @param Billrun_Cycle_Account $accountBillrun the billrun data of the account
	 * @param array $aggregatedResults
	 * @param Billrun_Aggregator_Customer $aggragator
	 */
	public function afterAccountInvoiceSaved (Mongodloid_Entity $invoice, Billrun_Cycle_Account_Invoice $accountBillrun) {
		if (!$this->run_after_invoice_created) {
			return;
		}
		$aid = $accountBillrun->getAid();
		$billrun_key = $accountBillrun->getBillrunKey();
		$invoice_data = $invoice->getRawData();
		Billrun_Factory::log("Checking if any metabase report should run after account " . $aid . " invoice " . $invoice_data['invoice_id'] . " created", Zend_Log::DEBUG);
		$params = [
			'invoices' => [$invoice_data],
			'event' => 'customer_invoice_created'
		];
		$this->runReports($params);
	}

	/**
	 * Function to create any MB report that should run after confirming account invoice
	 * @param array $invoice_bill the invoice bill data
	 */
	public function afterInvoiceConfirmed($invoice_bill, $invoice_data) {
		if (!$this->run_after_invoice_confirmed) {
			return;
		}
		Billrun_Factory::log("Checking if any metabase report should run after account " . $invoice_bill['aid'] . " invoice " . $invoice_bill['invoice_id'] . " confirmed", Zend_Log::DEBUG);
		$params = [
			'invoices' => [$invoice_data],
			'event' => 'customer_invoice_confirmed'
		];
		$this->runReports($params);
	}
	
	/**
	 * Function to create any MB report that should run after billing cycle is done
	 * @param array $data cycle invoices
	 * @param Billrun_DataTypes_CycleTime $cycle
	 * @param Billrun_Aggregator_Customer $aggragator
	 */
	public function afterCycleDone($data, $cycle, $aggragator) {
		if (!$this->run_after_cycle_finished) {
			return;
		}
		Billrun_Factory::log("Checking if any metabase report should run after cycle " . $cycle->key() . " finished", Zend_Log::DEBUG);
		$params = [
			'invoices' => array_map(function($invoice) {
				return $invoice->getInvoice()->getRawData();
			}, $data),
			'billrun_key' => $cycle->key(),
			'event' => 'cycle_finished'
		];
		$this->runReports($params);
	}

	/**
	 * Function to create any MB report that should run after billing cycle confirmation
	 * @param array $invoices cycle confirmed invoices
	 * @param string $billrun_key cycle stamp
	 */
	public function afterInvoicesConfirmation($invoices, $billrun_key) {
		if (!$this->run_after_cycle_confirmed) {
			return;
		}
		if (!Billrun_Util::isBillrunKey($billrun_key) || Billrun_Billingcycle::getCycleStatus($billrun_key) !== 'confirmed') {
			return;
		}
		Billrun_Factory::log("Checking if any metabase report should run after cycle " . $billrun_key . " confirmed", Zend_Log::DEBUG);
		$params = [
			'invoices' => $invoices,
			'billrun_key' => $billrun_key,
			'event' => 'cycle_confirmed'
		];
		$this->runReports($params);
	}

	public function cronHour () {
		$this->runReports();
	}
	
	public function getConfigurationDefinitions() {
		return [[
			"type" => "json",
			"field_name" => "reports",
			"title" => "MB's reports configuration",
			"editable" => true,
			"display" => true,
			"nullable" => false,
			], [
				"type" => "text",
				"field_name" => "export.connection_type",
				"title" => "MB reports - remote connection type",
				"select_list" => true,
				"select_options" => "ssh",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
			], [
				"type" => "string",
				"field_name" => "export.host",
				"title" => "MB reports - export server's host",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
			], [
				"type" => "string",
				"field_name" => "export.user",
				"title" => "MB reports - export server's user name",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
			], [
				"type" => "password",
				"field_name" => "export.password",
				"title" => "MB reports - export server's password",
				"editable" => true,
				"display" => true,
				"nullable" => true,
				"mandatory" => false
			], [
				"type" => "text",
				"field_name" => "export.key_file_name",
				"title" => "MB reports - export server's key file name",
				"editable" => true,
				"display" => true,
				"nullable" => true,
				"mandatory" => false
			], [
				"type" => "string",
				"field_name" => "export.remote_directory",
				"title" => "MB reports - report files' remote directory",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
			], [
				"type" => "string",
				"field_name" => "export.export_directory",
				"title" => "MB reports - report files' export directory",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
			], [
				"type" => "string",
				"field_name" => "metabase_details.url",
				"title" => "Metabase's url",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true,
			], [
				'type' => 'boolean',
				'field_name' => 'run_after_invoice_created',
				'title' => 'Run after account invoice created',
				'mandatory' => false,
				'editable' => true,
				'display' => true,
				'nullable' => false,
				'default' => false
			], [
				'type' => 'boolean',
				'field_name' => 'run_after_invoice_confirmed',
				'title' => 'Run after account invoice confirmed',
				'mandatory' => false,
				'editable' => true,
				'display' => true,
				'nullable' => false,
				'default' => false
			], [
				'type' => 'boolean',
				'field_name' => 'run_after_cycle_finished',
				'title' => 'Run after billing cycle done',
				'mandatory' => false,
				'editable' => true,
				'display' => true,
				'nullable' => false,
				'default' => false
			], [
				'type' => 'boolean',
				'field_name' => 'run_after_cycle_confirmed',
				'title' => 'Run after billing cycle confirmed',
				'mandatory' => false,
				'editable' => true,
				'display' => true,
				'nullable' => false,
				'default' => false
			]
		];
	}
	
	/**
	 * @param array $params
	 * Function to fetch the reports that should run in the relevant timing.
	 */
	public function runReports ($params = []) {
		if (!$this->validateReportsConfStructure()) {
			Billrun_Factory::log("Metabase reports - missing reports/metbase details/export info/all the reports are disabled. No action was done.", Zend_Log::WARN);
			return;
		}
		$reports = $this->getReportsToRun($params);
		Billrun_Factory::log("Found " . count($reports) . " metabase reports to run."  , Zend_Log::INFO);
		foreach ($reports as $index => $report_settings) {
			if (@class_exists($report_class = 'Report_' . $report_settings['name'])) {
				$report = new $report_class($report_settings);
			} else {
				$report = new Report($report_settings);
			}
			$metabase_url = rtrim($this->metabase_details['url'], "/");
			try {
				$report_params = $report->getReportParams($params);
				$params_query = $this->createParamsQuery ($report_params);
				Billrun_Factory::log($report->name . " report's params json query: " . $params_query, Zend_Log::DEBUG);
				$this->fetchReport($report, $metabase_url, $params_query);
				Billrun_Factory::log($report->name . " report was downloaded successfully" , Zend_Log::INFO);
				if ($report->need_post_process) {
					Billrun_Factory::log("Starting " . $report->name . " report's post process" , Zend_Log::DEBUG);
					$report->reportPostProcess($this->values);
				}
			} catch (Throwable $e) {
				Billrun_Factory::log("Report: " . $report_settings['name'] . " download ERR: " . $e->getMessage(), Zend_Log::ALERT);
				continue;
			}
			try {
				Billrun_Factory::log("Saving " . $report->name . " report." , Zend_Log::DEBUG);
				$this->save($report, $params);
				Billrun_Factory::log("Uploading " . $report->name . " report." , Zend_Log::INFO);
				$this->upload($report, $params);
			} catch (Exception $e) {
				Billrun_Factory::log("Report: " . $report_settings['name'] . " saving ERR: " . $e->getMessage(), Zend_Log::ALERT);
				continue;
			}
		}
	}

	/**
	 * Function that returns the reports that should run in the current day and hour.
	 * @return array of the relevant reports settings.
	 */
	protected function getReportsToRun($params = []) {
		$reportsToRun = [];
		foreach ($this->reports_details as $reportSettings) {
			if ((isset($reportSettings['enable']) ? $reportSettings['enable'] : true) && $this->shouldReportRun($reportSettings, $params)) {
				Billrun_Factory::log("Metabase report: " . $reportSettings['name'] . " should run." , Zend_Log::INFO);
				Billrun_Factory::dispatcher()->trigger('beforeBuildReportSettings', array(&$reportSettings, $params, $this));
				$reportsToRun[] = $reportSettings;
			}
		}
		return $reportsToRun;
	}
	
	/**
	 * 
	 * @param array $reportSettings
	 * @param array $params
	 * @return true if the report should run now, else - returns false.
	 */
	protected function shouldReportRun($reportSettings, $params = []) {
		$currentDay = intval(date('d'));
		$currentHour = intval(date('H'));
		$isRightDay = $isRightHour = false;
		if (isset($reportSettings['timing']) && isset($params['event'])) {
			return $reportSettings['timing'] === $params['event'];
		} else {
			$isRightHour = isset($reportSettings['hour']) ? $reportSettings['hour'] == $currentHour : false;
		$isRightDay = true;
		if (!empty($reportSettings['day']) && (intval($reportSettings['day']) != $currentDay)) {
			$isRightDay = false;
			}
		}

		return $isRightDay && $isRightHour;
	}
	
	/**
	 * Function to download the wanted report from the metabase.
	 * @param Report object $report
	 * @param string $metabase_url
	 * @param string $report_params
	 * @throws Exception - if the report couldn't be downloaded
	 */
	protected function fetchReport($report, $metabase_url, $report_params) {
		$url = $metabase_url . '/api/public/card/' . $report->getId() . '/query/' . $report->format;
		Billrun_Factory::log('Metabase reports request: ' . $url, Zend_Log::DEBUG);
		$params = !empty($report_params) ? ['parameters' => $report_params] : [];
		$response = Billrun_Util::sendRequest($url, $params, Zend_Http_Client::GET, array('Accept-encoding' => 'deflate'), null, null, true);
		$response_body = $response->getBody();
		if (empty($response_body)) {
			throw new Exception("Couldn't download " . $report->name . " report. Metabase response is empty.");
		}
		if (!$response->isSuccessful()) {
            Billrun_Factory::log('Report response: ' . $response_body, Zend_Log::DEBUG);
 			throw new Exception("Couldn't download " . $report->name . " report. Error code: " . $response->getStatus());
		}
		$report->setData($response_body);
	}
	
	/**
	 * Function that converts report's params array to json string.
	 * @param array $report_params
	 * @return string
	 */
	protected function createParamsQuery ($report_params) {
		$query = []; 
		foreach ($report_params as $name => $data) {
			$parameters[] = [
				'type' => $data['type'],
				'target' => ["variable", ["template-tag", $data['template-tag']]],
				'value' => $data['value']
			];
		}
		$query = json_encode($parameters);
		return $query;
	}

	/**
	 * Function that saves the report's files locally
	 * @param Report $report
	 */
	public function save($report, $manager_params) {
		$file_path = $this->export_details['export_directory'] . DIRECTORY_SEPARATOR . $report->getFileName($manager_params);
		Billrun_Factory::log("Saving " . $report->name . " under: " . $file_path , Zend_Log::INFO);
		file_put_contents($file_path, $report->getData());
	}
	
	/**
	 * Function that saves the report's files remotely 
	 * @param Report $report
	 */
	public function upload($report, $manager_params) {
		$export_conf = !empty($report_export = $report->getExportDetails()) ? $report_export : $this->export_details;
		$hostAndPort = $export_conf['host'] . ':'. (!empty($export_conf['port']) ? $export_conf['port'] : $this->port);
		$fileName = $report->getFileName($manager_params);
		// Check if private key exist
		if (isset($export_conf['key_file_name'])) {		
			Billrun_Factory::log("Found key file name configuration.." , Zend_Log::DEBUG);
			$key_file_name = basename($export_conf['key_file_name']);
			Billrun_Factory::log("Validating key file name.." , Zend_Log::DEBUG);
			if (!preg_match("/^([-\.\_\w]+)$/", $key_file_name)) {
				throw new Exception("Key file name isn't valid : " . $key_file_name . ". Couldn't upload " . $fileName . " report' file");
			}
			Billrun_Factory::log("Key file name : " .  $key_file_name . " is valid. Checking if the key file exists" , Zend_Log::DEBUG);
			$key_file_path = Billrun_Util::getBillRunPath('application/plugins/metabaseReports/keys/' . $key_file_name);
			if (!file_exists($key_file_path) || !is_file($key_file_path)) {
				throw new Exception("Couldn't find " . $key_file_name . " key file under: " . $key_file_path . ". Couldn't upload " . $fileName . " report' file");
			}
			Billrun_Factory::log("Found " . $key_file_name . " key file under : " . $key_file_path , Zend_Log::DEBUG);
			$auth = array(
				'key' => $key_file_path,
			);
		} else {
			$auth = array(
				'password' => $export_conf['password'],
			);
		}
		$connection = new Billrun_Ssh_Seclibgateway($hostAndPort, $auth, array());
		Billrun_Factory::log()->log("Connecting to SFTP server: " . $connection->getHost() , Zend_Log::INFO);
		$connected = $connection->connect($export_conf['user']);
		 if (!$connected){
			 Billrun_Factory::log()->log("SSH: Can't connect to server", Zend_Log::ALERT);
			 return;
		 }
		Billrun_Factory::log()->log("Success: Connected to: " . $connection->getHost() , Zend_Log::INFO);
		Billrun_Factory::log("Uploading " . $fileName . " to " . $export_conf['remote_directory'], Zend_Log::INFO);
		if (!empty($connection)){
			try {
				$local = $this->export_details['export_directory'] . '/' . $fileName;
				$remote = $export_conf['remote_directory'] . '/' . $fileName;
				$response = $connection->put($local, $remote);
			} catch (Exception $e) {
				Billrun_Factory::log("Report file: " . $fileName . " uploading ERR: " . $e->getMessage(), Zend_Log::ALERT);
				return;
			}
			if($response) {
				Billrun_Factory::log("Uploaded " . $fileName . " file successfully", Zend_Log::INFO);
			} else {
				Billrun_Factory::log("Couldn't upload " . $fileName . " file to server." , Zend_Log::ALERT);
			}
		}
	}
}

class Report {
	
	/**
	 * Report name
	 * @var string 
	 */
	public $name;
	
	/**
	 * Report id in metabase
	 * @var string 
	 */
	protected $id;
	
	/**
	 * Day to run the report, null if the report runs daily.
	 * @var number 
	 */
	public $day = null;
	
	/**
	 * Hour to run the report.
	 * @var number 
	 */
	public $hour = null;
	
	/**
	 * Predefined timing to run the report
	 * @var string
	 */
	public $timing = null;

	/**
	 * export details
	 * @var array
	 */
	public $export_details;
	
	/**
	 * Csv file name.
	 * @var string
	 */
	public $csv_name;
	
	/**
	 * Csv file name params.
	 * @var array
	 */
	public $file_name_params;
	public $file_name_structure;
	
	/**
	 * Report params
	 * @var array
	 */
	public $params;
	
	/**
	 * Report actual data
	 * @var array
	 */
	protected $data;
	
	/**
	 * True if the report needs post process
	 * @var boolean
	 */
	public $need_post_process;
	
	/**
	 * Report format - csv/json
	 * @var string
	 */
	public $format;
	
	/**
	 * Is report enabled
	 * @var boolean 
	 */
	protected $enabled;
	public $processed_params = [];
	public $processed_file_name = null;

	public function __construct($options) {
		if (is_null($options['id'])) {
			throw new Exception("Report ID is missing");
		}
		$this->id = $options['id'];
		$this->name = $options['name'];
		$this->day = !empty($options['day']) ? $options['day'] : $this->day;
		$this->hour = isset($options['hour']) ? $options['hour'] : $this->hour;
		$this->timing = isset($options['timing']) ? $options['timing'] : $this->timing;
		$this->file_name_params = isset($options['filename_params']) ? $options['filename_params'] : [];
		$this->file_name_structure = isset($options['filename']) ? $options['filename'] : '';
		$this->csv_name = isset($options['csv_name']) ? $options['csv_name'] : "";
		$this->params = $options['params'];
		$this->need_post_process = !empty($options['need_post_process']) ? $options['need_post_process'] : false;
		$this->format = $this->need_post_process ? "json" : "csv";
		$this->enabled = !empty($options['enable']) ? $options['enable'] : true;
		$this->export_details = !empty($options['export']) ? $options['export'] : [];
		
	}

	public function reportPostProcess ($values = []) {
		$data = array_map('str_getcsv', explode("\n", $report->getData()));
		return;
	}

	/**
	 * Function that process the configured report params, and return it as array.
	 * @return array Reports API params
	 * @throws Exception - if one of the configured params is in wrong configuration.
	 */
	public function getReportParams ($manager_params = []) {
		$params = [];
		if (!empty($this->params)) {
			foreach ($this->params as $index => $param) {
				switch ($param['type']) :
					case "date" :
						$date = "";
						if (preg_match('/^\[\[/', $param['value'])) {
							$date = $this->getPlaceHolderValue($param, $manager_params);
						} elseif (isset($param['value']) && is_array($param['value'])) {
							$date = Billrun_Util::calcRelativeTime($param['value'],time());
						}
						if (empty($date)) {
							throw new Exception("Invalid params for 'date' type, in parameter" . $param['template_tag']); 
						}
						$dateFormat = isset($param['format']) ? $param['format'] : 'Y-m-d';
						$value = date($dateFormat, $date);
					break;
					case "string":
					case "number":
						$value = $this->getNumberOrStringTranslationValue($param, $manager_params);
					break;
					default : 
						throw new Exception("Invalid param type, in parameter " . $param['template_tag']);
				endswitch;
				$params[$param['template_tag']] = [
					'value' => $value,
					'template-tag' => $param['template_tag'],
					'type' => $param['type']
				];
				$this->processed_params[$param['template_tag']] = $value;
			}
		}
		return $params;
	}

	public function getPlaceHolderValue($param, $manager_params) {
		$place_holder_key = str_replace(["[[", "]]"], "", $param['value']);
		$invoice = null;
		if (!empty($manager_params['invoices'])) {
			$invoice = current($manager_params['invoices']);
		}
		switch ($place_holder_key) : 
			case 'customer_id' :
				if (is_null($invoice)) {
					throw new Exception("customer_id placeholder isn't avilable in " . $manager_params['event'] . " timing");
				}
				return $invoice['aid'];
			break;
			case 'cycle_start_time':
				return is_null($invoice) ? Billrun_Billingcycle::getStartTime($manager_params['billrun_key']) : $invoice['start_date']->sec;
			break;
			case 'cycle_end_time':
				return is_null($invoice) ? Billrun_Billingcycle::getEndTime($manager_params['billrun_key']) :  $invoice['end_date']->sec;
			break;
			default : 
				throw new Exception("Unsupported placeholder " . $place_holder_key . ", in parameter " . isset($param['template_tag'])) ? $param['template_tag'] : $param['param'];
			endswitch;
	}

	public function getRelevantBillrunKey($billrun_key) {
		switch ($billrun_key) {
			case 'current':
				return Billrun_Billrun::getActiveBillrun();
			case 'first_unconfirmed':
				if (($last = Billrun_Billingcycle::getLastConfirmedBillingCycle()) != Billrun_Billingcycle::getFirstTheoreticalBillingCycle()) {
						return Billrun_Billingcycle::getFollowingBillrunKey($last);
				}
				if (is_null($lastStarted = Billrun_Billingcycle::getFirstStartedBillingCycle())) {
						return $last;
				}
				return $lastStarted;
			case 'last_confirmed':
				return Billrun_Billingcycle::getLastConfirmedBillingCycle();
			case 'last_cycle':
				return Billrun_Billingcycle::getPreviousBillrunKey(Billrun_Billingcycle::getBillrunKeyByTimestamp(time()));
			default:
				return false;
		}
	}
	
	public function getData() {
		return $this->data;
	}
	
	public function setData($data) {
		$this->data = $data;
	}
	
	public function getId () {
		return $this->id;
	}
	
	public function getFileName($manager_params = []) {
		if (!is_null($this->processed_file_name)) {
			return $this->processed_file_name;
		}
		$this->processed_file_name = $this->getDefaultFileName($manager_params);
		if (!empty($this->file_name_params)) {
			$translations = array();
			foreach ($this->file_name_params as $paramObj) {
				/*if (isset($this->processed_params[$paramObj['']])) {

				}*/
				if (isset($paramObj['linked_entity'])) {
					//Only invoice is supported as linked entity && report must be in account level
					if (!isset($manager_params['invoices']) && !isset($this->processed_params['aid'])) {
						Billrun_Factory::log("Report " . $this->name . " file name configuration is invalid - linked entity is used while it's not avilable. Default file name was used" , Zend_Log::ALERT);
						return;						
					}
					$invoice_data = current($manager_params['invoices']);
					$paramObj['value'] = Billrun_Util::getIn($invoice_data, $paramObj['linked_entity']['field_name'], "");
					$res = $this->getTranslationValue($paramObj, $manager_params);
				} else {
					$res = $this->getTranslationValue($paramObj, $manager_params);
				}
				if($res === false){
					break;
				}
				$translations[$paramObj['param']] = $res;
			}
			if ($res !== false) {
				$this->processed_file_name = Billrun_Util::translateTemplateValue($this->file_name_structure, $translations, null, true);
			}
		}
		return $this->processed_file_name;
	}
	
	protected function getTranslationValue($paramObj, $manager_params = []) {
        if (!isset($paramObj['type']) || !isset($paramObj['value'])) {
			Billrun_Factory::log()->log("Missing filename params definitions for $this->name report. Default file name was taken", Zend_Log::ERR);
			return false;
        }
        switch ($paramObj['type']) {
            case 'date':
                $dateFormat = isset($paramObj['format']) ? $paramObj['format'] : Billrun_Base::base_datetimeformat;
				if (preg_match('/^\[\[/', $paramObj['value'])) {
					$dateValue = $this->getPlaceHolderValue($paramObj, $manager_params);
				} elseif (is_numeric($paramObj['value'])) {
					$dateValue = $paramObj['value'];
				} else {
                	$dateValue = ($paramObj['value'] == 'now') ? time() : strtotime($paramObj['value']);
				}
                return date($dateFormat, $dateValue);
			break;
            case 'autoinc':
                if (!isset($paramObj['min_value']) && !isset($paramObj['max_value'])) {
					Billrun_Factory::log()->log("Missing filename params definitions for $this->name report. Default file name was taken", Zend_Log::ERR);
					return false;
                }
                $minValue = $paramObj['min_value'];
                $maxValue = $paramObj['max_value'];
                $dateGroup = isset($paramObj['date_group']) ? $paramObj['date_group'] : Billrun_Base::base_datetimeformat;
                $dateValue = ($paramObj['value'] == 'now') ? time() : strtotime($paramObj['value']);
                $date = date($dateGroup, $dateValue);
                $action = 'metabase_reports_' . $this->name;
                $fakeCollectionName = $date . '_' . $action;
                $seq = Billrun_Factory::db()->countersCollection()->createAutoInc(array(), $minValue, $fakeCollectionName);
                if ($seq > $maxValue) {
					Billrun_Factory::log()->log("Sequence exceeded max value when generating file name for $this->name report. Default file name was taken", Zend_Log::ERR);
					return false;
                }
                if (isset($paramObj['padding'])) {
                    $this->padSequence($seq, $paramObj);
                }
                return $seq;
			break;
			case 'number':
			case 'string':
				return $this->getNumberOrStringTranslationValue($paramObj, $manager_params);
			break;
            default:
				Billrun_Factory::log()->log("Unsupported filename_params type for $this->name report. Default file name was taken", Zend_Log::ERR);
				return false;
        }
    }

	protected function getNumberOrStringTranslationValue($param, $manager_params = []) {
		$value = isset($param['value']) ? $param['value'] : "";
		if (preg_match('/^\[\[/', $param['value'])) {
			$value = $this->getPlaceHolderValue($param, $manager_params);
		} else {
			if ($param['type'] == 'string' && isset($param['format']) && $param['format'] == 'billrun_key') {
				$billrun_key = Billrun_Util::isBillrunKey($param['value']) ? $param['value'] : $this->getRelevantBillrunKey($param['value']);
				if ($billrun_key !== false) {
						Billrun_Factory::log("Creating params query/file name for billrun key: " . $billrun_key . ", for report: " . $this->name, Zend_Log::DEBUG);
				} else {
						throw new Exception("Unsupported billrun key input: " . $param['value'] . ", for report: " . $this->name);
				}
				$value = $billrun_key;
			}
		}
		return $value;
	}
	
	protected function padSequence($seq, $padding) {
        $padDir = isset($padding['direction']) ? $padding['direction'] : STR_PAD_LEFT;
        $padChar = isset($padding['character']) ? $padding['character'] : '';
        $length = isset($padding['length']) ? $padding['length'] : strlen($seq);
        return str_pad(substr($seq, 0, $length), $length, $padChar, $padDir);
    }

	public function getExportDetails() {
		return $this->export_details;
    }

	protected function getDefaultFileName($manager_params = []) {
		$name = !empty($this->csv_name) ? $this->csv_name : $this->name;
		$res = strtolower(str_replace(" ", "_", $name)) . '_' . date('Ymd', time()) . '.csv';
		//Only invoice is supported as linked entity && report must be in account level
		if (isset($manager_params['invoices']) && isset($this->processed_params['aid'])) {
			$invoice_data = current($manager_params['invoices']);
			$res = strtolower(str_replace(" ", "_", $name)) . '_' . $invoice_data['aid'] . '_' . date('Ym', $invoice_data['start_date']->sec) . '.csv';
		}
		return $res;
	}

}

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
	
	public function __construct($options = array()) {		
		$this->reports_details = isset($options['reports']) ? $options['reports'] : [];
		$this->metabase_details = isset($options['metbase_details']) ? $options['metbase_details'] : [];
		$this->export_details = isset($options['export']) ? $options['export'] : [];
		$this->values = isset($options['added_data']) ? $options['added_data'] : [];
		
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

	public function cronHour () {
		if (!$this->validateReportsConfStructure()) {
			Billrun_Factory::log("Metabase reports - missing reports/metbase details/export info/all the reports are disabled. No action was done.", Zend_Log::WARN);
			return;
		}
		$this->runReports();
	}
	
	/**
	 * Function to fetch the reports that should run in the current day and hour.
	 */
	public function runReports () {
		$reports = $this->getReportsToRun();
		Billrun_Factory::log("Found " . count($reports) . " reports to run."  , Zend_Log::INFO);
		foreach ($reports as $index => $report_settings) {
			if (@class_exists($report_class = 'Report_' . $report_settings['name'])) {
				$report = new $report_class($report_settings);
			} else {
				$report = new Report($report_settings);
			}
			$metabase_url = rtrim($this->metabase_details['url'], "/");
			try {
				$report_params = $report->getReportParams ();
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
				$this->save($report);
				Billrun_Factory::log("Uploading " . $report->name . " report." , Zend_Log::INFO);
				$this->upload($report);
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
	protected function getReportsToRun() {
		$reportsToRun = [];
		foreach ($this->reports_details as $reportSettings) {
			if ((isset($reportSettings['enable']) ? $reportSettings['enable'] : true) && $this->shouldReportRun($reportSettings)) {
				Billrun_Factory::log("Report: " . $reportSettings['name'] . " should run." , Zend_Log::INFO);
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
		$isRightHour = $reportSettings['hour'] == $currentHour;
		$isRightDay = true;
		if (!empty($reportSettings['day']) && (intval($reportSettings['day']) != $currentDay)) {
			$isRightDay = false;
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
	public function save($report) {
		$file_path = $this->export_details['export_directory'] . DIRECTORY_SEPARATOR . $report->getFileName();
		Billrun_Factory::log("Saving " . $report->name . " under: " . $file_path , Zend_Log::INFO);
		file_put_contents($file_path, $report->getData());
	}
	
	/**
	 * Function that saves the report's files remotely 
	 * @param Report $report
	 */
	public function upload($report) {
		$hostAndPort = $this->export_details['host'] . ':'. $this->port;
		$auth = array(
			'password' => $this->export_details['password'],
		);
		$connection = new Billrun_Ssh_Seclibgateway($hostAndPort, $auth, array());
		Billrun_Factory::log()->log("Connecting to SFTP server: " . $connection->getHost() , Zend_Log::INFO);
		$connected = $connection->connect($this->export_details['user']);
		 if (!$connected){
			 Billrun_Factory::log()->log("SSH: Can't connect to server", Zend_Log::ALERT);
			 return;
		 }
		Billrun_Factory::log()->log("Success: Connected to: " . $connection->getHost() , Zend_Log::INFO);
        $fileName = $report->getFileName();
		Billrun_Factory::log("Uploading " . $fileName . " to " . $this->export_details['remote_directory'], Zend_Log::INFO);
		if (!empty($connection)){
			try {
				$local = $this->export_details['export_directory'] . '/' . $fileName;
				$remote = $this->export_details['remote_directory'] . '/' . $fileName;
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
	public $hour;
	
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
	public function __construct($options) {
		if (is_null($options['id'])) {
			throw new Exception("Report ID is missing");
		}
		$this->id = $options['id'];
		$this->name = $options['name'];
		$this->day = !empty($options['day']) ? $options['day'] : $this->day;
		$this->hour = $options['hour'];
		$this->file_name_params = isset($options['filename_params']) ? $options['filename_params'] : [];
		$this->file_name_structure = isset($options['filename']) ? $options['filename'] : '';
		$this->csv_name = isset($options['csv_name']) ? $options['csv_name'] : "";
		$this->params = $options['params'];
		$this->need_post_process = !empty($options['need_post_process']) ? $options['need_post_process'] : false;
		$this->format = $this->need_post_process ? "json" : "csv";
		$this->enabled = !empty($options['enable']) ? $options['enable'] : true;
		
	}

	public function reportPostProcess ($values = []) {
		$data = array_map('str_getcsv', explode("\n", $report->getData()));
		return;
	}

	/**
	 * Function that process the configured report params, and return it as array.
	 * @return type
	 * @throws Exception - if one of the configured params is in wrong configuration.
	 */
	public function getReportParams () {
		$params = [];
		if (!empty($this->params)) {
			foreach ($this->params as $index => $param) {
				switch ($param['type']) :
					case "date" :
						$dateFormat = isset($param['format']) ? $param['format'] : 'Y-m-d';
						if (isset($param['value']) && is_array($param['value'])) {
							$date = Billrun_Util::calcRelativeTime($param['value'],time());
							$params[$param['template_tag']]['value'] = date($dateFormat, $date);
						} else { throw new Exception("Invalid params for 'date' type, in parameter" . $param['template_tag']); }
					break;
					case "string" || "number" : 
						$params[$param['template_tag']]['value'] = $param['value'];
					break;
					default : 
						throw new Exception("Invalid param type, in parameter" . $param['template_tag']);
				endswitch;
				$params[$param['template_tag']]['template-tag'] = $param['template_tag'];
				$params[$param['template_tag']]['type'] = $param['type'];
			}
		}
		return $params;
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
	
	public function getFileName() {
		$name = !empty($this->csv_name) ? $this->csv_name : $this->name;
		$default_file_name = strtolower(str_replace(" ", "_", $name)) . '_' . date('Ymd', time()) . '.csv';
		if (!empty($this->file_name_params)) {
			$translations = array();
			foreach ($this->file_name_params as $paramObj) {
				$res = $this->getTranslationValue($paramObj);
				if($res == false){
					break;
				}
				$translations[$paramObj['param']] = $res;
			}
			if ($res == false) {
				return $default_file_name;
			} else {
				return Billrun_Util::translateTemplateValue($this->file_name_structure, $translations, null, true);
			}
		} else {
			return $default_file_name;
		}
	}
	
	protected function getTranslationValue($paramObj) {
        if (!isset($paramObj['type']) || !isset($paramObj['value'])) {
			Billrun_Factory::log()->log("Missing filename params definitions for $this->name report. Default file name was taken", Zend_Log::ERR);
			return false;
        }
        switch ($paramObj['type']) {
            case 'date':
                $dateFormat = isset($paramObj['format']) ? $paramObj['format'] : Billrun_Base::base_datetimeformat;
                $dateValue = ($paramObj['value'] == 'now') ? time() : strtotime($paramObj['value']);
                return date($dateFormat, $dateValue);
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
            default:
				Billrun_Factory::log()->log("Unsupported filename_params type for $this->name report. Default file name was taken", Zend_Log::ERR);
				return false;
        }
    }
	
	protected function padSequence($seq, $padding) {
        $padDir = isset($padding['direction']) ? $padding['direction'] : STR_PAD_LEFT;
        $padChar = isset($padding['character']) ? $padding['character'] : '';
        $length = isset($padding['length']) ? $padding['length'] : strlen($seq);
        return str_pad(substr($seq, 0, $length), $length, $padChar, $padDir);
    }

}

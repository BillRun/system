<?php


/**
 * @package	Billing
 * @copyright	Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license	GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Plugin to handle Epic custom behaviour
 *
 * @package  Application
 * @subpackage Plugins
 * @since    5.10
 */
class epicPlugin extends Billrun_Plugin_BillrunPluginBase {

	protected $name = 'epic';
	protected $plans;
	protected $services;
	protected $config;
	protected $requiredFields;
	protected $importTime;
	protected $modelColl;
	protected $onlyUpdate;
	protected $planRetArray = array('imported_entities' => array(), 'errors' => array());
	protected $serviceRetArray = array('imported_entities' => array(), 'errors' => array());
        
        public function importRates($uploadedFiles) {
                $ratesImporter = new RatesImporterMTNPlugin();
                $isMissingFile = $ratesImporter->isFileMissing($uploadedFiles);
                if(!$isMissingFile){
                    $ratesImporter->buildRatesAndPlansArrays($uploadedFiles);
                    $ratesImporter->updateOrCreateRatesAndPlans();
                    $ratesImporter->postprocess_output();
                    return $ratesImporter->getOutput();
                }else{
                    return ['errors' => [$isMissingFile]];
                }
        }
    
	public function importPlans($uploadedFiles) {
		if (!$this->initPlansAndProcessFiles($uploadedFiles)) {
			return $this->planRetArray;
		}
		Billrun_Factory::log('Starting import plans', Zend_Log::DEBUG);
		foreach ($this->plans as $plan) {
			$succ = false;
			$adjustedPlan = $this->adjustPlanData($plan);
			if (!empty($adjustedPlan['error_message'])) {
				$this->planRetArray['imported_entities'][key($adjustedPlan['error_message'])] = current($adjustedPlan['error_message']);
				continue;
			}
			foreach ($this->services as $service) {
				if (isset($service['included_in_plans']) && in_array($adjustedPlan['name'], $service['included_in_plans'])) {
					$adjustedPlan['include']['services'][] = $service['name'];
				}
				if (isset($service['optional_in_plans']) && in_array($adjustedPlan['name'], $service['optional_in_plans'])) {
					$adjustedPlan['optional']['services'][] = $service['name'];
				}
			}
			$params = array(
				'collection' => 'plans',
				'request' => array(
					'action' => 'create',
					'update' => json_encode($adjustedPlan),
				)
			);
			try {
				$entityModel = Models_Entity::getInstance($params);
				$succ = $entityModel->create();
			} catch (Exception $ex) {
				if ($ex->getMessage() == 'Entity already exists') {
					$this->updatePlan($adjustedPlan);
				} else if (empty($succ)) {
					$this->planRetArray['imported_entities'][$adjustedPlan['name']] = $ex->getMessage();
				}
			}
			if (!empty($succ)) {
				$this->planRetArray['imported_entities'][$adjustedPlan['name']] = true;
			}
		}
		Billrun_Factory::log('Plans import has ended', Zend_Log::DEBUG);
		return $this->planRetArray;
	}

	public function importServices($uploadedFiles) {
		if (!$this->initServicesAndProcessFiles($uploadedFiles)) {
			return $this->serviceRetArray;
		}
		Billrun_Factory::log('Starting import services', Zend_Log::DEBUG);
		$succ = false;
		foreach ($this->services as $service) {
			$adjustedService = $this->adjustServiceData($service);
			if (!empty($adjustedService['error_message'])) {
				$this->serviceRetArray['imported_entities'][key($adjustedService['error_message'])] = current($adjustedService['error_message']);
				continue;
			}

			$params = array(
				'collection' => 'services',
				'request' => array(
					'action' => 'create',
					'update' => json_encode($adjustedService),
				)
			);
			try {
				$entityModel = Models_Entity::getInstance($params);
				$succ = $entityModel->create();
			} catch (Exception $ex) {
				if ($ex->getMessage() == 'Entity already exists') {
					$this->updateService($adjustedService);
				} else if (empty($succ)) {
					$this->serviceRetArray['imported_entities'][$adjustedService['name']] = $ex->getMessage();
				}
			}
			if (!empty($succ)) {
				$this->serviceRetArray['imported_entities'][$adjustedService['name']] = true;
			}
		}

		Billrun_Factory::log('Services import has ended', Zend_Log::DEBUG);
		return $this->serviceRetArray;
	}

	protected function initPlansAndProcessFiles($uploadedFiles) {
		Yaf_Loader::getInstance(APPLICATION_PATH . '/application/modules/Billapi')->registerLocalNamespace("Models");
		$fileNames = array();
		foreach ($uploadedFiles as $fileName => $filePath) {
			$fileNames[] = $fileName;
			$fileContents[$fileName] = file_get_contents($filePath);
		}
		$importer = new Importer();
		$missingFiles = $importer->getMissingPlanFiles($fileNames);
		if (!empty($missingFiles)) {
			$this->planRetArray['errors'][] = 'The next files are missing ' . implode(',', $missingFiles);
			return false;
		}
		try {
			$contents = array(
				'Offers.csv' => $fileContents['offers.csv'],
				'Options.csv' => $fileContents['options.csv'],
			);
			foreach ($contents as $filename => $content) {
				$request['prices'][$filename] = $importer->loadCsvWithHeader($content);
			}
			if (!empty($request)) {
				$ret = $importer->execute($request, 'plan');
			}
		} catch (Exception $ex) {
			$this->planRetArray['errors'][] = $ex->getMessage();
			return false;
		}
		$this->plans = !empty($ret['plans']) ? $ret['plans'] : array();
		$this->services = !empty($ret['services']) ? $ret['services'] : array();
		return true;
	}

	protected function initServicesAndProcessFiles($uploadedFiles) {
		Yaf_Loader::getInstance(APPLICATION_PATH . '/application/modules/Billapi')->registerLocalNamespace("Models");
		$fileNames = array();
		foreach ($uploadedFiles as $fileName => $filePath) {
			$fileNames[] = $fileName;
			$fileContents[$fileName] = file_get_contents($filePath);
		}
		$importer = new importer();
		$missingFiles = $importer->getMissingServiceFiles($fileNames);
		if (!empty($missingFiles)) {
			$this->serviceRetArray['errors'][] = 'The next files are missing ' . implode(',', $missingFiles);
			return false;
		}
		try {
			$contents = array(
				'Options.csv' => $fileContents['options.csv'],
				'Options_zoning_and_limits.csv' => $fileContents['options_zoning_and_limits.csv'],
				'Option_group.csv' => $fileContents['option_group.csv'],
				'Technical_options_POM.csv' => $fileContents['technical_options_pom.csv'],
				'Comp_Matrix_BillRun_Options.csv' => $fileContents['comp_matrix_billrun_options.csv'],
				'Comp_Matrix_Technical_Options.csv' => $fileContents['comp_matrix_technical_options.csv'],
			);
			foreach ($contents as $filename => $content) {
				$request['prices'][$filename] = $importer->loadCsvWithHeader($content);
			}
			if (!empty($request)) {
				$ret = $importer->execute($request, 'service');
			}
		} catch (Exception $ex) {
			$this->serviceRetArray['errors'][] = $ex->getMessage();
			return false;
		}
		$this->services = !empty($ret['services']) ? $ret['services'] : array();
		return true;
	}

	protected function updatePlan($updatedData) {
		$succ = false;
		$query = array(
			'name' => $updatedData['name'],
			'effective_date' => $updatedData['from']
		);
		$params = array(
			'collection' => 'plans',
			'request' => array(
				'action' => 'permanentchange',
				'update' => json_encode($updatedData),
				'query' => json_encode($query),
			)
		);
		try {
			$entityModel = Models_Entity::getInstance($params);
			$succ = $entityModel->permanentchange();
		} catch (Exception $ex) {
			$this->planRetArray['imported_entities'][$updatedData['name']] = $ex->getMessage();
		}
		if (!empty($succ)) {
			$this->planRetArray['imported_entities'][$updatedData['name']] = true;
		}
	}

	protected function updateService($updatedData) {
		$succ = false;
		$query = array(
			'name' => $updatedData['name'],
			'effective_date' => isset($updatedData['from']) ? $updatedData['from'] : date(Billrun_Base::base_datetimeformat, time())
		);
		$params = array(
			'collection' => 'services',
			'request' => array(
				'action' => 'permanentchange',
				'update' => json_encode($updatedData),
				'query' => json_encode($query),
			)
		);
		try {
			$entityModel = Models_Entity::getInstance($params);
			$succ = $entityModel->permanentchange();
		} catch (Exception $ex) {
			$this->serviceRetArray['imported_entities'][$updatedData['name']] = $ex->getMessage();
		}
		if (!empty($succ)) {
			$this->serviceRetArray['imported_entities'][$updatedData['name']] = true;
		}
	}

	protected function adjustPlanData($plan) {
		$adjustedPlan = $plan;
		$priceObj = !empty($plan['price']) ? array(array('price' => floatval($plan['price']), 'from' => 0, 'to' => 'UNLIMITED')) : null;
		$planName = $plan['name'];
		if (empty($priceObj)) {
			return array('error_message' => array($planName => "Plan $planName is missing a price"));
		}
		if (empty($adjustedPlan['from'])) {
			return array('error_message' => array($planName => "Plan $planName is missing from field"));
		}
		$adjustedPlan['price'] = $priceObj;
		$adjustedPlan['recurrence'] = isset($plan['recurrence']) ? $plan['recurrence'] : array('periodicity' => 'month');
		$adjustedPlan['prorated'] = isset($plan['prorated']) ? $plan['prorated'] : true;
		$adjustedPlan['connection_type'] = isset($plan['connection_type']) ? $plan['connection_type'] : 'postpaid';
		$adjustedPlan['upfront'] = isset($plan['upfront']) ? $plan['upfront'] : false;
		$adjustedPlan['description'] = isset($plan['description']) ? $plan['description'] : $plan['name'];

		return $adjustedPlan;
	}

	protected function adjustServiceData($service) {
		$adjustedService = $service;
		$servicePrice = !empty($service['price']) ? $service['price'] : 0;
		$priceObj = array(array('price' => floatval($servicePrice), 'from' => 0, 'to' => 'UNLIMITED'));
		$rates = array();
		if (!empty($service['include'])) {
			$usageType = key($service['include']);
			$value = $service['include'][$usageType];
			$includeObj = array(
				'groups' => array(
					$service['name'] => array(
						'account_shared' => false,
						'account_pool' => false,
						'rates' => $rates,
						'value' => $value,
						'usage_types' => array(
							$usageType => array('unit' => 'gb1024')
						)
					)
				)
			);
		} else {
			$includeObj = array();
		}
		if (empty($service['name'])) {
			return array('error_message' => array("missing_key" => "Service is missing a name"));
		}

		$serviceName = $service['name'];
		if (empty($priceObj)) {
			return array('error_message' => array($serviceName => "Service $serviceName is missing a price"));
		}
		$adjustedService['price'] = $priceObj;
		$adjustedService['include'] = $includeObj;
		$adjustedService['prorated'] = isset($service['prorated']) ? $service['prorated'] : true;
		$adjustedService['description'] = isset($service['description']) ? $service['description'] : $service['name'];

		return $adjustedService;
	}
	
	public function beforeGeneratingCustomPaymentGatewayFile($type, $fileType, $options, &$bills) {
		if ($type != 'transactions_request' || !isset($options['sequence_type']) || (!in_array($options['sequence_type'], ['FRST', 'RCUR']))) {
			return;
		}
		$relevantAids = array();
		foreach ($bills as $bill) {
			$billData = $bill->getRawData();
			$relevantAids[] = $billData['aid'];
		}
		$mandatesPerAid = $this->getMandatesIds($relevantAids);
		$relevantMandates = array_values($mandatesPerAid);
		$query = array(
			'aid' => array('$in' => $relevantAids),
			'pg_request.mandate_identification' => array('$in' => $relevantMandates),
			'type' => 'rec',
			'method' => 'automatic',
			'waiting_for_confirmation' => array('$ne' => true),
			'pending' => array('$ne' => true),
		);
		$nonRejectedOrCanceled = Billrun_Bill::getNotRejectedOrCancelledQuery();
		$query = array_merge($query, $nonRejectedOrCanceled);
		$mandatesWithSuccesfulPayment = Billrun_Bill::getDistinctBills($query, 'pg_request.mandate_identification');
		if ($options['sequence_type'] == 'FRST') {
			$filteredMandates = array_diff($relevantMandates, $mandatesWithSuccesfulPayment);
		} else {
			$filteredMandates = $mandatesWithSuccesfulPayment;
		}
		foreach ($bills as $key => $bill) {
			$billData = $bill->getRawData();
			if (!in_array($mandatesPerAid[$billData['aid']], $filteredMandates)) {
				unset($bills[$key]);
			}
		}
		$bills = array_values($bills);
	}
        
        /**
         * Function that inserts the number of the immediate invoices, to the wkpdf generator, before the monthly invoice generation.
         * @param Generator_WkPdf $generator
         * @param Mongodloid_Entity $account
         * @param type $lines
         * @throws Exception
         */
        public function beforeGeneratorEntity($generator, &$account, &$lines){
            if($generator instanceof Generator_WkPdf){
                if(!$generator->isOnetime()){
                    if($account instanceof Mongodloid_Entity){
                        $billrun = $account->getRawData();
                        $billrun_key = $billrun['billrun_key'];
                        $aid = $billrun['aid'];
                        $cycle_start_time = Billrun_Billingcycle::getStartTime($billrun_key);
                        $cycle_end_time = Billrun_Billingcycle::getEndTime($billrun_key);
                        $immediate_invoices_in_range = Billrun_Billingcycle::getImmediateInvoicesInRange($aid, $cycle_start_time, $cycle_end_time);
                        $generator->setInvoiceExtraParams('immediate_invoices_count', count($immediate_invoices_in_range));
                    }else {
                        throw new Exception('epic.php : beforeGeneratorEntity : The received account is not instance of Mongodloid_Entity');
                    }
                }
            }else {
                throw new Exception('epic.php : beforeGeneratorEntity : The received generator is not instance of Generator_WkPdf');
            }
        }
        
        /**
         * Function that concatenate the immediate invoices of the previous month, to the monthly invoice.
         * @param Generator_WkPdf $generator
         * @param Mongodloid_Entity $account
         * @param type $lines
         * @throws Exception
         */
        public function afterGeneratorEntity($generator, &$account, &$lines){
            set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/../..');
            require_once ('vendor/plugins/autoload.php');
            Billrun_Factory::log('epic.php : afterGeneratedEntity is running.', Zend_Log::DEBUG);
            if($generator instanceof Generator_WkPdf){
                if(!$generator->isOnetime()){
                    if($account instanceof Mongodloid_Entity){
                        $billrun = $account->getRawData();
                        $invoice_file_path = $billrun['invoice_file'];
                        $billrun_key = $billrun['billrun_key'];
                        $aid = $billrun['aid'];
                        Billrun_Factory::log('Concatenate immediate invoices to monthly invoice, for billrun : ' . $billrun_key . ', aid : ' . $aid , Zend_Log::INFO);
                        $cycle_start_time = Billrun_Billingcycle::getStartTime($billrun_key);
                        $cycle_end_time = Billrun_Billingcycle::getEndTime($billrun_key);
                        $immediate_invoices_in_range = Billrun_Billingcycle::getImmediateInvoicesInRange($aid, $cycle_start_time, $cycle_end_time);
                        if(count($immediate_invoices_in_range) == 0){
                            Billrun_Factory::log('No invoices to concatenate.' , Zend_Log::INFO);
                            return;
                        }
                        if($immediate_invoices_in_range){
                            Billrun_Factory::log(count($immediate_invoices_in_range) . ' invoices to concatenate.' , Zend_Log::INFO);
                            $pdf = new \Jurosh\PDFMerge\PDFMerger;
                            $pdfFiles = $pdf->addPDF($invoice_file_path, 'all', 'vertical');
                            foreach($immediate_invoices_in_range as $object){
								if(!empty($object['invoice_file'])){
                                $immediate_invoice_path = $object['invoice_file'];
                                $pdfFiles = $pdfFiles->addPDF($immediate_invoice_path, 'all', 'vertical');
								}else{
									Billrun_Factory::log('Didn\'t find immediate invoice id:' . $object['invoice_id'] . ', for account id: ' . $aid . "." , Zend_Log::ALERT);
								}
                            }
                            $pdf->merge('file', $invoice_file_path);
                            Billrun_Factory::log('Done concatenating immediate invoices to monthly invoice, for account id: ' . $aid . "." , Zend_Log::INFO);
                        }else{
                            throw new Exception('epic.php : afterGeneratedEntity : something went wrong, no merge was made for account id: ' . $aid);
                        }
                    }else{
                            throw new Exception('epic.php : afterGeneratedEntity : The received account is not instance of Mongodloid_Entity');
                    }
                }
            }else{
                throw new Exception('epic.php : afterGeneratedEntity : The received generator is not instance of Generator_WkPdf');
            }
        }
        
        /**
         * Function confirm all the account's immediate invoices, that were created on this billrun.
         * @param int $aid
         * @param string $billrun_key
         * @param string $invoiceType
         */
        public function beforeInvoiceConfirmed ($aid, $billrun_key, $invoiceType){
            if($invoiceType === "regular"){
                $cycle_start_time = Billrun_Billingcycle::getStartTime($billrun_key);
                $cycle_end_time = Billrun_Billingcycle::getEndTime($billrun_key);
                $account_immediate_invoices = Billrun_Billingcycle::getImmediateInvoicesInRange($aid, $cycle_start_time, $cycle_end_time);
                if(count($account_immediate_invoices) > 0){
					$options['stamp'] = $billrun_key;
					$billrunToBill = new Generator_BillrunToBill($options);
                    foreach ($account_immediate_invoices as $immediate_invoice){
                        $billrunToBill->createBillFromInvoice($immediate_invoice, array($billrunToBill,'updateBillrunONBilled'));
                    }
                }
            }
        }
		
	protected function getMandatesIds($aids) {
		$mandatePerAid = array();
		$newAccount = Billrun_Factory::account();
		$accountQuery = array('aid' => array('$in' => $aids));
		$accounts = $newAccount->loadAccountsForQuery($accountQuery);
		foreach ($accounts as $account) {
			$accountData = $account->getRawData();
			$mandatePerAid[$accountData['aid']] = $accountData['mandate_id'];
		}
		
		return $mandatePerAid;
	}
	
	protected function afterAddInvoiceConfigurableData($object, $data){
		$dataArray = $data->getRawData();
		$dataArray['total_validated'] = $dataArray['totals']['after_vat_rounded'] + $dataArray['totals']['past_balance']['after_vat'] + (!empty($dataArray['added_data']['notes']) ? ($dataArray['added_data']['notes'][0]['credit'] + $dataArray['added_data']['notes'][0]['debit']) : 0);
		$pastBalance = Billrun_Bill::getTotalDueForAccount($dataArray['aid'], "99990101000000");
		$dataArray['total_validated_cease'] = $dataArray['totals']['after_vat_rounded'] + $pastBalance['total'] - $pastBalance['total_pending_amount'] + (!empty($dataArray['added_data']['notes']) ? ($dataArray['added_data']['notes'][0]['credit'] + $dataArray['added_data']['notes'][0]['debit']) : 0);
		$data->setRawData($dataArray);
		return;
	}

	public function beforeSplitDebt($params, &$executeSplitBill) {
		$previousBillrunKey = Billrun_Billingcycle::getPreviousBillrunKey(Billrun_Billingcycle::getBillrunKeyByTimestamp());
		if (!Billrun_Bill_Invoice::isInvoiceConfirmed($params['aid'], $previousBillrunKey)) {
			$executeSplitBill = false;
		}
	}
	public function beforeImmediateInvoiceCreation($aid, $inputCdrs, $paymentData, $allowBill, $step, $oneTimeStamp, $sendEmail) {
		if ($step > 0) {
			throw new Exception('Unavilable step choice for Epic\'s immediate invoice API request.');
		}
	}
}

class importer {

	protected $normalizeConf = array('plans', 'opts', 'opts_excludes', 'opts_tech_ex', 'opts_include', 'tech_vals', 'opt_grouping');
	protected $requiredPlanFiles = array('offers.csv', 'options.csv');
	protected $requiredServiceFiles = array('options.csv', 'options_zoning_and_limits.csv', 'option_group.csv', 'technical_options_pom.csv', 'comp_matrix_billrun_options.csv', 'comp_matrix_technical_options.csv');

	public function __construct() {
		$this->loadConfig(APPLICATION_PATH . '/application/plugins/conf/epic.ini');
		$this->requiredFields = array('not_billable_options', 'from', 'to', 'name');
	}

	protected function loadConfig($path) {
		$structConfig = (new Yaf_Config_Ini($path))->toArray();
		$this->config = $structConfig['importoptions'];
	}

	public function getMissingPlanFiles($fileNames) {
		return array_diff($this->requiredPlanFiles, $fileNames);
	}

	public function getMissingServiceFiles($fileNames) {
		return array_diff($this->requiredServiceFiles, $fileNames);
	}

	public function execute($params = false, $entity) {
		gc_enable();
		$this->importTime = time();
		$this->onlyUpdate = empty($params['remove_values']);
		$this->config = array_merge($this->config, $params);
		$this->modelColl = Billrun_Factory::db()->plansCollection();

		if ($entity == 'plan') {
			$importedRates = $this->normalizeRates($this->fetchPlanData($params));
		}
		if ($entity == 'service') {
			$importedRates = $this->normalizeRates($this->fetchServiceData($params));
		}

		return $importedRates;
	}

	/**
	 * Load the files  related to the rating that should be imported
	 * @param type $params parametered passed in the command line.
	 * @return type array containing  the  loaded rates.
	 */
	protected function fetchPlanData($params) {
		Billrun_Factory::log("Loading plans", Zend_Log::INFO);
		$plans = array();
		foreach (explode(",", $this->config['input_files']['plan_files']) as $pln_file) {
			Billrun_Factory::log("Loading file : $pln_file");
			$plans = array_merge($plans, $params['prices'][$pln_file]);
		}
		Billrun_Factory::log("Loading options", Zend_Log::INFO);
		$options = array();
		foreach (explode(",", $this->config['input_files']['option_files']) as $opt_file) {
			Billrun_Factory::log("Loading file : $opt_file");
			foreach ($params['prices'][$opt_file] as $opt) {
				$k = empty($opt[$this->config['opt_name_field']]) ? $opt[$this->config['opt_tech_name_field']] : $opt[$this->config['opt_name_field']];
				$options[$k] = $opt;
			}
		}

		gc_collect_cycles();
		return array('plans' => &$plans, 'opts' => &$options);
	}

	protected function fetchServiceData($params) {
		Billrun_Factory::log("Loading options", Zend_Log::INFO);
		$options = array();
		foreach (explode(",", $this->config['input_files']['option_files']) as $opt_file) {
			Billrun_Factory::log("Loading file : $opt_file");
			foreach ($params['prices'][$opt_file] as $opt) {
				$k = empty($opt[$this->config['opt_name_field']]) ? $opt[$this->config['opt_tech_name_field']] : $opt[$this->config['opt_name_field']];
				$options[$k] = $opt;
			}
		}
		$opt_exlusion = array();
		foreach (explode(",", $this->config['input_files']['opt_exclude_files']) as $optex_file) {
			Billrun_Factory::log("Loading file : $optex_file");
			foreach ($params['prices'][$optex_file] as $zone) {
				if (!is_array($zone)) {
					continue;
				}
				$opt_exlusion[$zone[$this->config['opt_ex_name_field']]] = $zone;
			}
		}

		$opt_tech_exlusion = array();
		foreach (explode(",", $this->config['input_files']['opt_tech_exclude_files']) as $opt_tech_ex_file) {
			Billrun_Factory::log("Loading file : $opt_tech_ex_file");
			foreach ($params['prices'][$opt_tech_ex_file] as $zone) {
				if (!is_array($zone)) {
					continue;
				}
				$opt_tech_exlusion[$zone[$this->config['opt_tech_ex_name_field']]] = $zone;
			}
		}

		$opt_include = array();
		foreach (explode(",", $this->config['input_files']['opt_include_files']) as $optinc_file) {
			Billrun_Factory::log("Loading file : $optinc_file");
			foreach ($params['prices'][$optinc_file] as $zone) {
				if (!is_array($zone)) {
					continue;
				}
				$opt_include[] = $zone;
			}
		}

		$opt_tech_values = array();
		if ($this->config['input_files']['opt_tech_pom_files']) {
			foreach (explode(",", $this->config['input_files']['opt_tech_pom_files']) as $opttech_val_file) {
				Billrun_Factory::log("Loading file : $opttech_val_file");
				foreach ($params['prices'][$opttech_val_file] as $zone) {
					if (!is_array($zone)) {
						continue;
					}
					if (!empty($zone[$this->config['opt_tech_val_name_field']])) {
						$opt_tech_values[$zone[$this->config['opt_tech_val_name_field']]] = $zone;
					}
				}
			}
		}
		$opt_grouping = array();
		foreach (explode(",", $this->config['input_files']['opt_group_files']) as $opt_group_file) {
			Billrun_Factory::log("Loading file : $opt_group_file");
			foreach ($params['prices'][$opt_group_file] as $zone) {
				if (!is_array($zone)) {
					continue;
				}
				$opt_grouping[$zone[$this->config['grouping_id_field']]] = $zone;
			}
		}

		gc_collect_cycles();
		return array('opts' => &$options, 'opts_excludes' => &$opt_exlusion, 'opts_tech_ex' => &$opt_tech_exlusion,
			'opts_include' => &$opt_include, 'tech_vals' => &$opt_tech_values, 'opt_grouping' => &$opt_grouping);
	}

	/**
	 * normalize the rates to fit  the  billrun rates  structure.
	 * @param type $rates the imported rates
	 */
	protected function normalizeRates($plansStructure) {
		gc_collect_cycles();
		Billrun_Factory::log("Normalizing the rates", Zend_Log::INFO);
		$normalizedPlans = array();
		$services = array();
		$count = 0;
		foreach ($this->normalizeConf as $value) {
			if (!isset($plansStructure[$value])) {
				$plansStructure[$value] = array();
			}
		}

		foreach ($plansStructure['plans'] as $pKey => &$plan) {
			$planName = $plan[$this->config['plan_name_field']];
			$planKey = $this->removeSpaces($plan[$this->config['plan_key_field']]);
			$normalizedPlans[$planKey]['name'] = $planKey;
			$normalizedPlans[$planKey]['technical_name'] = $planName;
			$normalizedPlans[$planKey]['key'] = $plan[$this->config['plan_key_field']];
			$normalizedPlans[$planKey]['provisioning'] = explode("/", $plan[$this->config['plan_provisioning_field']]);
			$normalizedPlans[$planKey]['from'] = max($plan['from'], Billrun_Util::getFieldVal($normalizedPlans[$planKey]['from'], ''));
			$normalizedPlans[$planKey]['to'] = max($plan['to'], Billrun_Util::getFieldVal($normalizedPlans[$planKey]['to'], ''));


			$normalizedPlans[$planKey]['price'] = $plan[$this->config['plan_price_field']];
			if ($plan[$this->config['plan_commitment_field']]) {
				$normalizedPlans[$planKey]['commitment'] = array(
					'price' => $plan[$this->config['plan_commitment_price_field']],
					'duration' => $plan[$this->config['plan_commitment_field']],
					'interval' => 'months'
				);
			}

			foreach (Billrun_Util::getFieldVal($this->config['plan_copy_fields'], array()) as $newPlanKey => $importKey) {
				if (!empty($plan[$importKey])) {
					$normalizedPlans[$planKey][$newPlanKey] = $plan[$importKey];
				}
			}

			$normalizedPlans[$planKey]['forceCommitment'] = $plan[$this->config['plan_force_commitment_field']] == 1;
			$normalizedPlans[$planKey]['invoice_label'] = $plan[$this->config['plan_invoice_label_field']];
			$normalizedPlans[$planKey]['invoice_type'] = strtolower($plan[$this->config['plan_invoice_type_field']]);
			//$normalizedPlans[$planKey]['priority'] = 1;
			//unset($plansStructure['plans'][$pKey]); //here to save memory.

			$count++;
		}

		foreach ($plansStructure['opts'] as $key => &$opt) {
//			if (empty($opt[$planName]) || $opt[$planName] == $this->config['plan_exclude_option']) {
//				continue;
//			}

			$optName = empty($opt[$this->config['opt_name_field']]) ? $opt[$this->config['opt_tech_name_field']] : $opt[$this->config['opt_name_field']];
			$newOpt = array('name' => $optName);
			if (!empty($opt[$this->config['opt_tech_name_field']])) {
				$newOpt['tech_name'] = $opt[$this->config['opt_tech_name_field']];
			}

			$newOpt['parameters'] = !empty($opt[$this->config['opt_parameters_field']]) ? $this->getComplexValueFromField($opt[$this->config['opt_parameters_field']]) : array();

			if (!empty($opt[$this->config['opt_vti_name_field']])) {
				$newOpt['vti_name'] = $opt[$this->config['opt_vti_name_field']];
			}
			foreach ($plansStructure['opts_excludes'] as $opt_exclude) {
				if (isset($opt_exclude[$this->config['opt_ex_name_field']]) && $opt_exclude[$this->config['opt_ex_name_field']] == $optName) {
					foreach ($opt_exclude as $oekey => $value) {
						$value = strtoupper($value);
						if ($oekey == $optName) {
							continue;
						}
//
						if ($value == "D") {
							$newOpt['depends'][] = strtoupper($this->removeSpaces($oekey));
						} else if ($value == "X") {
							$newOpt['excludes'][] = strtoupper($this->removeSpaces($oekey));
						}
					}
				}
			}

			if (isset($newOpt['tech_name'])) {
				foreach ($plansStructure['opts_tech_ex'] as $opt_exclude) {
					if (isset($opt_exclude[$this->config['opt_tech_ex_name_field']]) && $opt_exclude[$this->config['opt_tech_ex_name_field']] == $newOpt['tech_name']) {
						foreach ($opt_exclude as $oekey => $value) {
							$value = strtoupper($value);
							if ($oekey == $optName) {
								continue;
							}
							if ($value == "D") {
								$newOpt['depends'][] = strtoupper($this->removeSpaces($oekey));
							} else if ($value == "X") {
								$newOpt['excludes'][] = strtoupper($this->removeSpaces($oekey));
							}
						}
					}
				}
			}

			if ($plansStructure['opts_include']) {
				foreach ($plansStructure['opts_include'] as $opt_include) {
					if ($opt_include[$this->config['opt_inc_name_field']] == $optName) {
						$this->removePreviousGroupToRates($opt_include[$this->config['opt_inc_type_field']], strtoupper($this->removeSpaces($opt_include[$this->config['opt_inc_name_field']])));
					}
				}
				foreach ($plansStructure['opts_include'] as $opt_include) {
					if ($opt_include[$this->config['opt_inc_name_field']] == $optName) {
						if (!empty($opt_include[$this->config['opt_inc_amount_field']])) {
							$newOpt['include'][$opt_include[$this->config['opt_inc_type_field']]] = $opt_include[$this->config['opt_inc_amount_field']];
						}
						if (!empty($opt_include[$this->config['opt_inc_amount_max_field']])) {
							$newOpt['max_usage'][$opt_include[$this->config['opt_inc_type_field']]] = $opt_include[$this->config['opt_inc_amount_max_field']];
						}

						$query = $this->getAddGroupToRatesQuery($opt_include);

						$this->addGroupToRates($query, $opt_include[$this->config['opt_inc_type_field']], strtoupper($this->removeSpaces($opt_include[$this->config['opt_inc_name_field']])));
						//}
					}
				}
			}
			$newOpt['price'] = $opt[$this->config['opt_price_field']];
			$newOpt['invoice_type'] = strtolower($opt[$this->config['opt_invoice_type_field']]);
			if (!empty($opt[$this->config['opt_group_field']]) && !empty($plansStructure['opt_grouping'])) {
				$newOpt['grouping'] = $plansStructure['opt_grouping'][$opt[$this->config['opt_group_field']]][$this->config['grouping_name_field']];
			}
			if (!empty($opt[$this->config['opt_group_field']]) && !empty($plansStructure['opt_grouping'])) {
				$newOpt['grouping_order'] = $plansStructure['opt_grouping'][$opt[$this->config['opt_group_field']]][$this->config['grouping_order_field']];
			}

			$optToPlanRel = isset($opt[$planName]) ? explode("/", $opt[$planName]) : array();
			$newOpt['included'] = (in_array($this->config['plan_include_option'], $optToPlanRel) ? 1 : 0);
			if (in_array($this->config['opt_with_commit'], $optToPlanRel) || in_array($this->config['opt_without_commit'], $optToPlanRel)) {
				$newOpt['require_commitment'] = (in_array($this->config['opt_with_commit'], $optToPlanRel) ? 1 : 0);
			}
			if (in_array($this->config['plan_default_option'], $optToPlanRel)) {
				$newOpt['default'] = 1;
			}

			if (!empty($opt[$this->config['opt_display_in_field']])) {
				$newOpt['display_in'] = array('all' => explode('/', $opt[$this->config['opt_display_in_field']]));
			}

			foreach (Billrun_Util::getFieldVal($this->config['opt_copy_fields'], array()) as $newOptKey => $importKey) {
				if (isset($opt[$importKey])) {
					$newOpt[$newOptKey] = is_numeric($opt[$importKey]) ? floatval($opt[$importKey]) : $this->getComplexValueFromField($opt[$importKey], true, true);
				}
			}

			if (!empty($plansStructure['tech_vals'][$optName]) || !empty($plansStructure['tech_vals'][Billrun_Util::getFieldVal($newOpt['tech_name'], FALSE)])) {
				$techRefName = !empty($plansStructure['tech_vals'][$optName]) ? $optName : $newOpt['tech_name'];
				foreach ($plansStructure['tech_vals'][$techRefName] as $newKey => $techVal) {
					if ($techVal == $techRefName || $newKey == 'Option ID' || empty($newKey)) {
						continue;
					}
					$newOpt['provisioning'][strtoupper($this->removeSpaces($newKey))] = strpos($techVal, '/') !== FALSE ? explode('/', $techVal) : $techVal;
				}
			}


			$newOpt['type'] = $opt[$this->config['opt_type_field']];
			foreach ($opt as $fieldName => $value) {
				$plansList = $this->config['plans'];
				if (in_array($fieldName, $plansList)) {
					if ($value == 'I') {
						$newOpt['included_in_plans'][] = $fieldName;
					} else if ($value == 'O') {
						$newOpt['optional_in_plans'][] = $fieldName;
					}
				}
			}
			$services[$key] = $newOpt;
			if ($opt[$this->config['opt_type_field']] == 'technical') {
				$services[$key]['billable'] = false;
			} else {
				$services[$key]['billable'] = true;
			}
		}
		unset($plansStructure);
		gc_collect_cycles();

		return array('plans' => $normalizedPlans, 'services' => $services);
	}

	/**
	 * remove  spaces from a string
	 * @param type $$data the string to remove  and clear the spaces from
	 * @return type
	 */
	protected function removeSpaces($data) {
		if (is_array($data)) {
			foreach ($data as &$str) {
				$str = preg_replace("/[^\w_]/", "", preg_replace("/\s+/", "_", $str));
			}
			return $data;
		}
		return preg_replace("/[^\w_]/", "", preg_replace("/\s+/", "_", $data));
	}

	protected function getComplexValueFromField($fieldValue, $extractSingleValue = FALSE, $ignoreSlash = FALSE) {
		$ret = FALSE;
		if (!empty(json_decode(preg_replace('/[“”]+/', '"', $fieldValue), true))) {
			$ret = json_decode(preg_replace('/[“”]+/', '"', $fieldValue), true);
		} else if (!$ignoreSlash && !empty(explode("/", $fieldValue))) {
			$ret = explode("/", $fieldValue);
		} else if (!empty(explode("|", $fieldValue))) {
			$ret = explode("|", $fieldValue);
		}

		if (empty($ret)) {
			Billrun_Factory::log("Error trying to decode complex value form field", Zend_Log::ALERT);
		}
		if ($extractSingleValue && count($ret) == 1 && key($ret) === 0) {
			$ret = reset($ret);
		}

		return $ret;
	}

	protected function getAddGroupToRatesQuery($opt_include) {
		/* if(!empty($this->config['rate_key'])) {
		  $query['params.packs'] = array('$in' => $this->removeSpaces(explode("|", $opt_include[$this->config['zone_pack']])));
		  } else { */
		if (isset($opt_include[$this->config['opt_inc_rate_keys_field']])) {
			if (empty($opt_include[$this->config['opt_inc_rate_keys_field']])) {
				return $query['key']['$in'] = array(111);
			}
			$query['key']['$in'] = $this->removeSpaces(explode("/", $opt_include[$this->config['opt_inc_rate_keys_field']]));
		} else {
			$query['country'] = array('$in' => $this->removeSpaces(explode("|", $opt_include[$this->config['opt_inc_from_field']])));
			if (!empty($opt_include[$this->config['opt_inc_to_field']])) {
				$toArr = $this->removeSpaces(explode("|", $opt_include[$this->config['opt_inc_to_field']]));
				$query['params.destination.region'] = array('$in' => $toArr);
				$query['params.source_types'] = array('$in' => $this->removeSpaces(explode("|", $opt_include[$this->config['opt_inc_source_field']])));
				if (in_array('adresse_email', $toArr)) {
					$query['$or'] = array(
						array('params.destination.region' => $query['params.destination.region']),
						array('params.dynamic.called_number' => new MongoRegex("/.+@.+/")),
					);
					unset($query['params.destination.region']);
				}
			}
		}
		//}

		return $query;
	}

	public function addGroupToRates($query, $type, $group) {
		$legitimateRatesQuery = array('type' => 'regular',
			"rates.$type" => array('$exists' => 1),
			'to' => array('$gte' => new MongoDate()),
		);

		//Add the new rates grouping configuration to active rates
		Billrun_Factory::db()->ratesCollection()->update(array_merge($query, $legitimateRatesQuery), array('$addToSet' => array("rates.$type.groups" => $group)), array('multiple' => 1));
	}

	function removePreviousGroupToRates($type, $group) {
		$legitimateRatesQuery = array('type' => 'regular',
			"rates.$type" => array('$exists' => 1),
			'to' => array('$gte' => new MongoDate()),
		);
		//Remove old rates grouping configuration from active rates
		Billrun_Factory::db()->ratesCollection()->update(array_merge($legitimateRatesQuery, array("rates.$type.groups" => $group)), array('$pull' => array("rates.$type.groups" => $group)), array('multiple' => 1));
	}

	public function loadCsvWithHeader($fileContent) {
		$lines = explode(PHP_EOL, $fileContent);
		$retArr = array();
		foreach ($lines as $key => $csvLine) {
			if ($key == 0) {
				$header = str_getcsv($csvLine);
				$header = array_map("trim", $header);
			} else if (!empty($csvLine)) {
				$line = str_getcsv($csvLine);
				$retArr[] = array_combine($header, array_map("trim", $line));
			}
		}
		return $retArr;
	}

}

class RatesImporterMTNPlugin {

	const INTERNATIONAL_RATES_FILE = 'International Rates 9_1pk.csv';
	const PREMIUM_VOICE_RATES_FILE = 'Premium Voice rates 9_1pk1.csv';
	const PREMIUM_SMS_RATES_FILE = 'Premium SMS rates 9_1pk1.csv';
	const RATE_PLANS_POSTPAID_MOBILE = 'Rate Plans - Postpaid Mobile 9.1pk1.csv';
	const BIB_PLANS_FILE = 'BiB plans v8.1.csv';
	const M2M_PLANS_FILE = 'M2M plans v8.1.csv';
	const DIM_VASDP_SERVICE_FILE = 'DIM_VASDP_SERVICE_9_1pk1.csv';
	const DIM_VASDP_SERVICE_PROVIDER_FILE = 'DIM_VASDP_SERVICE_PROVIDER_9_1pk1.csv';
	const OTHER_PARTY_MNPRN_FILE = 'otherPartyMNPRN 20190402v2.csv';
	const LOCATION_MCCMNC_FILE = 'locationMCCMNC 20190402.csv';
	const INTERNATIONAL_DESTINATION_CODE = 'International_Destination_Code.csv';
	const ROAMING_PREFIXES_FILE = 'Roaming Prefixes v8.1.csv';
	const ROAMING_PRICES_FOR_ZONE1 = 'RoamingPrices20190415_for_zone1_9_1pk1.csv';
	const DATE_REGEX = '/^\d{4}-/';
	
    protected $output = [
        'status' => 1,
        'warnings' => [],
        'errors' => [],
        'created' => array(),
        'updated' => array(),
        'imported_entities' => array()
    ];
    protected $errors = array('incorrect file', 'undefined interval', 'wrong price structure', 'No prefix was found', 'no revision for this key');
    protected $warnings = array('incorrect destination', 'no zone', 'was inserted only once', 'didnt find destination as it written in LocationMccMnc');
    protected $RatesToCreate = array();
    protected $RatesToCheckIfUpdate = array();
    protected $RatesToUpdate = array();
    protected $created_counter = 0;
    protected $updated_counter = 0;
    protected $rates_updateOrcreate = array();
    protected $plans_to_update_by_date = array();
    protected $updated_plans = array();
    protected $rates_keys_and_dates = array();

    public function isFileMissing($uploadedFiles){
        if(!array_key_exists(self::INTERNATIONAL_DESTINATION_CODE, $uploadedFiles) || !array_key_exists(self::LOCATION_MCCMNC_FILE, $uploadedFiles) || !array_key_exists(self::OTHER_PARTY_MNPRN_FILE, $uploadedFiles) || !array_key_exists(self::ROAMING_PREFIXES_FILE, $uploadedFiles)){
            return 'missing one of the necessary files.';
        }
        if(array_key_exists(self::BIB_PLANS_FILE, $uploadedFiles) && !array_key_exists(self::RATE_PLANS_POSTPAID_MOBILE, $uploadedFiles)){
            return 'missing one of the dependent files.';
        }
        if(array_key_exists(self::M2M_PLANS_FILE, $uploadedFiles) && !array_key_exists(self::RATE_PLANS_POSTPAID_MOBILE, $uploadedFiles)){
            return 'missing one of the dependent files.';
        }
        return false;
    }
    
    public function buildRatesAndPlansArrays($uploadedFiles = '') {
        $fileNames = array();
        $filesOrder = array(self::INTERNATIONAL_RATES_FILE, 
                       self::PREMIUM_VOICE_RATES_FILE, 
                       self::PREMIUM_SMS_RATES_FILE,
                       self::RATE_PLANS_POSTPAID_MOBILE, 
                       self::BIB_PLANS_FILE, 
                       self::M2M_PLANS_FILE, 
                       self::DIM_VASDP_SERVICE_FILE, 
                       self::DIM_VASDP_SERVICE_PROVIDER_FILE
            );
        $files = array_intersect($filesOrder, array_keys($uploadedFiles));
        $this->add_prefix_zone_mccmnc_roamingPrefix_MNPRN_CD_fields();

        $internationalRates = array();
        $plans = array();

        $to = new MongoDate(strtotime('2119-05-01 00:00:00'));

        $network_operator = array();
        if(array_key_exists(self::OTHER_PARTY_MNPRN_FILE, $uploadedFiles)){
        $otherPartyFile = fopen($uploadedFiles[self::OTHER_PARTY_MNPRN_FILE] , 'r');
        $otherPartyLine = 0;
        if ($otherPartyFile) {
            while (($line = fgetcsv($otherPartyFile)) !== FALSE) {
                $line_0 = $line[0];
                $line_1 = $line[1];
                $line_2 = $line[2];
                $line_3 = $line[3];
                if ($otherPartyLine == 0) {
                    $otherPartyLine ++;
                    continue;
                }
                if ($line_3 === "ON NET") {
                    $network_operator[$line_3][] = $line_0;
                }
                if ($line_3 === "OFF NET") {
                    if ($line_2 === "CYTA") {
                        $network_operator['cyta'][] = $line_0;
                    } else {
                        if ($line_2 === "PRIMETEL PLC") {
                            $network_operator['primetel'][] = $line_0;
                        } else {
                            if ($line_2 === "CABLENET") {
                                $network_operator['cablenet'][] = $line_0;
                            } else {
                                $network_operator['other'][] = $line_0;
                            }
                        }
                    }
                }
            }
            fclose($otherPartyFile);
        } else {
            //echo 'Problem loading file:  ' . $filesPath . 'otherPartyMNPRN 20190402v2.csv, die..' . PHP_EOL;
            $this->setStatus(0);
            $this->setError($filename, $this->errors[0] . ' ' . $filename);
            return $this->postprocess_output();
        }
        }

        //create MCCMNC list
        $mccmncRow = 0;
        if(array_key_exists(self::LOCATION_MCCMNC_FILE, $uploadedFiles)){
			$mccmncFile = fopen($uploadedFiles[self::LOCATION_MCCMNC_FILE] , 'r');
			$mccmnc = array();
			$mccmnc_by_zone = array();
			$CyprusMCCMNC = array();
			$mccmncCounter = 0;
			if ($mccmncFile) {
				while (($line = fgetcsv($mccmncFile)) !== FALSE) {
					$mccmncCounter++;
					if ($mccmncRow == 0) {//skip the headers line
						$mccmncRow ++;
						continue;
					}
					if ($line[6] !== "Cyprus") {
						$mccmnc[$line[6]]['mccmnc'][] = $line[0] . $line[1];
						$mccmnc[$line[6]]['the_zone'] = $line[7];
						//echo 'Reading line ' . $mccmncCounter . ' from MCCMNC' . PHP_EOL;
					} else {
						if ($line[1] === "10") {
							$CyprusMCCMNC[] = $line[0] . $line[1];
						}
					}
					$mccmnc_by_zone[$line[7]][] = $line[0] . $line[1];
				}
				fclose($mccmncFile);
			} else {
				//echo 'Problem loading file:  ' . $filesPath . 'locationMCCMNC 20190402.csv, die..' . PHP_EOL;
				$this->setStatus(0);
				$this->setError($filename, $this->errors[0] . ' ' . $filename);
				return $this->postprocess_output();
			}
		}

        $InternationalPrefixRow = 0;
        if(array_key_exists(self::INTERNATIONAL_DESTINATION_CODE, $uploadedFiles)){
        $InternationalCodesFile = fopen($uploadedFiles[self::INTERNATIONAL_DESTINATION_CODE] , 'r');
        $InternationalPrefAndSubPref = array();
        $InternationalCodeCounter = 0;
        if ($InternationalCodesFile) {
            while (($line = fgetcsv($InternationalCodesFile)) !== FALSE) {
                $InternationalCodeCounter++;
                if ($InternationalPrefixRow == 0) {//skip the headers line
                    $InternationalPrefixRow ++;
                    continue;
                }
                if ($line[1] === "") {
                    $prefixCell = $line[2];
                } else {
                    $prefixCell = $line[1];
                }
                $cell_prefix_in_array = explode(',', $prefixCell);
                for ($i = 0; $i < count($cell_prefix_in_array); $i++) {
                    $cell_prefix_in_array[$i] = ltrim($cell_prefix_in_array[$i], "00");
                }
                $country = rtrim($line[0]);
                $InternationalPrefAndSubPref[$country]['prefix'] = array();
                $InternationalPrefAndSubPref[$country]['prefix'] = $cell_prefix_in_array;
                $destination = rtrim($this->getDestination($line[0]));
                if (isset($mccmnc[$destination])) {
                    $InternationalPrefAndSubPref[$country]['the_zone'] = $mccmnc[$destination]['the_zone'];
                } else {
                    //echo 'didnt find ' . $destination . ' as it written in LocationMccMnc ' . PHP_EOL;
                    $this->setStatus(1);
                    $this->setGeneralError($destination . ' - ' . $this->warnings[3]);
                }
            }
            fclose($InternationalCodesFile);
        } else {
            //echo 'Problem loading file:  ' . $filesPath . ' International_Destination_Code.csv, die..' . PHP_EOL;
            $this->setStatus(0);
            $this->setError($filename, $this->errors[0] . ' ' . $filename);
            return $this->postprocess_output();
        }
        }

        if(array_key_exists(self::ROAMING_PREFIXES_FILE, $uploadedFiles)){
        $RoamingPrefixesFile = fopen($uploadedFiles[self::ROAMING_PREFIXES_FILE] , 'r');
        $RoamingPrefixesRow = 0;
        $RoamingPrefixesList = array();
        $RoamingPrefixesList_by_zones = array();
        $RoamingPrefixesList_by_zones_and_countries = array();
        $RoamingPrefixesCounter = 0;
        if ($RoamingPrefixesFile) {
            while (($line = fgetcsv($RoamingPrefixesFile)) !== FALSE) {
                $RoamingPrefixesCounter++;
                if ($RoamingPrefixesRow == 0) {//skip the headers line
                    $RoamingPrefixesRow ++;
                    continue;
                }
                $countryName = rtrim($line[0]);
                $RoamingPrefixesList[$countryName]['prefix'][] = $line[1];
                $RoamingPrefixesList[$countryName]['the_zone'] = $line[2];
                $RoamingPrefixesList_by_zones[$line[2]][] = $line[1];
                $RoamingPrefixesList_by_zones_and_countries[$line[2]][$line[0]][] = $line[1];
            }
            fclose($RoamingPrefixesFile);
        } else {
            //echo 'Problem loading file:  ' . $filesPath . ' Roaming Prefixes v8.1.csv, die..' . PHP_EOL;
            $this->setStatus(0);
            $this->setError($filename, $this->errors[0] . ' ' . $filename);
            return $this->postprocess_output();
        }
        }

        //done reading the codes file 
        //going through all files - reading each one.
        $output = array();
        $rates_to_check = array();
        $rates = array();
        $Rate_Plans = 0;
        $currentNumberHead = "900000";
        $nationalBiB = 0;
        $nationalM2M = 0;
        $M2M_Rates = 0;
        $vasServiceName = array();
        $vasServiceProviderArray = array();
        $dim_service_counter = 0;
        $dim_provider_service_counter = 0;
        $rate_Saver_add_on = array();
        $took_time = 0;
        $rate_with_error = 0;
        $wrong_file = 0;
        $dateIndex = 0;
        $InternationalDateFlag = 0;
        $PremiumVoiceDateFlag = 0;
        $PremiumSMSDateFlag = 0;
        $BiBPlansDateFlag = 0;
        $dimServiceDateFlag = 0;
        $dimServiceProviderDateFlag = 0;
        foreach ($files as $filename) {
            $dateIndex = 0;
            $wrong_file = 0;
            unset($rates);
            $rates = array();
            $file = fopen($uploadedFiles[$filename] , 'r');
            if ($file) {
                $Rate_Plans = 0;
                $row_inter_rates = 0;
                $row_voice_rates = 0;
                $row_premium_sms_rates = 0;
                while (($line = fgetcsv($file)) !== FALSE) {
                    $rate_with_error = 0;
                    if ($Rate_Plans === 1) {
                        break 1;
                    }
                    if (($M2M_Rates === 1) && ($filename === self::M2M_PLANS_FILE)) {
                        break 1;
                    }
                    $rate = array();
                    if ($filename == self::INTERNATIONAL_RATES_FILE) {
                        $flag = 0;
                        if ($took_time === 0) {
                            $start_time = microtime(true);
                            $took_time++;
                        }
                        //making sure the file is international rates 8.1
                        if (($row_inter_rates == 0) && ($line[0] == 'Postpaid International Rates for all plans' )) {
                            $row_inter_rates ++;
                            for ($a = 0; $a < count($line); $a++) {
                                if ($line[$a] === "from") {
                                    $dateIndex = $a;
                                    $InternationalDateFlag = 1;
                                }
								if ($line[$a] === "account") { $accountNumIndex = $a; }
								if ($line[$a] === "objectID") { $objectIDIndex = $a; }
								if ($line[$a] === "product label") { $labelIndex = $a; }
								if ($line[$a] === "description") { $descriotionIndex = $a; }
								if ($line[$a] === "fis descr") { $fis_descrIndex = $a; }
								if ($line[$a] === "management reporting") { $managmentReportingIndex = $a; }
                            }
                            if($InternationalDateFlag == 0){
                                Billrun_Factory::log()->log('No date column was found in ' . $filename . ' file. "from" header was expected in the header line that starts with "Postpaid International Rates for all plans". Assume the date is "now", for all of the rates.', Zend_Log::NOTICE);
                            }
                            continue;
                        } else {
                            if ($row_inter_rates == 0) {
                                $this->setStatus(0);
                                $this->setError($filename, $this->errors[0] . ' ' . $filename);
                                return $this->postprocess_output();
                            }
                        }
                        if ($row_inter_rates == 1) {
                            $row_inter_rates++;
                            continue;
                        }
                        //if the file is really International_Rates - continue
                        $description = $line[0];
                        $destination = $this->getDestination($description);
                        if ($destination == (-1)) {
                            //echo 'couldnt read the destination from ' . $description . ' International_Rates case' . PHP_EOL;
                            $this->setStatus(2);
                            $this->setEntityWarning($description, $this->warnings[0]);
                        }
                        $price = $line[1];
                        $interval_size_sec = $line[2];
						$rate['from'] = $from = date(Billrun_Base::base_datetimeformat, time());
                        if(($InternationalDateFlag == 1) && (!empty($line[$dateIndex])) && preg_match(self::DATE_REGEX, $line[$dateIndex])){
							$rate['from'] = $from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
						}
                        $rate['to'] = $to;
                        $rate['key'] = 'INTERNATIONAL_' . $this->getKey(trim($description)) . '_CALL';
                        $rate['description'] = 'international ' . $description . ' call';
						$rate['epic_description'] = !empty($line[$descriotionIndex]) ? $line[$descriotionIndex] : $rate['description'];
                        $rate['vatable'] = true;
                        $rate['pricing_method'] = "tiered";
                        $rate['tariff_category'] = "retail";
                        $rate['add_to_retail'] = true;
                        $rate['creation_time'] = $from;
						$rate['account_num'] = !empty($line[$accountNumIndex]) ? $line[$accountNumIndex] : "";
						$rate['object_id'] = !empty($line[$objectIDIndex]) ? $line[$objectIDIndex] : "";
						$rate['label'] = !empty($line[$labelIndex]) ? $line[$labelIndex] : "";
						$rate['fis_descr'] = !empty($line[$fis_descrIndex]) ? $line[$fis_descrIndex] : "";
						$rate['management_reporting'] = !empty($line[$managmentReportingIndex]) ? $line[$managmentReportingIndex] : "";
                        $rate['params']['international_call_saver_add_on'] = false;
                        $rate['params']['international_rate'] = true;
                        $rate['params']['national_rate'] = false;
						
						$rate_Saver_add_on['from'] = $from = date(Billrun_Base::base_datetimeformat, time());
                        if(($InternationalDateFlag == 1) && (!empty($line[$dateIndex])) && preg_match(self::DATE_REGEX, $line[$dateIndex])){
							$rate_Saver_add_on['from'] = $from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
						}
                        $rate_Saver_add_on['to'] = $to;
                        $rate_Saver_add_on['key'] = 'INTERNATIONAL_' . $this->getKey(trim($description)) . '_CALL_SAVER_ADD_ON';
                        $rate_Saver_add_on['description'] = 'international ' . $description . ' call saver add on';
						$rate_Saver_add_on['epic_description'] = !empty($line[$descriotionIndex]) ? $line[$descriotionIndex] : $rate_Saver_add_on['description'];
                        $rate_Saver_add_on['vatable'] = true;
                        $rate_Saver_add_on['pricing_method'] = "tiered";
                        $rate_Saver_add_on['tariff_category'] = "retail";
                        $rate_Saver_add_on['add_to_retail'] = true;
                        $rate_Saver_add_on['creation_time'] = $from;
                        $rate_Saver_add_on['params']['international_call_saver_add_on'] = true;
                        $rate_Saver_add_on['params']['international_rate'] = true;
                        $rate_Saver_add_on['params']['national_rate'] = false;

                        for ($a = 0; $a < count($CyprusMCCMNC); $a++) {
                            $rate['params']['mccmnc'][] = $CyprusMCCMNC[$a];
                            $rate_Saver_add_on['params']['mccmnc'][] = $CyprusMCCMNC[$a];
                        }
                        if (key_exists($destination, $RoamingPrefixesList)) {
                            if (key_exists('the_zone', $RoamingPrefixesList[$destination])) {
                                $rate['params']['destination_zone'] = $RoamingPrefixesList[$destination]['the_zone'];
                                $rate_Saver_add_on['params']['destination_zone'] = $RoamingPrefixesList[$destination]['the_zone'];
                            } else {
                                //echo $destination . ' didnt get a zone, so no zone was added, both to international and international saver add on' . PHP_EOL;
                                $this->setEntityWarning($rate['key'], $this->warnings[1]);
                                $this->setEntityWarning($rate_Saver_add_on['key'], $this->warnings[1]);
                            }
                        } else {
                            //echo 'Didnt find ' . $destination . ' at "Roaming Prefixes v8.1 file", so the zone wasnt added both to international and international saver add on, please add it manualy.' . PHP_EOL;
                            $this->setEntityWarning($rate['key'], $this->warnings[1]);
                            $this->setEntityWarning($rate_Saver_add_on['key'], $this->warnings[1]);
                        }

                        if ((in_array($description, array_keys($InternationalPrefAndSubPref))) || (in_array(($description . ' '), array_keys($InternationalPrefAndSubPref)))) {
                            foreach ($InternationalPrefAndSubPref[$description] as $key => $val) {
                                if ($key === 'prefix') {
                                    for ($v = 0; $v < count($val); $v++) {
                                        $rate['params']['prefix'][] = $val[$v];
                                        $rate_Saver_add_on['params']['prefix'][] = '929200' . $val[$v];
                                    }
                                }
                            }
                            sort($rate['params']['prefix'], SORT_STRING);
                            sort($rate_Saver_add_on['params']['prefix'], SORT_STRING);
                        } else {
                            //echo 'No ' . $description . ' at the International_Destination_Code.csv file - so no prefix was added both to international and international saver add on, please add it manualy.' . PHP_EOL;
                            $this->setEntityError($rate['key'], $this->errors[3]);
                            $this->setEntityError($rate_Saver_add_on['key'], $this->errors[3]);
                            $rate_with_error = 1;
                        }
                        $rate['rates']['local_call']['BASE']['rate'][] = array(
                            'from' => 0,
                            'to' => 'UNLIMITED',
                            'interval' => (int) $interval_size_sec,
                            'price' => (float) $price,
                            'uom_display' => array(
                                'range' => 'seconds',
                                'interval' => 'seconds',
                            )
                        );
                        $rate_Saver_add_on['rates']['local_call']['BASE']['rate'][] = array(
                            'from' => 0,
                            'to' => 'UNLIMITED',
                            'interval' => (int) $interval_size_sec,
                            'price' => (float) $price,
                            'uom_display' => array(
                                'range' => 'seconds',
                                'interval' => 'seconds',
                            )
                        );
                        $internationalRates[] = $rate;
                        $rates[$rate['key']] = $rate;
                        $internationalRates[] = $rate_Saver_add_on;
                        $rates[$rate_Saver_add_on['key']] = $rate_Saver_add_on;
                        if ($rate_with_error == 0) {
                            $this->rates_updateOrcreate[$rate['key']] = $rate;
                            $this->rates_updateOrcreate[$rate_Saver_add_on['key']] = $rate_Saver_add_on;
                            $this->rates_keys_and_dates[$rate['key']] = $from;
                            $this->rates_keys_and_dates[$rate_Saver_add_on['key']] = $from;
                        } else {
                            $this->setStatus(2);
                            $rate_with_error = 0;
                        }
                        unset($rate);
                        unset($rate_Saver_add_on);
                    }
                    if ($filename == self::PREMIUM_VOICE_RATES_FILE) {
                        if (($row_voice_rates == 0) && ($line[0] === "PREMIUM IVR NUMBER")) {
                            $row_voice_rates ++;
                            for ($a = 0; $a < count($line); $a++) {
                                if ($line[$a] === "from") {
                                    $dateIndex = $a;
                                    $PremiumVoiceDateFlag = 1;
                                }
								if ($line[$a] === "account") { $accountNumIndex = $a; }
								if ($line[$a] === "objectID") { $objectIDIndex = $a; }
								if ($line[$a] === "product label") { $labelIndex = $a; }
								if ($line[$a] === "description") { $descriotionIndex = $a; }
								if ($line[$a] === "fis descr") { $fis_descrIndex = $a; }
								if ($line[$a] === "management reporting") { $managmentReportingIndex = $a; }
                            }
                            if($PremiumVoiceDateFlag == 0){
                                Billrun_Factory::log()->log('No date column was found in ' . $filename . ' file. "from" header was expected in the header line that starts with "PREMIUM IVR NUMBER". Assume the date is "now", for all of the rates.', Zend_Log::NOTICE);
                            }
                            continue;
                        } else {
                            if ($row_voice_rates == 0) {
                                //echo "Premium_Voice_Rates - first column isn't -PREMIUM IVR NUMBER - wrong file, die..\n" . PHP_EOL;
                                $this->setStatus(0);
                                $this->setError($filename, $this->errors[0] . ' ' . $filename);
                                return $this->postprocess_output();
                            }
                        }
                        if ($currentNumberHead !== $line[1]) {
                            $currentNumberHead = $line[1];
                        }
                        $header = $line[1];
                        $prefix = $line[0];
                        $price = $line[3];
                        $rate['description'] = 'Premium voice call to ' . $currentNumberHead . ' ' . $line[2];
                        $rate['epic_description'] = !empty($line[$descriotionIndex]) ? $line[$descriotionIndex] : $rate['description'];
						$rate['key'] = $this->getKey(trim($rate['description']));
                        if (!isset($rates[$rate['key']])) {
                            $interval_size_sec = $this->getInterval($line[4]);
                            if ($interval_size_sec == (-1)) {
                                //echo 'undefined interval - IVR NUMBER' . $prefix . 'Premium_Voice_Rates case' . PHP_EOL;
                                $this->setEntityError($rate['key'], $this->errors[1]);
                                $rate_with_error = 1;
                            }
                            if ($interval_size_sec == 1) {
                                $rate['rates']['local_call']['BASE']['rate'][] = array(
                                    'from' => 0,
                                    'to' => 1,
                                    'interval' => (int) $interval_size_sec,
                                    'price' => (float) $price,
                                    'uom_display' => array(
                                        'range' => 'seconds',
                                        'interval' => 'seconds',
                                    )
                                );
                                $rate['rates']['local_call']['BASE']['rate'][] = array(
                                    'from' => 2,
                                    'to' => 'UNLIMITED',
                                    'interval' => (int) $interval_size_sec,
                                    'price' => 0,
                                    'uom_display' => array(
                                        'range' => 'seconds',
                                        'interval' => 'seconds',
                                    )
                                );
                            }
                            if ($interval_size_sec == 60) {
                                $rate['rates']['local_call']['BASE']['rate'][] = array(
                                    'from' => 0,
                                    'to' => 'UNLIMITED',
                                    'interval' => (int) $interval_size_sec,
                                    'price' => (float) $price,
                                    'uom_display' => array(
                                        'range' => 'seconds',
                                        'interval' => 'seconds',
                                    )
                                );
                            }
                            if ($interval_size_sec == 26) {
                                $rate['rates']['local_call']['BASE']['rate'][] = array(
                                    'from' => 0,
                                    'to' => 26,
                                    'interval' => 1,
                                    'price' => 0,
                                    'uom_display' => array(
                                        'range' => 'seconds',
                                        'interval' => 'seconds',
                                    )
                                );
                                $rate['rates']['local_call']['BASE']['rate'][] = array(
                                    'from' => 27,
                                    'to' => 'UNLIMITED',
                                    'interval' => 60,
                                    'price' => (float) $price,
                                    'uom_display' => array(
                                        'range' => 'seconds',
                                        'interval' => 'seconds',
                                    )
                                );
                            }
														
							$rate['from'] = $from = date(Billrun_Base::base_datetimeformat, time());
                            if(($PremiumVoiceDateFlag == 1 && (!empty($line[$dateIndex])) && preg_match(self::DATE_REGEX, $line[$dateIndex]))){
                                $rate['from'] = $from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
							}
                            $rate['to'] = $to;
                            $rate['description'] = 'Premium voice call to ' . $header . ' ' . $line[2];
							$rate['epic_description'] = !empty($line[$descriotionIndex]) ? $line[$descriotionIndex] : $rate['description'];
                            $rate['key'] = $this->getKey(trim($rate['description']));
                            $rate['params']['prefix'][] = $prefix;
                            $rate['vatable'] = true;
                            $rate['pricing_method'] = "tiered";
                            $rate['tariff_category'] = "retail";
                            $rate['add_to_retail'] = true;
                            $rate['creation_time'] = $from;
							$rate['account_num'] = !empty($line[$accountNumIndex]) ? $line[$accountNumIndex] : "";
							$rate['object_id'] = !empty($line[$objectIDIndex]) ? $line[$objectIDIndex] : "";
							$rate['label'] = !empty($line[$labelIndex]) ? $line[$labelIndex] : "";
							$rate['fis_descr'] = !empty($line[$fis_descrIndex]) ? $line[$fis_descrIndex] : "";
							$rate['management_reporting'] = !empty($line[$managmentReportingIndex]) ? $line[$managmentReportingIndex] : "";
                            $rate['params']['prefix'][] = $header;
                            $rate['params']['national_rate'] = true;
                            $rate['params']['international_rate'] = false;

                            if ($line[2] == "POSTPAID") {
                                $rate['collection_type'] = "post_paid";
                            } else {
                                $rate['collection_type'] = "pre_paid";
                            }
                            //echo 'Inserted voice to ' . $rate['description'] . PHP_EOL;
                            $rates[$rate['key']] = $rate;
                            if ($rate_with_error == 0) {
                                $this->rates_updateOrcreate[$rate['key']] = $rate;
                                $this->rates_keys_and_dates[$rate['key']] = $from;
                            } else {
                                $this->setStatus(2);
                                $rate_with_error = 0;
                            }
                        } else {
                            $rates[$rate['key']]['params']['prefix'][] = $prefix;
                            $this->rates_updateOrcreate[$rate['key']]['params']['prefix'][] = $prefix;
                        }
                    }
                    if ($filename == self::PREMIUM_SMS_RATES_FILE) {
                        if (($row_premium_sms_rates == 0) && ($line[0] == "SMS SHORT CODE")) {
                            $row_premium_sms_rates ++;
                            for ($a = 0; $a < count($line); $a++) {
                                if ($line[$a] === "from") {
                                    $dateIndex = $a;
                                    $PremiumSMSDateFlag = 1;
                                }
								if ($line[$a] === "account") { $accountNumIndex = $a; }
								if ($line[$a] === "objectID") { $objectIDIndex = $a; }
								if ($line[$a] === "product label") { $labelIndex = $a; }
								if ($line[$a] === "description") { $descriotionIndex = $a; }
								if ($line[$a] === "fis descr") { $fis_descrIndex = $a; }
								if ($line[$a] === "management reporting") { $managmentReportingIndex = $a; }
                            }
                            if($PremiumSMSDateFlag == 0){
                                Billrun_Factory::log()->log('No date column was found in ' . $filename . ' file. "from" header was expected in the header line that starts with "SMS SHORT CODE". Assume the date is "now", for all of the rates.', Zend_Log::NOTICE);
                            }
                            continue;
                        } else {
                            if ($row_premium_sms_rates == 0) {
                                //echo "Premium_SMS_Rates - first column isn't -SMS SHORT CODE - wrong file, die..\n" . PHP_EOL;
                                $this->setStatus(0);
                                $this->setError($filename, $this->errors[0] . ' ' . $filename);
                                return $this->postprocess_output();
                            }
                        }
                        $prefix = $line[0];
                        $price = $line[1];

                        $rate['rates']['local_sms']['BASE']['rate'][] = array(
                            'from' => 0,
                            'to' => 'UNLIMITED',
                            'interval' => 1,
                            'price' => (float) $price,
                            'uom_display' => array(
                                'range' => 'counter',
                                'interval' => 'counter',
                            )
                        );
						
						$rate['from'] = $from = date(Billrun_Base::base_datetimeformat, time());
                        if(($PremiumSMSDateFlag == 1) && (!empty($line[$dateIndex])) && preg_match(self::DATE_REGEX, $line[$dateIndex])){
                            $rate['from'] = $from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
						}
                        $rate['to'] = $to;
                        $rate['key'] = 'PREMIUM_SMS_CODE_' . $this->getKey($prefix);
                        $rate['description'] = 'Premium sms with code number ' . $prefix;
                        $rate['epic_description'] = !empty($line[$descriotionIndex]) ? $line[$descriotionIndex] : $rate['description'];						
						$rate['params']['prefix'][] = $prefix;
                        $rate['vatable'] = true;
                        $rate['pricing_method'] = "tiered";
                        $rate['tariff_category'] = "retail";
                        $rate['add_to_retail'] = true;
                        $rate['creation_time'] = $from;
						$rate['account_num'] = !empty($line[$accountNumIndex]) ? $line[$accountNumIndex] : "";
						$rate['object_id'] = !empty($line[$objectIDIndex]) ? $line[$objectIDIndex] : "";
						$rate['label'] = !empty($line[$labelIndex]) ? $line[$labelIndex] : "";
						$rate['fis_descr'] = !empty($line[$fis_descrIndex]) ? $line[$fis_descrIndex] : "";
						$rate['management_reporting'] = !empty($line[$managmentReportingIndex]) ? $line[$managmentReportingIndex] : "";
                        $rate['params']['national_rate'] = true;
                        $rate['params']['international_rate'] = false;


                        if ((isset($rates[$rate['description']]))) {
                            //echo $rate['description'] . ' was insert only once.' . PHP_EOL;
                            $this->setEntityWarning($rate['key'], $this->warnings[2]);
                        } else {
                            $this->rates_updateOrcreate[$rate['key']] = $rate;
                            $this->rates_keys_and_dates[$rate['key']] = $from;
                            $rates[$rate['description']] = $rate;
                        }
                        //echo 'Inserted SMS code ' . $line[0] . PHP_EOL;
                    }
                    if (($filename == self::RATE_PLANS_POSTPAID_MOBILE) && ($Rate_Plans == 0)) {                        
                        $Rate_Plans = 1;
                        $PostPaidMobileRates = $this->PostPaidMobileRates($uploadedFiles, $RoamingPrefixesList, $mccmnc, $plans, $ratesCollection, $network_operator, $CyprusMCCMNC, $InternationalPrefAndSubPref, $mccmnc_by_zone, $RoamingPrefixesList_by_zones, $RoamingPrefixesList_by_zones_and_countries);
                        if($PostPaidMobileRates == -1){
                            return $this->postprocess_output();
                        }
                        $dontInsert = 1;
                        for ($a = 0; $a < count($PostPaidMobileRates); $a++) {
                            if (isset($PostPaidMobileRates[$a])) {
                                $rates[] = $PostPaidMobileRates[$a];
                            }
                        }
                    }
                    if ($filename == self::BIB_PLANS_FILE) {
                        $BroadBandInBoxM40Index = 3;
                        $MobileBroadBandAccessIndex = 8;
                        if ($line[1] === "MTN Plan Name") {
                            for ($a = 0; $a < count($line); $a++) {
                                if (($line[$a] !== "") && ($line[$a] !== "MTN Plan Name") && ($line[$a] !== "from")) {
                                    $BiBPlans[$a] = $line[$a];
                                }
                                if ($line[$a] === "from") {
                                    $dateIndex = $a;
                                    $BiBPlansDateFlag = 1;
                                }
                            }
                            if($BiBPlansDateFlag == 0){
                                Billrun_Factory::log()->log('No date column was found in ' . $filename . ' file. "from" header was expected in the header line that starts with "MTN Plan Name". Assume the date is "now", for all of the rates.', Zend_Log::NOTICE);
                            }
                        }
                        if ($line[0] === "Local SMS") {
                            //searching for nat local sms rate, to override it's price at BiB plans.
                            for ($b = 0; $b < count($PostPaidMobileRates); $b++) {
                                if ($PostPaidMobileRates[$b]['key'] === "NATL_SMS_ON_NET") {
                                    $localSMSRate = $PostPaidMobileRates[$b];
                                }
                            }
                            //going through the plans, overriding the rate's price.
                            foreach ($BiBPlans as $key => $planName) {
                                $rate['key'] = $localSMSRate['key'];
                                if (($line[$key] !== "N/A") && ($line[$key] !== "n/a")) {
                                    $returned_price = $this->getPricedRate($line[$key], $line, $key);
                                    $price = (float) $returned_price['rates']['sms']['BASE']['rate'][0]['price'];
                                } else {
                                    $price = 0;
                                }
                                $rate['rates']['local_sms']['BASE']['rate'][] = array(
                                    'from' => 0,
                                    'to' => 'UNLIMITED',
                                    'interval' => 1,
                                    'price' => (float) $price,
                                    'uom_display' => array(
                                        'range' => 'counter',
                                        'interval' => 'counter',
                                    )
                                );
                                $usageType = 'local_sms';
                                $planKey = $this->getKey($planName);
								$defaultFrom = date(Billrun_Base::base_datetimeformat, time());
                                for ($i = 0; $i < count($rate['rates'][$usageType]['BASE']['rate']); $i++) {
                                    if(($BiBPlansDateFlag == 1) && (!empty($line[$dateIndex])) && preg_match(self::DATE_REGEX, $line[$dateIndex])){
										$from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
									} else {
										$from = $defaultFrom;
									}
                                    $this->plans_to_update_by_date[$planKey][$from][$rate['key']][$usageType]['rate'][] = $rate['rates'][$usageType]['BASE']['rate'][$i];                                                                    
                                }
                                unset($rate);
                            }
                        }
                        if ($line[0] === "International SMS") {
                            for ($b = 0; $b < count($PostPaidMobileRates); $b++) {
                                if ($PostPaidMobileRates[$b]['key'] === "INTERNATIONAL_SMS") {
                                    $internationalSMSRate = $PostPaidMobileRates[$b];
                                }
                            }
                            //going through the plans, overriding the rate's price.
                            foreach ($BiBPlans as $key => $planName) {
                                $rate['key'] = $internationalSMSRate['key'];
                                if (($line[$key] !== "N/A") && ($line[$key] !== "n/a")) {
                                    $returned_price = $this->getPricedRate($line[$key], $line, $key);
                                    $price = (float) $returned_price['rates']['sms']['BASE']['rate'][0]['price'];
                                } else {
                                    $price = 0;
                                }
                                $rate['rates']['local_sms']['BASE']['rate'][] = array(
                                    'from' => 0,
                                    'to' => 'UNLIMITED',
                                    'interval' => 1,
                                    'price' => (float) $price,
                                    'uom_display' => array(
                                        'range' => 'counter',
                                        'interval' => 'counter',
                                    )
                                );
                                $usageType = 'local_sms';
                                $planKey = $this->getKey($planName);		
								$defaultFrom = date(Billrun_Base::base_datetimeformat, time());
                                for ($i = 0; $i < count($rate['rates'][$usageType]['BASE']['rate']); $i++) {
                                    if(($BiBPlansDateFlag == 1) && (!empty($line[$dateIndex])) && preg_match(self::DATE_REGEX, $line[$dateIndex])){
										$from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
									} else {
										$from = $defaultFrom;
									}
                                    $this->plans_to_update_by_date[$planKey][$from][$rate['key']][$usageType]['rate'][] = $rate['rates'][$usageType]['BASE']['rate'][$i];                                   
                                }
                                unset($rate);
                            }
                        }
                        if ($line[0] === "Nat'l Data (Gb)") {
                            $nationalBiB = 1;
                        }
                        if ($nationalBiB === 1) {
                            if ($line[1] === "Out of Bundle") {
                                for ($b = 0; $b < count($PostPaidMobileRates); $b++) {
                                    if ($PostPaidMobileRates[$b]['key'] === "NATIONAL_DATA_GB_OUT_OF_BUNDLE") {
                                        $natDataOutOfBoundleRate = $PostPaidMobileRates[$b];
                                    }
                                }
                                foreach ($BiBPlans as $key => $planName) {
                                    $rate['key'] = $natDataOutOfBoundleRate['key'];
                                    if (($line[$key] !== "N/A") && ($line[$key] !== "n/a")) {
                                        $returned_price = $this->getPricedRate($line[$key], $line, $key);
                                        $price = (float) $returned_price['rates']['data']['BASE']['rate'][0]['price'];
                                        $interval = (int) $returned_price['rates']['data']['BASE']['rate'][0]['interval'];
                                    } else {
                                        $price = 0;
                                        $interval = 1;
                                    }
                                    $rate['rates']['data']['BASE']['rate'][] = array(
                                        'from' => 0,
                                        'to' => 'UNLIMITED',
                                        'interval' => (int) $interval,
                                        'price' => (float) $price,
                                        'uom_display' => array(
                                            'range' => 'kb1000',
                                            'interval' => 'kb1000',
                                        )
                                    );
                                    $dontInsert = 1;
                                    $usageType = 'data';
                                    $planKey = $this->getKey($planName);
									$defaultFrom = $from = date(Billrun_Base::base_datetimeformat, time());
                                    for ($i = 0; $i < count($rate['rates'][$usageType]['BASE']['rate']); $i++) {										
                                        if(($BiBPlansDateFlag == 1) && (!empty($line[$dateIndex])) && preg_match(self::DATE_REGEX, $line[$dateIndex])){
											$from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
										} else {
											$from = $defaultFrom;
										}
                                        $this->plans_to_update_by_date[$planKey][$from][$rate['key']][$usageType]['rate'][] = $rate['rates'][$usageType]['BASE']['rate'][$i];
                                    }
                                    unset($rate);
                                }
                            }
                        }
                    }
                    if (($filename == self::M2M_PLANS_FILE) && ($M2M_Rates == 0)) {
                        $M2M_Rates = 1;
                        $M2MRates = $this->M2MRates($uploadedFiles, $PostPaidMobileRates, $ratesCollection);
                        if($M2MRates == -1){
                            return $this->postprocess_output();
                        }
                        $dontInsert = 1;
                    }
                    if ($filename == self::DIM_VASDP_SERVICE_FILE) {
                        if ($line[0] === "SERVICE_CD") {
                            for ($a = 0; $a < count($line); $a++) {
                                if ($line[$a] === "from") {
                                    $dateIndex = $a;
                                    $dimServiceDateFlag = 1;
                                }
								if ($line[$a] === "account") { $accountNumIndex = $a; }
								if ($line[$a] === "objectID") { $objectIDIndex = $a; }
								if ($line[$a] === "product label") { $labelIndex = $a; }
								if ($line[$a] === "description") { $descriotionIndex = $a; }
								if ($line[$a] === "fis descr") { $fis_descrIndex = $a; }
								if ($line[$a] === "management reporting") { $managmentReportingIndex = $a; }
                            }
                            if($dimServiceDateFlag == 0){
                                Billrun_Factory::log()->log('No date column was found in ' . $filename . ' file. "from" header was expected in the header line that starts with "SERVICE_CD". Assume the date is "now", for all of the rates.', Zend_Log::NOTICE);
                            }
                        }
                        if ($dim_service_counter == 0) {
                            $dim_service_counter = 1;
                            continue;
                        }
                        $serviceName = $line[2];
                        $service_cd = $line[0];
                        if ($serviceName !== "") {
                            if (array_key_exists($service_cd, $rates)) {
                                $this->setEntityWarning($serviceName, $this->warnings[2]);
                                //echo 'Service_cd ' . $service_cd . ' was inserted once.' . PHP_EOL;
                            } else {
								$rate['from'] = $from = date(Billrun_Base::base_datetimeformat, time());
                                if(($dimServiceDateFlag == 1) && (!empty($line[$dateIndex])) && preg_match(self::DATE_REGEX, $line[$dateIndex])){
                                    $rate['from'] = $from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
								}
                                $rate['to'] = $to;
                                $rate['vatable'] = true;
                                $rate['pricing_method'] = "tiered";
                                $rate['tariff_category'] = "retail";
                                $rate['add_to_retail'] = true;
                                $rate['creation_time'] = $from;
								$rate['account_num'] = !empty($line[$accountNumIndex]) ? $line[$accountNumIndex] : "";
								$rate['object_id'] = !empty($line[$objectIDIndex]) ? $line[$objectIDIndex] : "";
								$rate['label'] = !empty($line[$labelIndex]) ? $line[$labelIndex] : "";
								$rate['fis_descr'] = !empty($line[$fis_descrIndex]) ? $line[$fis_descrIndex] : "";
								$rate['management_reporting'] = !empty($line[$managmentReportingIndex]) ? $line[$managmentReportingIndex] : "";
                                $rate['params']['prefix'] = $service_cd;
                                $MO_charge_amt = $line[7];
                                $MT_charge_amt = $line[8];
                                if (($MO_charge_amt !== "") && ($MT_charge_amt !== "")) {
                                    if (($MO_charge_amt === '0') && ($MT_charge_amt !== '0')) {
                                        $rate['description'] = 'VAS ' . $service_cd . ' ' . $serviceName . ' MT';
                                        $rate['key'] = $this->getKey(trim($rate['description']));
                                        $price = (float) $MT_charge_amt;
                                    }
                                    if (($MT_charge_amt === '0') && ($MO_charge_amt !== '0')) {
                                        $rate['description'] = 'VAS ' . $service_cd . ' ' . $serviceName . ' MO';
                                        $rate['key'] = $this->getKey(trim($rate['description']));
                                        $price = (float) $MO_charge_amt;
                                    }
                                    if (($MO_charge_amt === '0') && ($MT_charge_amt === '0')) {
                                        $rate['description'] = 'VAS ' . $service_cd . ' ' . $serviceName . ' MT';
                                        $rate['key'] = $this->getKey(trim($rate['description']));
                                        $price = 0;
                                    }
                                    if (($MT_charge_amt !== 0) && ($MO_charge_amt !== 0)) {
                                        $rate['description'] = 'VAS ' . $service_cd . ' ' . $serviceName . ' MO';
                                        $rate['key'] = $this->getKey(trim($rate['description']));
                                        $price = (float) $MO_charge_amt;
                                        //echo $service_cd . ' has price both at MO and MT case. MO was chosen.' . PHP_EOL;
                                    }
                                } else {
                                    continue;
                                }
								$rate['epic_description'] = !empty($line[$descriotionIndex]) ? $line[$descriotionIndex] : $rate['description'];
                                $rate['rates']['vas']['BASE']['rate'][] = array(
                                    'from' => 0,
                                    'to' => 'UNLIMITED',
                                    'interval' => 1,
                                    'price' => (float) $price,
                                    'uom_display' => array(
                                        'range' => 'counter',
                                        'interval' => 'counter',
                                    )
                                );
                                $rates[$service_cd] = $rate;
                                $this->rates_updateOrcreate[$rate['key']] = $rate;
                                $this->rates_keys_and_dates[$rate['key']] = $from;
                                $dontInsert = 1;
                                unset($rate);
                            }
                        }
                    }
                    if ($filename == self::DIM_VASDP_SERVICE_PROVIDER_FILE) {
                        if ($line[0] === "SERVICE_PROVIDER_CD") {
                            for ($a = 0; $a < count($line); $a ++) {
                                if ($line[$a] === "from") {
                                    $dateIndex = $a;
                                    $dimServiceProviderDateFlag = 1;
                                }
								if ($line[$a] === "account") { $accountNumIndex = $a; }
								if ($line[$a] === "objectID") { $objectIDIndex = $a; }
								if ($line[$a] === "product label") { $labelIndex = $a; }
								if ($line[$a] === "description") { $descriotionIndex = $a; }
								if ($line[$a] === "fis descr") { $fis_descrIndex = $a; }
								if ($line[$a] === "management reporting") { $managmentReportingIndex = $a; }
                            }
                            if($dimServiceProviderDateFlag == 0){
                                Billrun_Factory::log()->log('No date column was found in ' . $filename . ' file. "from" header was expected in the header line that starts with "SERVICE_PROVIDER_CD". Assume the date is "now", for all of the rates.', Zend_Log::NOTICE);
                            }
                        }
                        if ($dim_provider_service_counter == 0) {
                            $dim_provider_service_counter = 1;
                            continue;
                        }
                        $serviceName = $line[1];
                        $service_provider_cd = $line[0];
                        if ($service_provider_cd === "") {
                            continue;
                        }
                        if ($serviceName !== "") {
                            if (array_key_exists($service_provider_cd, $rates)) {
                                $this->setEntityWarning($service_provider_cd, $this->warnings[2]);
                                //echo 'Service_provider_cd ' . $service_provider_cd . ' was inserted once.' . PHP_EOL;
                            } else {
								$rate['from'] = $from = date(Billrun_Base::base_datetimeformat, time());
                                if(($dimServiceProviderDateFlag == 1) && !empty($line[$dateIndex]) && preg_match(self::DATE_REGEX, $line[$dateIndex])){
                                    $rate['from'] = $from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
                                }
                                $rate['to'] = $to;
                                $rate['vatable'] = true;
                                $rate['pricing_method'] = "tiered";
                                $rate['tariff_category'] = "retail";
                                $rate['add_to_retail'] = true;
                                $rate['creation_time'] = $from;
								$rate['account_num'] = !empty($line[$accountNumIndex]) ? $line[$accountNumIndex] : "";
								$rate['object_id'] = !empty($line[$objectIDIndex]) ? $line[$objectIDIndex] : "";
								$rate['label'] = !empty($line[$labelIndex]) ? $line[$labelIndex] : "";
								$rate['fis_descr'] = !empty($line[$fis_descrIndex]) ? $line[$fis_descrIndex] : "";
								$rate['management_reporting'] = !empty($line[$managmentReportingIndex]) ? $line[$managmentReportingIndex] : "";
                                $rate['params']['prefix'] = $service_provider_cd;
                                $rate['description'] = 'VAS provider ' . $service_provider_cd . ' ' . $serviceName;
                                $rate['epic_description'] = !empty($line[$descriotionIndex]) ? $line[$descriotionIndex] : $rate['description'];
								$rate['key'] = $this->getKey(trim($rate['description']));
                                $price = 0;
                                $rate['rates']['vas']['BASE']['rate'][] = array(
                                    'from' => 0,
                                    'to' => 'UNLIMITED',
                                    'interval' => 1,
                                    'price' => (float) $price,
                                    'uom_display' => array(
                                        'range' => 'counter',
                                        'interval' => 'counter',
                                    )
                                );
                                $rates[$service_provider_cd] = $rate;
                                $this->rates_updateOrcreate[$rate['key']] = $rate;
                                $this->rates_keys_and_dates[$rate['key']] = $from;
                                $dontInsert = 1;
                                unset($rate);
                            }
                        }
                    }
                }
                fclose($file);
                $ratesCollection = Billrun_Factory::db()->ratesCollection();
            } else {
                $this->setError($filename, 'Problem loading file:  ' . $filename);
                return $this->postprocess_output();
            }
        }
    }

    public function updateOrCreateRatesAndPlans() {
        $RatesKeys = array_keys($this->rates_updateOrcreate);
        $ratesKeysFromDB = Billrun_Factory::db()->ratesCollection()->distinct('key');
        for ($a = 0; $a < count($RatesKeys); $a++) {
            if (!in_array($RatesKeys[$a], $ratesKeysFromDB)) {
                $this->RatesToCreate[] = $RatesKeys[$a];
            } else {
                $this->RatesToCheckIfUpdate[$this->rates_keys_and_dates[$RatesKeys[$a]]][] = $RatesKeys[$a];
            }
        }
        $this->createRates();
        
        foreach ($this->RatesToCheckIfUpdate as $date => $ratesInDate) {
            $ratesInDateMongo = Billrun_Factory::db()->ratesCollection()->distinct('key', array('from' => ['$lte' => new MongoDate(strtotime($date))], 'to' => ['$gte' => new MongoDate(strtotime($date))]));
            for ($i = 0; $i < count($ratesInDate); $i++) {
                if (!in_array($ratesInDate[$i], $ratesInDateMongo)) {
                    $this->setEntityError($ratesInDate[$i], $this->errors[4]);
                } else {
                    $this->RatesToUpdate[] = $ratesInDate[$i];
                }
            }
        }
        $this->updateRates();
        
        $this->updatePlanByDate();
    }

    protected function createRates() {
        for ($a = 0; $a < count($this->RatesToCreate); $a++) {
            $params['request']['update'] = json_encode($this->rates_updateOrcreate[$this->RatesToCreate[$a]]);
            $params['request']['action'] = 'create';
            $params['request']['collection'] = 'rates';
            $params['collection'] = 'rates';
            try{
                $Rates_Models = new Models_Rates($params);
                $response = $Rates_Models->create();
                if ($response == 1) {
                    $this->created_counter++;
                    if (isset($this->rates_updateOrcreate[$this->RatesToCreate[$a]]['key'])) {
                        $this->output['created'][] = $this->rates_updateOrcreate[$this->RatesToCreate[$a]]['key'];
                    }
                }
            } catch(Exception $ex){
                $this->setEntityError($this->rates_updateOrcreate[$this->RatesToCreate[$a]]['key'], $ex->getMessage());
            }
        }
    }

    protected function updateRates() {
        for ($i = 0; $i < count($this->RatesToUpdate); $i++) {
            $rateId = Billrun_Factory::db()->ratesCollection()->distinct('_id', array('key' => ($this->rates_updateOrcreate[$this->RatesToUpdate[$i]]['key'])));
            $rateId = (string) $rateId[0];
            $params['request']['query'] = json_encode(array(
                '_id' => $rateId,
                'effective_date' => $this->rates_keys_and_dates[$this->rates_updateOrcreate[$this->RatesToUpdate[$i]]['key']],
            ));
            $params['request']['update'] = json_encode($this->rates_updateOrcreate[$this->RatesToUpdate[$i]]);
            $params['request']['action'] = 'permanentchange';
            $params['request']['collection'] = 'rates';
            $params['collection'] = 'rates';
            try{
                $update = new Models_Entity($params);
                $update->permanentChange();
            } catch(Exception $ex){
                $this->setEntityError($this->rates_updateOrcreate[$this->RatesToUpdate[$i]]['key'], $ex->getMessage());
            }
            $this->updated_counter++;
        }
    }

    protected function updatePlanByDate() {
        foreach ($this->plans_to_update_by_date as $plan => $dates) {
            ksort($dates);
            $planKey = $this->getKey($plan);
            $planId = Billrun_Factory::db()->plansCollection()->distinct('_id', array('key' => $planKey));
            foreach ($dates as $date => $keys) {
                $currentPlan = $this->getPlan($planKey);
                if(!empty($currentPlan)){
                    $revisionIdByFrom = array();
                    foreach ($currentPlan as $id => $values) {
                        $revisionIdByFrom[gmdate("Y-m-d\TH:i:s\Z", $values['from']->sec)] = $id;
                    }
                    ksort($revisionIdByFrom);
                    $fromArray = array_keys($revisionIdByFrom);
                    unset($fromsToUpdate);
                    for ($i = 0; $i < count($fromArray); $i++) {
                        if (isset($fromArray[$i + 1])) {
                            $dateInSec = strtotime($date);
                            $dateInMongo = new MongoDate($dateInSec);
                            if ((($dateInMongo > new MongoDate($fromArray[$i])) && ($dateInMongo < new MongoDate($fromArray[$i + 1]))) || (new MongoDate($fromArray[$i]) > $dateInMongo)) {
                                $fromsToUpdate[$fromArray[$i]] = $revisionIdByFrom[$fromArray[$i]];
                            }
                        } else {
                            $fromsToUpdate[$fromArray[$i]] = $revisionIdByFrom[$fromArray[$i]];
                        }
                    }
                    ksort($fromsToUpdate);
                    $fromsToUpdateIndexes = array_keys($fromsToUpdate);
                    unset($updatesArrayByFrom);
                    for ($i = 0; $i < count($fromsToUpdateIndexes); $i++) {
                        $currentRevision = $currentPlan[$fromsToUpdate[$fromsToUpdateIndexes[$i]]]->getRawData();
                        if (!isset($currentRevision['rates'])) {
                            foreach ($keys as $rateKey => $rate) {
                                $usageType = array_keys($rate)[0];
                                $updatesArrayByFrom[$fromsToUpdateIndexes[$i]]['id'] = $fromsToUpdate[$fromsToUpdateIndexes[$i]];
                                for ($a = 0; $a < count($rate[$usageType]['rate']); $a++) {
                                    $updatesArrayByFrom[$fromsToUpdateIndexes[$i]]['rates'][$rateKey][$usageType]['rate'][] = $rate[$usageType]['rate'][$a];
                                }
                            }
                        } else {
                            $updatesArrayByFrom[$fromsToUpdateIndexes[$i]]['rates'] = $currentRevision['rates'];
                            foreach ($keys as $rateKey => $rate) {
                                $usageType = array_keys($rate)[0];
                                $updatesArrayByFrom[$fromsToUpdateIndexes[$i]]['id'] = $fromsToUpdate[$fromsToUpdateIndexes[$i]];
                                if(!isset($updatesArrayByFrom[$fromsToUpdateIndexes[$i]]['rates'][$rateKey][$usageType]['rate'])){
                                    for ($a = 0; $a < count($rate[$usageType]['rate']); $a++) {
                                        $updatesArrayByFrom[$fromsToUpdateIndexes[$i]]['rates'][$rateKey][$usageType]['rate'][] = $rate[$usageType]['rate'][$a];
                                    }
                                }else{
                                    unset($updatesArrayByFrom[$fromsToUpdateIndexes[$i]]['rates'][$rateKey][$usageType]['rate']);
                                    for ($a = 0; $a < count($rate[$usageType]['rate']); $a++) {
                                        $updatesArrayByFrom[$fromsToUpdateIndexes[$i]]['rates'][$rateKey][$usageType]['rate'][] = $rate[$usageType]['rate'][$a];
                                    }
                                }
                            }
                        }
                    }
                    ksort($updatesArrayByFrom);
                    $fromsArray = array_keys($updatesArrayByFrom);
                    for ($j = 0; $j < count($updatesArrayByFrom); $j++) {
                        if ($j == 0) {
                            $from = $date;
                        } else {
                            $from = $fromsArray[$j];
                        }
                        $params['request']['query'] = json_encode(array(
                            '_id' => $updatesArrayByFrom[$fromsArray[$j]]['id'],
                            'effective_date' => $from,
                        ));
                        $params['request']['update'] = json_encode(array('rates' => $updatesArrayByFrom[$fromsArray[$j]]['rates'], 'from' => $from));
                        $params['request']['action'] = 'permanentchange';
                        $params['request']['collection'] = 'plans';
                        $params['collection'] = 'plans';
                        try{
                            $update = new Models_Entity($params);
                            $update->setUpdate(array('rates' => $updatesArrayByFrom[$fromsArray[$j]]['rates'], 'from' => new MongoDate(strtotime($from))));
                            $update->permanentChange();
                        } catch(Exception $ex){
                            $this->setEntityError($planKey, $ex->getMessage());
                            }
                    }
                }
            }
        }
    }

    protected function add_prefix_zone_mccmnc_roamingPrefix_MNPRN_CD_fields() {
        $entityModel = new ConfigModel();
        $category = 'subscribers';
        $data = json_encode(json_decode("{}"));
        $all_subscribers_fields_1 = $entityModel->getFromConfig('subscribers', '{}');
        $all_rates_fields_1 = $entityModel->getFromConfig('rates', '{}');
        $all_plans_fields_1 = $entityModel->getFromConfig('plans', '{}');
        $all_services_fields_1 = $entityModel->getFromConfig('services', '{}');


        $exist_params_prefix = false;
        $exist_zone = false;
        $exist_collection_type = false;
        $exist_mccmnc = false;
        $exist_params_roaming_prefix = false;
        $exist_on_off_network = false;
        $exist_upto_50kb = false;
        $exist_international_call_saver_add_on = false;
        $exist_zone_dest = false;
        $national_rate_exist = false;
        $international_rate_exist = false;
        $roaming_data_exist = false;

        for ($i = 0; $i < count($all_rates_fields_1['fields']); $i++) {
            if ($all_rates_fields_1['fields'][$i]['field_name'] === "params.prefix") {
                $exist_params_prefix = true;
            }
            if ($all_rates_fields_1['fields'][$i]['field_name'] === "params.roaming_prefix") {
                $exist_params_roaming_prefix = true;
            }
            if ($all_rates_fields_1['fields'][$i]['field_name'] === "zone") {
                $exist_zone = true;
            }
            if ($all_rates_fields_1['fields'][$i]['field_name'] === "collection_type") {
                $exist_collection_type = true;
            }
            if ($all_rates_fields_1['fields'][$i]['field_name'] === "params.mccmnc") {
                $exist_mccmnc = true;
            }
            if ($all_rates_fields_1['fields'][$i]['field_name'] === "params.mnprn_cd") {
                $exist_on_off_network = true;
            }
            if ($all_rates_fields_1['fields'][$i]['field_name'] === "params.up_to_50_kb") {
                $exist_upto_50kb = true;
            }
            if ($all_rates_fields_1['fields'][$i]['field_name'] === "params.international_call_saver_add_on") {
                $exist_international_call_saver_add_on = true;
            }
            if ($all_rates_fields_1['fields'][$i]['field_name'] === "params.destination_zone") {
                $exist_zone_dest = true;
            }
            if ($all_rates_fields_1['fields'][$i]['field_name'] === "params.national_rate") {
                $national_rate_exist = true;
            }
            if ($all_rates_fields_1['fields'][$i]['field_name'] === "params.international_rate") {
                $international_rate_exist = true;
            }
            if ($all_rates_fields_1['fields'][$i]['field_name'] === "params.roaming_data") {
                $roaming_data_exist = true;
            }
            }
        $data_to_send_1 = array();
        $data_to_send_1[1]['rates']['fields'] = $all_rates_fields_1['fields'];
        if (!$roaming_data_exist) {
            $prefix_field_1['editable'] = true;
            $prefix_field_1['display'] = true;
            $prefix_field_1['title'] = "Roaming Data";
            $prefix_field_1['field_name'] = "params.roaming_data";
            $prefix_field_1['default_value'] = false;
            $prefix_field_1['type'] = "boolean";
            $prefix_field_1['searchable'] = true;
            array_push($data_to_send_1[1]['rates']['fields'], $prefix_field_1);
        }
        if (!$national_rate_exist) {
            $prefix_field_1['editable'] = true;
            $prefix_field_1['display'] = true;
            $prefix_field_1['title'] = "National rate";
            $prefix_field_1['field_name'] = "params.national_rate";
            $prefix_field_1['default_value'] = false;
            $prefix_field_1['type'] = "boolean";
            $prefix_field_1['searchable'] = true;
            array_push($data_to_send_1[1]['rates']['fields'], $prefix_field_1);
        }
        unset($prefix_field_1);
        if (!$international_rate_exist) {
            $prefix_field_1['editable'] = true;
            $prefix_field_1['display'] = true;
            $prefix_field_1['title'] = "International rate";
            $prefix_field_1['field_name'] = "params.international_rate";
            $prefix_field_1['default_value'] = false;
            $prefix_field_1['type'] = "boolean";
            $prefix_field_1['searchable'] = true;
            array_push($data_to_send_1[1]['rates']['fields'], $prefix_field_1);
        }
        unset($prefix_field_1);
        if (!$exist_params_prefix) {
            $prefix_field_1['editable'] = true;
            $prefix_field_1['display'] = true;
            $prefix_field_1['title'] = "Prefix";
            $prefix_field_1['field_name'] = "params.prefix";
            $prefix_field_1['searchable'] = true;
            array_push($data_to_send_1[1]['rates']['fields'], $prefix_field_1);
        }
        unset($prefix_field_1);
        if (!$exist_zone_dest) {
            $prefix_field_1['editable'] = true;
            $prefix_field_1['display'] = true;
            $prefix_field_1['title'] = "Destination zone";
            $prefix_field_1['field_name'] = "params.destination_zone";
            $prefix_field_1['searchable'] = true;
            array_push($data_to_send_1[1]['rates']['fields'], $prefix_field_1);
        }
        unset($prefix_field_1);
        if (!$exist_upto_50kb) {
            $prefix_field_1['editable'] = true;
            $prefix_field_1['display'] = true;
            $prefix_field_1['title'] = "MMS up to 50 KB";
            $prefix_field_1['field_name'] = "params.up_to_50_kb";
            $prefix_field_1['default_value'] = false;
            $prefix_field_1['type'] = "boolean";
            $prefix_field_1['searchable'] = true;
            array_push($data_to_send_1[1]['rates']['fields'], $prefix_field_1);
        }
        unset($prefix_field_1);
        if (!$exist_international_call_saver_add_on) {
            $prefix_field_1['editable'] = true;
            $prefix_field_1['display'] = true;
            $prefix_field_1['title'] = "International call saver add on";
            $prefix_field_1['field_name'] = "params.international_call_saver_add_on";
            $prefix_field_1['default_value'] = false;
            $prefix_field_1['type'] = "boolean";
            $prefix_field_1['searchable'] = true;
            array_push($data_to_send_1[1]['rates']['fields'], $prefix_field_1);
        }
        unset($prefix_field_1);
        if (!$exist_on_off_network) {
            $prefix_field_1['editable'] = true;
            $prefix_field_1['display'] = true;
            $prefix_field_1['title'] = "MNPRN_CD";
            $prefix_field_1['field_name'] = "params.mnprn_cd";
            $prefix_field_1['searchable'] = true;
            array_push($data_to_send_1[1]['rates']['fields'], $prefix_field_1);
        }
        unset($prefix_field_1);
        if (!$exist_params_roaming_prefix) {
            $prefix_field_1['editable'] = true;
            $prefix_field_1['display'] = true;
            $prefix_field_1['title'] = "Roaming Prefix";
            $prefix_field_1['field_name'] = "params.roaming_prefix";
            $prefix_field_1['searchable'] = true;
            array_push($data_to_send_1[1]['rates']['fields'], $prefix_field_1);
        }
        unset($prefix_field_1);
        if (!$exist_zone) {
            $zone_field_1['editable'] = true;
            $zone_field_1['display'] = true;
            $zone_field_1['title'] = "Zone";
            $zone_field_1['field_name'] = "zone";
            $zone_field_1['searchable'] = true;
            array_push($data_to_send_1[1]['rates']['fields'], $zone_field_1);
        }
        unset($prefix_field_1);
        if (!$exist_collection_type) {
            $collection_type_field_1['select_list'] = true;
            $collection_type_field_1['select_options'] = "post_paid, pre_paid";
            $collection_type_field_1['editable'] = true;
            $collection_type_field_1['display'] = true;
            $collection_type_field_1['title'] = "Collection type";
            $collection_type_field_1['field_name'] = "collection_type";
            $collection_type_field_1['searchable'] = true;
            array_push($data_to_send_1[1]['rates']['fields'], $collection_type_field_1);
        }
        unset($prefix_field_1);
        if (!$exist_mccmnc) {
            $mccmnc_field_1['editable'] = true;
            $mccmnc_field_1['multiple'] = true;
            $mccmnc_field_1['display'] = true;
            $mccmnc_field_1['title'] = "MCCMNC";
            $mccmnc_field_1['field_name'] = "params.mccmnc";
            $mccmnc_field_1['searchable'] = true;
            array_push($data_to_send_1[1]['rates']['fields'], $mccmnc_field_1);
        }
        unset($prefix_field_1);
        $exist_mtn_sid = false;
        for ($i = 0; $i < count($all_subscribers_fields_1['subscriber']['fields']); $i++) {
            if ($all_subscribers_fields_1['subscriber']['fields'][$i]['field_name'] === "sub_number") {
                $exist_mtn_sid = true;
            }
        }

        if (!$exist_mtn_sid) {
            $new_field_1['editable'] = true;
            $new_field_1['display'] = true;
            $new_field_1['title'] = "sub_number";
            $new_field_1['field_name'] = "sub_number";
            $new_field_1['searchable'] = true;
            $data_to_send_1[0]['subscribers']['subscriber']['fields'] = $all_subscribers_fields_1['subscriber']['fields'];
            array_push($data_to_send_1[0]['subscribers']['subscriber']['fields'], $new_field_1);
        }

        $data_to_send_1[0]['subscribers']['types'] = $all_subscribers_fields_1['types'];
        $data_to_send_1[0]['subscribers']['fields'][0] = $all_subscribers_fields_1['fields'][0];
        $data_to_send_1[3]['services']['fields'] = $all_services_fields_1['fields'];
        $data_to_send_1[2]['plans']['fields'] = $all_plans_fields_1['fields'];

        $result = $entityModel->updateConfig("", $data_to_send_1);
    }

    protected function setStatus($status) {
        $this->output['status'] = $status;
    }

    public function postprocess_output() {
        $this->output['created'] = $this->created_counter;
        $this->output['updated'] = $this->updated_counter;
        return $this->output;
    }

    protected function setWarning($key, $warning) {
        $this->output['warnings'][$key] = $warning;
    }
    
    protected function setGeneralError($error){
        $this->output['general_errors'][] = $error;
    }

    protected function setError($key, $error) {
        $this->output['errors'][$key] = $error;
    }
    
    protected function setEntityError($key, $error){
        if(!isset($this->output['imported_entities'][$key])){
            $this->output['imported_entities'][$key] = $error;
        }else{
            if(is_array($this->output['imported_entities'][$key])){
                unset($this->output['imported_entities'][$key]);
                $this->output['imported_entities'][$key] = $error;
            }
        }
    }
    
    protected function setEntityWarning($key, $warning){
        $this->output['imported_entities'][$key] = ['warning' => $warning];
    }

    protected function sendRequest($url, $data) {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($curl);
        return $response;
    }

    protected function PostPaidMobileRates($filesPath = '', $RoamingPrefixesList = '', $mccmnc = '', $plans = '', $ratesCollection = '', $network_operator = '', $CyprusMCCMNC = '', $InternationalPrefAndSubPref = '', $mccmnc_by_zone = '', $RoamingPrefixesList_by_zones = '', $RoamingPrefixesList_by_zones_and_countries = '') {
        $filename = self::RATE_PLANS_POSTPAID_MOBILE;
        $file = fopen($filesPath[$filename] , 'r');
        $to = new MongoDate(strtotime('2119-05-01 00:00:00'));
        $rate = array();
        $rate['description'] = "";
        $rate['rates'] = "";
        $relevantRate = 0;
        $nat_before_roam = 0;
        $cyprusPrefix = array('00357', '357');
        $natVoiceRate = array();
        $natSMSRate = array();
        $natMMSRate = array();
        $natDataRate = array();
        $zone1 = 0;
        $cablenet = 0;
        $mccmncWasFound = 0;
        $roamVoiceZone1 = 0;
        $roamVoiceZone2 = 0;
        $roamVoiceZone3 = 0;
        $roamVoiceZone4 = 0;
        $roamVoiceZone5 = 0;
        $roamVoiceZone6 = 0;
        $roamVoiceZone7 = 0;
        $nationalVoiceOffnet = 0;
        $nationalData = 0;
        $billingIncrementsLine = null;
        $billingIncrementsExist = 0;
        $MTNPlanNameExist = 0;
        $NationalRates = array();
        $alreadyInserted = 0;
        $rates = array();
        $rate = array();
        $currentSection = "";
        $roamVoice = 0;
        $national_interval = 0;
        $usagetype = "";
        $priceCal = array();
        $PostPaidMobilePlans = array();
        $Zone1RatesAlreadyInserted = 0;
        $update_national = 0;
        $rates_index = 0;
        $date_header_line = 0;
        $postPaidDateFlag = 0;
        unset($line);
        while (($line = fgetcsv($file)) !== FALSE) {
            if ($line[0] == "Billing Increments for National Voice Traffic") {
                $billingIncrementsLine = $line;
                $billingIncrementsExist = 1;
            }
            if ($line[1] === "MTN Plan Name") {
                $MTNPlanNameExist = 1;
                for ($a = 2; $a < count($line); $a++) {
                    if (($line[$a] !== "") && !in_array($line[$a], ['from', 'account', 'objectID', 'product label', 'description', 'fis descr', 'management reporting'])) {
                        $PostPaidMobilePlans[$a] = $line[$a];
                    }
                    if ($line[$a] === "MTN Simple") {
                        $rates_index = $a;
                    }
                    if ($line[$a] === "from") {
                        $dateIndex = $a;
                        $postPaidDateFlag = 1;
                    }
					if ($line[$a] === "account") { $accountNumIndex = $a; }
					if ($line[$a] === "objectID") { $objectIDIndex = $a; }
					if ($line[$a] === "product label") { $labelIndex = $a; }
					if ($line[$a] === "description") { $descriotionIndex = $a; }
					if ($line[$a] === "fis descr") { $fis_descrIndex = $a; }
					if ($line[$a] === "management reporting") { $managmentReportingIndex = $a; }
                }
                if($postPaidDateFlag == 0){
                    Billrun_Factory::log()->log('No date column was found in ' . $filename . ' file. "from" header was expected in the header line that starts with "MTN Plan Name". Assume the date is "now", for all of the rates.', Zend_Log::NOTICE);
                }
            }
        }
        if ($billingIncrementsExist === 0) {
            //echo 'no "billing increments" line - didnt find billing icrements at cell 0, at "Rate Plans - Postpaid Mobile 8.1" - die()..' . PHP_EOL;
            $this->setStatus(0);
            $this->setError($filename, $this->errors[0] . ' ' . $filename);
            return -1;
        }
        if ($PostPaidMobilePlans === 0) {
            //echo 'no "MTN Plan Name" line - didnt find MTN Plan Name at cell 1, at "Rate Plans - Postpaid Mobile 8.1" - die()..' . PHP_EOL;
            $this->setStatus(0);
            $this->setError($filename, $this->errors[0] . ' ' . $filename);
            return -1;
        }
        fclose($file);
        unset($file);
        foreach ($PostPaidMobilePlans as $key => $planName) {
            $planKey = $this->getKey($planName);
            $this->plans_to_update_by_date[$planKey] = array();
        }
        $file = fopen($filesPath[$filename], 'r');
        while (($line = fgetcsv($file)) !== FALSE) {
            $line_0 = $line[0];
            $line_1 = $line[1];
            $line_2 = $line[2];
            $line_3 = $line[3];
            $line_4 = $line[4];
            $line_5 = $line[5];
            $line_6 = $line[6];
            $line_7 = $line[7];
            $line_8 = $line[8];
            $line_9 = $line[9];
            $line_10 = $line[10];
            $line_11 = $line[11];
            $line_12 = $line[12];
            $line_13 = $line[13];

            unset($rate);
            if ($line_0 === $this->NewSection($line_0)) {
                $currentSection = $line_0;
                continue 1;
            }
            if ($line_0 === $this->ZoneSection($line_0)) {
                $zone = $this->getTheZone($line_0);
                $key = $this->getTheZoneKey($line_0);
                $currentSection = "zones";
                continue 1;
            }
            if (($line_0 == "") && ($line_1 == "") && ($line_2 == "")) {
                continue 1;
            }
            switch ($currentSection) {
                case "National traffic":
                    $nat_before_roam = 1;
                    if ($line_0 == "Billing Increments for National Voice Traffic") {
                        continue 1;
                    }
                    if ($line_0 == "Nat'l Voice Off-Net") {
                        $nationalVoiceOffnet = 1;
                    }
                    if ($line_0 == "Nat'l Data (Gb)") {
                        $nationalData = 1;
                    }
                    if (($nationalVoiceOffnet == 1) && ($line[1] != "")) {
                        $rate['description'] = 'National Voice Off-Net_' . $line_1;
                    }
                    if (($nationalData == 1) && ($line_1 != "")) {
                        $rate['description'] = 'National Data (Gb)_' . $line_1;
                    }
                    if (($line_1 == "In Bundle") && ($nationalData == 1)) {
                        break;
                    }
                    if (($line_0 == "Nat'l Voice On-Net") || ($line_0 == "Nat'l Voice Fix-Net")) {
                        $rate['description'] = $line_0 . '_' . $line_1;
                    }
                    if ($line_1 == "") {
                        $rate['description'] = $line_0;
                    }
                    if ($line_0 === "Nat'l Voice On-Net") {
                        for ($r = 0; $r < count($network_operator['ON NET']); $r++) {
                            $rate['params']['mnprn_cd'][] = $network_operator['ON NET'][$r];
                        }
                    }
                    if (($line_0 === "Nat'l Voice Off-Net") && ($line_1 === "Cyta")) {
                        for ($r = 0; $r < count($network_operator['cyta']); $r++) {
                            $rate['params']['mnprn_cd'][] = $network_operator['cyta'][$r];
                        }
                    }
                    if (($line_0 === "Nat'l Voice Off-Net") && ($line_1 === "Primetel")) {
                        for ($r = 0; $r < count($network_operator['primetel']); $r++) {
                            $rate['params']['mnprn_cd'][] = $network_operator['primetel'][$r];
                        }
                    }
                    if ($line_1 === "Cablenet") {
                        for ($r = 0; $r < count($network_operator['cablenet']); $r++) {
                            $rate['params']['mnprn_cd'][] = $network_operator['cablenet'][$r];
                            $rate['description'] = 'National Voice Off-Net_Cablenet';
                        }
                    }
                    if ($line_0 === "Nat'l SMS On-Net") {
                        for ($r = 0; $r < count($network_operator['ON NET']); $r++) {
                            $rate['params']['mnprn_cd'][] = $network_operator['ON NET'][$r];
                        }
                    }
                    if ($line_0 === "Nat'l SMS Off-Net") {
                        for ($r = 0; $r < count($network_operator['cyta']); $r++) {
                            $rate['params']['mnprn_cd'][] = $network_operator['cyta'][$r];
                        }
                        for ($r = 0; $r < count($network_operator['primetel']); $r++) {
                            $rate['params']['mnprn_cd'][] = $network_operator['primetel'][$r];
                        }
                    }
                    if ($line_0 === "Nat'l MMS - charge up to 50KB") {
                        $rate['params']['up_to_50_kb'] = true;
                    }
                    if ($line_0 === "Nat'l MMS - charge over 50KB") {
                        $rate['params']['up_to_50_kb'] = false;
                    }
					$rate['from'] = $from = date(Billrun_Base::base_datetimeformat, time());
                    if($postPaidDateFlag == 1 && preg_match(self::DATE_REGEX, $line[$dateIndex])){
                       $rate['from'] = $from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
                    }
					$rate['epic_description'] = !empty($line[$descriotionIndex]) ? $line[$descriotionIndex] : $rate['description'];
					$rate['key'] = $this->getKey($rate['description']);
                    $rate['vatable'] = true;
                    $rate['pricing_method'] = "tiered";
                    $rate['tariff_category'] = "retail";
                    $rate['add_to_retail'] = true;
                    $rate['creation_time'] = $from;                                        
                    $rate['to'] = $to;
					$rate['account_num'] = !empty($line[$accountNumIndex]) ? $line[$accountNumIndex] : "";
					$rate['object_id'] = !empty($line[$objectIDIndex]) ? $line[$objectIDIndex] : "";
					$rate['label'] = !empty($line[$labelIndex]) ? $line[$labelIndex] : "";
					$rate['fis_descr'] = !empty($line[$fis_descrIndex]) ? $line[$fis_descrIndex] : "";
					$rate['management_reporting'] = !empty($line[$managmentReportingIndex]) ? $line[$managmentReportingIndex] : "";
                    $rate['params']['national_rate'] = true;
                    $rate['params']['international_rate'] = false;

                    for ($a = 0; $a < count($CyprusMCCMNC); $a++) {
                        $rate['params']['mccmnc'][] = $CyprusMCCMNC[$a];
                    }
                    $zone1 = 0;
                    for ($a = 0; $a < count($cyprusPrefix); $a++) {
                        $rate['params']['prefix'] = $cyprusPrefix[$a];
                    }

                    $this->NationalInsertRateToPlans($line, $rate, $zone1, $billingIncrementsLine, $from, $PostPaidMobilePlans);
                    $priceCal = $this->getPricedRate($line[$rates_index], $line, $rates_index);
                    if ($priceCal !== -1) {
                        if (strpos($line_0, "SMS") !== false) {
                            for ($r = 0; $r < count($priceCal['rates']['sms']['BASE']['rate']); $r++) {
                                $rate['rates']['local_sms']['BASE']['rate'][] = $priceCal['rates']['sms']['BASE']['rate'][$r];
                            }
                            $natSMSRate[] = $rate;
                        } else {
                            if (strpos($line_0, "MMS") !== false) {
                                for ($r = 0; $r < count($priceCal['rates']['mms']['BASE']['rate']); $r++) {
                                    $rate['rates']['mms']['BASE']['rate'][] = $priceCal['rates']['mms']['BASE']['rate'][$r];
                                }
                                $natMMSRate[] = $rate;
                            } else {
                                if ($nationalData != 1) {
                                    $pricing = $this->getBillingIncrements($billingIncrementsLine[$rates_index]);
                                    for ($r = 0; $r < count($priceCal['rates']['call']['BASE']['rate']); $r++) {
                                        $priceCal['rates']['call']['BASE']['rate'][$r]['interval'] = (int) $pricing['amount'];
                                        $priceCal['rates']['call']['BASE']['rate'][$r]['uom_display']['range'] = $pricing['interval'];
                                        $priceCal['rates']['call']['BASE']['rate'][$r]['uom_display']['interval'] = $pricing['interval'];
                                        $rate['rates']['local_call']['BASE']['rate'][] = $priceCal['rates']['call']['BASE']['rate'][$r];
                                    }
                                    $natVoiceRate[] = $rate;
                                } else {
                                    for ($r = 0; $r < count($priceCal['rates']['data']['BASE']['rate']); $r++) {
                                        $rate['rates']['data']['BASE']['rate'][] = $priceCal['rates']['data']['BASE']['rate'][$r];
                                    }
                                    $rate['params']['roaming_data'] = false;
                                }
                            }
                        }
                    } else {
                        //echo 'The price is -, so ' . $rate['description'] . ' wasnt inserted, and wasnt overriden in any plan' . PHP_EOL;
                        $this->setEntityError($rate['key'], $this->error[2]);
                        continue 1;
                    }
                    $NationalRates[] = $rate;
                    $rates[] = $rate;
                    $this->rates_updateOrcreate[$rate['key']] = $rate;
                    $this->rates_keys_and_dates[$rate['key']] = $from;
                    $relevantRate = 0;
                    unset($rate);
                    break;
                case "International Rates":
                    if (strpos($line[$rates_index], "See") !== false) {
                        continue 1;
                    }
                    if (strpos($line[$rates_index], "Allowance") !== false) {
                        continue 1;
                    }
                    if (strpos($line[$rates_index], "International") != 0) {
                        $this->setError($filename, 'At International Rates - line doesnt start with "International"');
                    }
                    if ($line_0 === "International SMS") {
                        foreach ($InternationalPrefAndSubPref as $InterKey => $InterVal) {
                            for ($i = 0; $i < count($InterVal['prefix']); $i++) {
                                $rate['params']['prefix'][] = $InterVal['prefix'][$i];
                            }
                        }
                    }
                    if ($line_0 === "International MMS - charge up to 50KB") {
                        $rate['params']['up_to_50_kb'] = true;
                        foreach ($InternationalPrefAndSubPref as $InterKey => $InterVal) {
                            for ($i = 0; $i < count($InterVal['prefix']); $i++) {
                                $rate['params']['prefix'][] = $InterVal['prefix'][$i];
                            }
                        }
                    }
                    if ($line_0 === "International MMS - charge over 50KB") {
                        $rate['params']['up_to_50_kb'] = false;
                        foreach ($InternationalPrefAndSubPref as $InterKey => $InterVal) {
                            for ($i = 0; $i < count($InterVal['prefix']); $i++) {
                                $rate['params']['prefix'][] = $InterVal['prefix'][$i];
                            }
                        }
                    }
					$rate['from'] = $from = date(Billrun_Base::base_datetimeformat, time());
                    if($postPaidDateFlag == 1 && preg_match(self::DATE_REGEX, $line[$dateIndex])){
                       $rate['from'] = $from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
                    }
                    $rate['description'] = $line_0;
					$rate['epic_description'] = !empty($line[$descriotionIndex]) ? $line[$descriotionIndex] : $rate['description'];
					$rate['key'] = $this->getKey($line_0);
                    $rate['vatable'] = true;
                    $rate['pricing_method'] = "tiered";
                    $rate['tariff_category'] = "retail";
                    $rate['add_to_retail'] = true;
                    $rate['creation_time'] = $from;
                    $rate['to'] = $to;
					$rate['account_num'] = !empty($line[$accountNumIndex]) ? $line[$accountNumIndex] : "";
					$rate['object_id'] = !empty($line[$objectIDIndex]) ? $line[$objectIDIndex] : "";
					$rate['label'] = !empty($line[$labelIndex]) ? $line[$labelIndex] : "";
					$rate['fis_descr'] = !empty($line[$fis_descrIndex]) ? $line[$fis_descrIndex] : "";
					$rate['management_reporting'] = !empty($line[$managmentReportingIndex]) ? $line[$managmentReportingIndex] : "";
                    $rate['params']['international_rate'] = true;
                    $rate['params']['national_rate'] = false;

                    for ($a = 0; $a < count($CyprusMCCMNC); $a++) {
                        $rate['params']['mccmnc'][] = $CyprusMCCMNC[$a];
                    }
                    $zone1 = 0;
                    $this->InsertRateToPlans($line, $rate, $zone1, $PostPaidMobilePlans, $from);
                    $relevantRate = 1;
                    $priceCal = $this->getPricedRate($line[$rates_index], $line, $rates_index);
                    if ($priceCal !== -1) {
                        if (strpos($line_0, "SMS") !== false) {
                            for ($r = 0; $r < count($priceCal['rates']['sms']['BASE']['rate']); $r++) {
                                $rate['rates']['local_sms']['BASE']['rate'][] = $priceCal['rates']['sms']['BASE']['rate'][$r];
                            }
                        }
                        if (strpos($line_0, "MMS") !== false) {
                            for ($r = 0; $r < count($priceCal['rates']['mms']['BASE']['rate']); $r++) {
                                $rate['rates']['mms']['BASE']['rate'][] = $priceCal['rates']['mms']['BASE']['rate'][$r];
                            }
                        }
                    } else {
                        $this->setEntityError($rate['key'], $this->error[2]);
                        continue 1;
                    }
                    $rates[] = $rate;
                    $this->rates_updateOrcreate[$rate['key']] = $rate;
                    $this->rates_keys_and_dates[$rate['key']] = $from;
                    unset($rate);
                    break;
                case "Premium Rates":
                    break;
                case "Roaming traffic - Zone 1 (ROAM LIKE HOME)":
                    if ($Zone1RatesAlreadyInserted == 0) {
                        $returnedRates = $this->Zone1Rates($from, $to, $cyprusPrefix, $mccmnc, $RoamingPrefixesList, $filesPath);
                        $Zone1RatesAlreadyInserted = 1;
                    }
                    break;
                case "zones" :
                    $relevantRate = 0;
                    $alreadyInserted = 0;
                    $mccmncWasFound = 0;
                    if ($line_0 === "Roam Voice") {
                        $roamVoice = 1;
                    }
                    if (($roamVoice == 1) && ($line_1 != "")) {
                        if ($line_1 === "Local") {
							$rate['from'] = $from = date(Billrun_Base::base_datetimeformat, time());
                            if($postPaidDateFlag == 1 && preg_match(self::DATE_REGEX, $line[$dateIndex])){
                                $rate['from'] = $from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
                            }
                            $rate['description'] = !empty($line[$descriotionIndex]) ? $line[$descriotionIndex] : 'Local Roaming Voice zone ' . $key;
                            $rate['epic_description'] = !empty($line[$descriotionIndex]) ? $line[$descriotionIndex] : $rate['description'];
							$rate['key'] = $this->getKey(rtrim($rate['description']));
                            $rate['vatable'] = true;
                            $rate['pricing_method'] = "tiered";
                            $rate['tariff_category'] = "retail";
                            $rate['add_to_retail'] = true;
                            $rate['creation_time'] = $from;
                            $rate['to'] = $to;
                            $rate['zone'] = $zone;
							$rate['account_num'] = !empty($line[$accountNumIndex]) ? $line[$accountNumIndex] : "";
							$rate['object_id'] = !empty($line[$objectIDIndex]) ? $line[$objectIDIndex] : "";
							$rate['label'] = !empty($line[$labelIndex]) ? $line[$labelIndex] : "";
							$rate['fis_descr'] = !empty($line[$fis_descrIndex]) ? $line[$fis_descrIndex] : "";
							$rate['management_reporting'] = !empty($line[$managmentReportingIndex]) ? $line[$managmentReportingIndex] : "";
                            $rate['params']['national_rate'] = false;
                            $rate['params']['international_rate'] = false;
                            $relevantRate = 1;
                            if (isset($mccmnc_by_zone[$zone])) {
                                for ($v = 0; $v < count($mccmnc_by_zone[$zone]); $v++) {
                                    $rate['params']['mccmnc'][] = $mccmnc_by_zone[$zone][$v];
                                }
                            }
                            if (isset($RoamingPrefixesList_by_zones[$zone])) {
                                for ($v = 0; $v < count($RoamingPrefixesList_by_zones[$zone]); $v++) {
                                    $rate['params']['roaming_prefix'][] = $RoamingPrefixesList_by_zones[$zone][$v];
                                }
                            }
                            $priceCal = $this->getPricedRate($line[$rates_index], $line, $rates_index);
                            if ($priceCal !== -1) {
                                for ($r = 0; $r < count($priceCal['rates']['call']['BASE']['rate']); $r++) {
                                    $rate['rates']['roaming_call']['BASE']['rate'][] = $priceCal['rates']['call']['BASE']['rate'][$r];
                                }
                            } else {
                                $this->setStatus(2);
                                $this->setEntityError($rate['key'], $this->errors[2]);
                                $relevantRate = 0;
                                //return -1;
                            }
                            $zone1 = 0;
                            $plansPostPaid = $this->InsertRateToPlans($line, $rate, $zone1, $PostPaidMobilePlans, $from);
                            if ($relevantRate == 1) {
                                //echo 'Inserting rate ' . $rate['description'] . ' from ' . $filename . PHP_EOL;
                                $this->rates_updateOrcreate[$rate['key']] = $rate;
                                $this->rates_keys_and_dates[$rate['key']] = $from;
                                $rates[] = $rate;
                                unset($rate);
                            }
                        }
                        if ($line_1 === 'Back to Cyprus') {
							$rate['from'] = $from = date(Billrun_Base::base_datetimeformat, time());
                            if($postPaidDateFlag == 1 && preg_match(self::DATE_REGEX, $line[$dateIndex])){
                                $rate['from'] = $from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
                            }
                            $rate['description'] = 'Back to Cyprus Roaming Voice '/* . $key . */ . ' zone ' . $key;
                            $rate['epic_description'] = !empty($line[$descriotionIndex]) ? $line[$descriotionIndex] : $rate['description'];
							$rate['key'] = $this->getKey(rtrim($rate['description']));
                            $rate['vatable'] = true;
                            $rate['pricing_method'] = "tiered";
                            $rate['tariff_category'] = "retail";
                            $rate['add_to_retail'] = true;
                            $rate['creation_time'] = $from;
                            $rate['to'] = $to;
                            $rate['zone'] = $zone;
							$rate['account_num'] = !empty($line[$accountNumIndex]) ? $line[$accountNumIndex] : "";
							$rate['object_id'] = !empty($line[$objectIDIndex]) ? $line[$objectIDIndex] : "";
							$rate['label'] = !empty($line[$labelIndex]) ? $line[$labelIndex] : "";
							$rate['fis_descr'] = !empty($line[$fis_descrIndex]) ? $line[$fis_descrIndex] : "";
							$rate['management_reporting'] = !empty($line[$managmentReportingIndex]) ? $line[$managmentReportingIndex] : "";
                            $rate['params']['national_rate'] = false;
                            $rate['params']['international_rate'] = false;
                            $relevantRate = 1;
                            for ($a = 0; $a < count($cyprusPrefix); $a++) {
                                $rate['params']['roaming_prefix'][] = $cyprusPrefix[$a];
                            }
                            $zone1 = 0;
                            $plansPostPaid = $this->InsertRateToPlans($line, $rate, $zone1, $PostPaidMobilePlans, $from);
                            $priceCal = $this->getPricedRate($line[$rates_index], $line, $rates_index);
                            if ($priceCal !== -1) {
                                for ($r = 0; $r < count($priceCal['rates']['call']['BASE']['rate']); $r++) {
                                    $rate['rates']['roaming_call']['BASE']['rate'][] = $priceCal['rates']['call']['BASE']['rate'][$r];
                                }
                            } else {
                                //echo 'The price is -, so ' . $rate['description'] . ' wasnt inserted, and wasnt overriden in any plan' . PHP_EOL;
                                $this->setStatus(0);
                                $this->setEntityError($rate['key'], $this->errors[0]);
                            }
                            if (isset($mccmnc_by_zone[$zone])) {
                                for ($v = 0; $v < count($mccmnc_by_zone[$zone]); $v++) {
                                    $rate['params']['mccmnc'][] = $mccmnc_by_zone[$zone][$v];
                                }
                            }
                            if ($relevantRate == 1) {
                                //echo 'Inserting rate ' . $rate['description'] . ' from ' . $filename . PHP_EOL;
                                $this->rates_updateOrcreate[$rate['key']] = $rate;
                                $this->rates_keys_and_dates[$rate['key']] = $from;
                                $rates[] = $rate;
                                unset($rate);
                            }
                        }
                        if ($line_1 === 'To EU') {
							$rate['from'] = $from = date(Billrun_Base::base_datetimeformat, time());
                            if($postPaidDateFlag == 1 && preg_match(self::DATE_REGEX, $line[$dateIndex])){
                                $rate['from'] = $from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
							}
                            $rate['description'] = 'Roaming Voice To EU zone ' . $key;
                            $rate['epic_description'] = !empty($line[$descriotionIndex]) ? $line[$descriotionIndex] : $rate['description'];
							$rate['key'] = $this->getKey(rtrim($rate['description']));
                            $rate['vatable'] = true;
                            $rate['pricing_method'] = "tiered";
                            $rate['tariff_category'] = "retail";
                            $rate['add_to_retail'] = true;
                            $rate['creation_time'] = $from;
                            $rate['to'] = $to;
                            $rate['zone'] = $zone;
							$rate['account_num'] = !empty($line[$accountNumIndex]) ? $line[$accountNumIndex] : "";
							$rate['object_id'] = !empty($line[$objectIDIndex]) ? $line[$objectIDIndex] : "";
							$rate['label'] = !empty($line[$labelIndex]) ? $line[$labelIndex] : "";
							$rate['fis_descr'] = !empty($line[$fis_descrIndex]) ? $line[$fis_descrIndex] : "";
							$rate['management_reporting'] = !empty($line[$managmentReportingIndex]) ? $line[$managmentReportingIndex] : "";
                            $rate['params']['national_rate'] = false;
                            $rate['params']['international_rate'] = false;
                            $relevantRate = 1;
                            if (isset($mccmnc_by_zone[$zone])) {
                                for ($v = 0; $v < count($mccmnc_by_zone[$zone]); $v++) {
                                    $rate['params']['mccmnc'][] = $mccmnc_by_zone[$zone][$v];
                                }
                            }
                            if (isset($RoamingPrefixesList_by_zones["Zone1"])) {
                                for ($v = 0; $v < count($RoamingPrefixesList_by_zones[$zone]); $v++) {
                                    $rate['params']['roaming_prefix'][] = $RoamingPrefixesList_by_zones[$zone][$v];
                                }
                            }


                            $priceCal = $this->getPricedRate($line[$rates_index], $line, $rates_index);
                            if ($priceCal !== -1) {
                                for ($r = 0; $r < count($priceCal['rates']['call']['BASE']['rate']); $r++) {
                                    $rate['rates']['call']['BASE']['rate'][] = $priceCal['rates']['call']['BASE']['rate'][$r];
                                }
                            } else {
                                $this->setStatus(0);
                                $this->setEntityError($rate['key'], $this->errors[0]);
                            }
                            $zone1 = 0;
                            $plansPostPaid = $this->InsertRateToPlans($line, $rate, $zone1, $PostPaidMobilePlans, $from);
                            if ($relevantRate == 1) {
                                //echo 'Inserting rate ' . $rate['description'] . ' from ' . $filename . PHP_EOL;
                                $this->rates_updateOrcreate[$rate['key']] = $rate;
                                $this->rates_keys_and_dates[$rate['key']] = $from;
                                $rates[] = $rate;
                                unset($rate);
                            }
                        }
                        if ($line_1 === 'To Rest of the World') {
							$rate['from'] = $from = date(Billrun_Base::base_datetimeformat, time());
                            if($postPaidDateFlag == 1  && preg_match(self::DATE_REGEX, $line[$dateIndex])){
                                $rate['from'] = $from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
							}
                            $rate['description'] = 'Roaming Voice To Rest of the World zone ' . $key;
                            $rate['epic_description'] = !empty($line[$descriotionIndex]) ? $line[$descriotionIndex] : $rate['description'];
							$rate['key'] = $this->getKey(rtrim($rate['description']));
                            $rate['vatable'] = true;
                            $rate['pricing_method'] = "tiered";
                            $rate['tariff_category'] = "retail";
                            $rate['add_to_retail'] = true;
                            $rate['creation_time'] = $from;
                            $rate['to'] = $to;
                            $rate['zone'] = $zone;
							$rate['account_num'] = !empty($line[$accountNumIndex]) ? $line[$accountNumIndex] : "";
							$rate['object_id'] = !empty($line[$objectIDIndex]) ? $line[$objectIDIndex] : "";
							$rate['label'] = !empty($line[$labelIndex]) ? $line[$labelIndex] : "";
							$rate['fis_descr'] = !empty($line[$fis_descrIndex]) ? $line[$fis_descrIndex] : "";
							$rate['management_reporting'] = !empty($line[$managmentReportingIndex]) ? $line[$managmentReportingIndex] : "";
                            $rate['params']['national_rate'] = false;
                            $rate['params']['international_rate'] = false;
                            $relevantRate = 1;

                            if (isset($mccmnc_by_zone[$zone])) {
                                for ($v = 0; $v < count($mccmnc_by_zone[$zone]); $v++) {
                                    $rate['params']['mccmnc'][] = $mccmnc_by_zone[$zone][$v];
                                }
                            }
                            foreach ($RoamingPrefixesList as $keyCountry => $valCountry) {
                                if (array_key_exists("the_zone", $valCountry)) {
                                    if (($valCountry['the_zone'] !== "Zone1") && ($valCountry['the_zone'] !== $zone) && ($valCountry['prefix'] !== $cyprusPrefix[0]) && ($valCountry['prefix'] !== $cyprusPrefix[1])) {
                                        for ($c = 0; $c < count($valCountry['prefix']); $c++) {
                                            $rate['params']['roaming_prefix'][] = $valCountry['prefix'][$c];
                                        }
                                    }
                                }
                            }
                            $priceCal = $this->getPricedRate($line[$rates_index], $line, $rates_index);
                            if ($priceCal !== -1) {
                                for ($r = 0; $r < count($priceCal['rates']['call']['BASE']['rate']); $r++) {
                                    $rate['rates']['call']['BASE']['rate'][] = $priceCal['rates']['call']['BASE']['rate'][$r];
                                }
                            } else {
                                $this->setStatus(0);
                                $this->setEntityError($rate['key'], $this->errors[0]);
                            }
                            $country = $this->getDestination($keyCountry);
                            $zone1 = 0;
                            $plansPostPaid = $this->InsertRateToPlans($line, $rate, $zone1, $PostPaidMobilePlans, $from);
                            if ($relevantRate == 1) {
                                //echo 'Inserting rate ' . $rate['description'] . ' from ' . $filename . PHP_EOL;
                                $this->rates_updateOrcreate[$rate['key']] = $rate;
                                $this->rates_keys_and_dates[$rate['key']] = $from;
                                $rates[] = $rate;
                                unset($rate);
                            }
                        }
                        if ($line_1 === 'Receiving') {
                            $countries_in_zone = array_keys($RoamingPrefixesList_by_zones_and_countries[$zone]);
							$defaultFrom = date(Billrun_Base::base_datetimeformat, time());
                            for ($z = 0; $z < count($countries_in_zone); $z++) {
                                if($postPaidDateFlag == 1  && preg_match(self::DATE_REGEX, $line[$dateIndex])){
                                    $rate['from'] = $from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
                                }else{
                                    $rate['from'] = $from = $defaultFrom;
                                }
                                $mccmncWasFound = 0;
                                $rate['description'] = 'Receiving Roaming Voice ' . $countries_in_zone[$z] . ' zone ' . $key;
								$rate['epic_description'] = !empty($line[$descriotionIndex]) ? $line[$descriotionIndex] : $rate['description'];
								$rate['key'] = $this->getKey(rtrim($rate['description']));
                                $rate['vatable'] = true;
                                $rate['pricing_method'] = "tiered";
                                $rate['tariff_category'] = "retail";
                                $rate['add_to_retail'] = true;
                                $rate['creation_time'] = $from;
                                $rate['to'] = $to;
                                $rate['zone'] = $zone;
								$rate['account_num'] = !empty($line[$accountNumIndex]) ? $line[$accountNumIndex] : "";
								$rate['object_id'] = !empty($line[$objectIDIndex]) ? $line[$objectIDIndex] : "";
								$rate['label'] = !empty($line[$labelIndex]) ? $line[$labelIndex] : "";
								$rate['fis_descr'] = !empty($line[$fis_descrIndex]) ? $line[$fis_descrIndex] : "";
								$rate['management_reporting'] = !empty($line[$managmentReportingIndex]) ? $line[$managmentReportingIndex] : "";
                                $rate['params']['national_rate'] = false;
                                $rate['params']['international_rate'] = false;
                                $relevantRate = 1;
                                $country = $countries_in_zone[$z];
                                if (isset($mccmnc[$country])) {
                                    $mccmncWasFound = 1;
                                    for ($v = 0; $v < count($mccmnc[$country]['mccmnc']); $v++) {
                                        $rate['params']['mccmnc'][] = $mccmnc[$country]['mccmnc'][$v];
                                    }
                                } else {
                                    $this->setEntityWarning($rate['key'], 'Didnt find mccmnc for ' . $country . ', because it is written differently in "locationMCCMNC" file, or it doesnt exist there.');
                                }
                                $priceCal = $this->getPricedRate($line[$rates_index], $line, $rates_index);
                                if ($priceCal !== -1) {
                                    for ($r = 0; $r < count($priceCal['rates']['call']['BASE']['rate']); $r++) {
                                        $rate['rates']['incoming call']['BASE']['rate'][] = $priceCal['rates']['call']['BASE']['rate'][$r];
                                    }
                                } else {
                                    $this->setStatus(0);
                                    $this->setEntityError($rate['key'], $this->errors[0]);
                                }
                                $zone1 = 0;
                                $plansPostPaid = $this->InsertRateToPlans($line, $rate, $zone1, $PostPaidMobilePlans, $from);
                                if ($relevantRate == 1) {
                                    //echo 'Inserting rate ' . $rate['description'] . ' from ' . $filename . PHP_EOL;
                                    $this->rates_updateOrcreate[$rate['key']] = $rate;
                                    $this->rates_keys_and_dates[$rate['key']] = $from;
                                    $rates[] = $rate;
                                    unset($rate);
                                }
                            }
                        }
                    } else {
                        if ($line_0 === "FUP Roaming Allowance") {
                            continue 1;
                        }
						$rate['from'] = $from = date(Billrun_Base::base_datetimeformat, time());
                        if($postPaidDateFlag == 1 && preg_match(self::DATE_REGEX, $line[$dateIndex])){
                            $rate['from'] = $from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
						}
                        $rate['description'] = !empty($line[$descriotionIndex]) ? $line[$descriotionIndex] : 'Zone ' . $key . ' ' . $line_0;
                        $rate['epic_description'] = !empty($line[$descriotionIndex]) ? $line[$descriotionIndex] : $rate['description'];
						$rate['key'] = $this->getKey(rtrim($rate['description']));
                        $rate['vatable'] = true;
                        $rate['pricing_method'] = "tiered";
                        $rate['tariff_category'] = "retail";
                        $rate['add_to_retail'] = true;
                        $rate['creation_time'] = $from;
                        $rate['to'] = $to;
                        $rate['zone'] = $zone;
						$rate['account_num'] = !empty($line[$accountNumIndex]) ? $line[$accountNumIndex] : "";
						$rate['object_id'] = !empty($line[$objectIDIndex]) ? $line[$objectIDIndex] : "";
						$rate['label'] = !empty($line[$labelIndex]) ? $line[$labelIndex] : "";
						$rate['fis_descr'] = !empty($line[$fis_descrIndex]) ? $line[$fis_descrIndex] : "";
						$rate['management_reporting'] = !empty($line[$managmentReportingIndex]) ? $line[$managmentReportingIndex] : "";
                        $rate['params']['national_rate'] = false;
                        $rate['params']['international_rate'] = false;
                        $relevantRate = 1;
                        $zone1 = 0;
                        $plansPostPaid = $this->InsertRateToPlans($line, $rate, $zone1, $PostPaidMobilePlans, $from);

                        if (isset($mccmnc_by_zone[$zone])) {
                            for ($v = 0; $v < count($mccmnc_by_zone[$zone]); $v++) {
                                $rate['params']['mccmnc'][] = $mccmnc_by_zone[$zone][$v];
                            }
                        }
                        $priceCal = $this->getPricedRate($line[$rates_index], $line, $rates_index);
                        if ($priceCal !== -1) {
                            if (strpos($line_0, "SMS") !== false) {
                                for ($r = 0; $r < count($priceCal['rates']['sms']['BASE']['rate']); $r++) {
                                    $rate['rates']['roaming_sms']['BASE']['rate'][] = $priceCal['rates']['sms']['BASE']['rate'][$r];
                                }
                            } else {
                                if (strpos($line_0, "Data") !== false) {
                                    for ($r = 0; $r < count($priceCal['rates']['data']['BASE']['rate']); $r++) {
                                        $rate['rates']['data']['BASE']['rate'][] = $priceCal['rates']['data']['BASE']['rate'][$r];
                                    }
                                    $rate['params']['roaming_data'] = true;
                                }
                            }
                        } else {
                            $this->setStatus(0);
                            $this->setEntityError($rate['key'], $this->errors[0]);
                        }
                        //      }
                        //   }
                        if ($relevantRate == 1) {
                            $this->rates_updateOrcreate[$rate['key']] = $rate;
                            $this->rates_keys_and_dates[$rate['key']] = $from;
                            unset($rate);
                        }
                    }
                    break;
                case "All Roaming Traffic":
                    break;
                case "Other Call Charges":
                    $rate['description'] = $line_0;
                    $rate['epic_description'] = !empty($line[$descriotionIndex]) ? $line[$descriotionIndex] : $rate['description'];
					if ($line_0 == "") {
                        continue;
                    }
					$rate['from'] = $from = date(Billrun_Base::base_datetimeformat, time());
                    if($postPaidDateFlag == 1 && preg_match(self::DATE_REGEX, $line[$dateIndex])){
                        $rate['from'] = $from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
					}
                    $rate['key'] = $this->getKey(trim($line_0));
                    $rate['vatable'] = true;
                    $rate['pricing_method'] = "tiered";
                    $rate['tariff_category'] = "retail";
                    $rate['add_to_retail'] = true;
                    $rate['creation_time'] = $from;
                    $rate['to'] = $to;
					$rate['account_num'] = !empty($line[$accountNumIndex]) ? $line[$accountNumIndex] : "";
					$rate['object_id'] = !empty($line[$objectIDIndex]) ? $line[$objectIDIndex] : "";
					$rate['label'] = !empty($line[$labelIndex]) ? $line[$labelIndex] : "";
					$rate['fis_descr'] = !empty($line[$fis_descrIndex]) ? $line[$fis_descrIndex] : "";
					$rate['management_reporting'] = !empty($line[$managmentReportingIndex]) ? $line[$managmentReportingIndex] : "";
                    $rate['params']['national_rate'] = true;
                    $rate['params']['international_rate'] = false;
                    $rate['params']['prefix'] = $this->getPrefix($rate['description']);
                    $zone1 = 0;
                    $plansPostPaid = $this->InsertRateToPlans($line, $rate, $zone1, $PostPaidMobilePlans, $from);
                    $priceCal = $this->getPricedRate($line[$rates_index], $line, $rates_index);
                    if ($priceCal !== -1) {
                        for ($r = 0; $r < count($priceCal['rates']['call']['BASE']['rate']); $r++) {
                            $rate['rates']['local_call']['BASE']['rate'][] = $priceCal['rates']['call']['BASE']['rate'][$r];
                        }
                    } else {
                        $this->setStatus(0);
                        $this->setEntityError($rate['key'], $this->errors[0]);
                    }
                    //echo 'Inserting rate ' . $rate['description'] . ' from ' . $filename . PHP_EOL;
                    $rates[] = $rate;
                    $this->rates_updateOrcreate[$rate['key']] = $rate;
                    $this->rates_keys_and_dates[$rate['key']] = $from;
                    unset($rate);
                    $relevantRate = 0;
                    break;
                case "Inclusive Units" || "Contract Length" || "4G Enabled" || "WIFI Calling Enabled" || "Comments" || "Recurring Charge" || "Monthly Commitment":
                    break;
                case "":
                    break;
                default:
                    $this->setError($filename, "unknown section " . $currentSection);
            }
        }
        fclose($file);
        return $rates;
    }

    protected function getKey($str) {
        $unwanted_array = array('Š' => 'S', 'š' => 's', 'Ž' => 'Z', 'ž' => 'z', 'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'A', 'Ç' => 'C', 'È' => 'E', 'É' => 'E',
            'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ù' => 'U',
            'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'B', 'ß' => 'Ss', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'a', 'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'o', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ý' => 'y', 'þ' => 'b', 'ÿ' => 'y', '(' => "", ')' => "");
        $str = strtr($str, $unwanted_array);
        $str = strtoupper(str_replace('&', '_and_', $str));
        $str = strtoupper(str_replace('+', '_', $str));
        $str = strtoupper(str_replace('(', '_', str_replace("'", "", str_replace('-', '_', (str_replace('__', '_', str_replace(' ', '_', str_replace('.', '_', str_replace('$', '_', str_replace(',', '_', str_replace('‘', '_', $str)))))))))));
        $str = strtoupper(str_replace('__', '_', $str));
        $str = strtoupper(str_replace('/', '_', $str));
        $str = strtoupper(str_replace('*', '', $str));
        $str = strtoupper(str_replace('&', '_and_', $str));
        $str = strtoupper(str_replace('__', '_', $str));
        $str = trim($str, '__');
        $str = trim($str, '_');
        $str = trim($str, ' ');
        return $str;
    }

    protected function getPlan($planName = '') {
        $plan = iterator_to_array(Billrun_Factory::db()->plansCollection()->query(array('name' => $planName), array(), array('multi' => true))->cursor());
        return $plan;
    }

    protected function NewSection($section = '') {
        $sectionsNames = array("National traffic", "International Rates", "Premium Rates", "Roaming traffic - Zone 1 (ROAM LIKE HOME)", "All Roaming Traffic", "Other Call Charges");
        if (in_array($section, $sectionsNames)) {
            return $section;
        }
        return 0;
    }

    protected function ZoneSection($section = '') {
        $zonesNames = array("Roaming traffic - Zone 2", "Roaming traffic - Zone 3", "Roaming traffic - Zone 4", "Roaming traffic - Zone 5", "Roaming traffic - Zone 6", "Roaming traffic - Zone 7");
        if (in_array($section, $zonesNames)) {
            return $section;
        }
        return 0;
    }

    protected function getTheZone($line_0 = '') {
        if ($line_0 === "Roaming traffic - Zone 2") {
            return "Zone2";
        }
        if ($line_0 === "Roaming traffic - Zone 3") {
            return "Zone3";
        }
        if ($line_0 === "Roaming traffic - Zone 4") {
            return "Zone4";
        }
        if ($line_0 === "Roaming traffic - Zone 5") {
            return "Zone5";
        }
        if ($line_0 === "Roaming traffic - Zone 6") {
            return "Zone6";
        }
        if ($line_0 === "Roaming traffic - Zone 7") {
            return "Zone7";
        }
    }

    protected function getTheZoneKey($line_0 = '') {
        if ($line_0 === "Roaming traffic - Zone 2") {
            return "2";
        }
        if ($line_0 === "Roaming traffic - Zone 3") {
            return "3";
        }
        if ($line_0 === "Roaming traffic - Zone 4") {
            return "4";
        }
        if ($line_0 === "Roaming traffic - Zone 5") {
            return "5";
        }
        if ($line_0 === "Roaming traffic - Zone 6") {
            return "6";
        }
        if ($line_0 === "Roaming traffic - Zone 7") {
            return "7";
        }
    }

    protected function getDestination($description = '') {
        if (strpos($description, "FIXED")) {
            return str_replace(" FIXED", "", $description);
        } else {
            if (strpos($description, "IntPREMIUM")) {
                return str_replace(" IntPREMIUM", "", $description);
            } else {
                if (strpos($description, "MOBILE")) {
                    return str_replace(" MOBILE", "", $description);
                }
            }
        }
        return -1;
    }

    protected function getInterval($charge_description = '') {
        if ($charge_description == 'per minute') {
            return 60;
        } else {
            if ($charge_description == 'per call') {
                return 1;
            } else {
                if ($charge_description == 'per minute (first 26 sec FREE)'){
                    return 26;
                }
            }
        }
        return -1;
    }

    protected function NationalInsertRateToPlans($line = '', $rate = '', $zone1 = '', $billingIncrementsLine = '', $date = '', $PostPaidMobilePlans = '') {
        $currentPlan = null;
        $pricing = array();
        foreach ($PostPaidMobilePlans as $key => $planName) {
            $price = $line[$key];
            $pricing = $this->getBillingIncrements($billingIncrementsLine[$key]);
            $planKey = $this->getKey($planName);
            //$planKey = $planeName['_values']['key'];
            unset($currentPlan);
            $planRate = $this->getPricedRate($price, $line, $key);
            $usageType = array_keys($planRate['rates'])[0];
            for ($t = 0; $t < count($planRate['rates'][$usageType]['BASE']['rate']); $t++) {
                if ($usageType === "call") {
                    $planRate['rates'][$usageType]['BASE']['rate'][$t]['interval'] = (int) $pricing['amount'];
                    $planRate['rates'][$usageType]['BASE']['rate'][$t]['uom_display']['range'] = $pricing['interval'];
                    $planRate['rates'][$usageType]['BASE']['rate'][$t]['uom_display']['interval'] = $pricing['interval'];
                }
                $this->plans_to_update_by_date[$planKey][$date][$rate['key']][$usageType]['rate'][] = $planRate['rates'][$usageType]['BASE']['rate'][$t];
            }
        }
    }

    protected function getPrefix($description = '') {
        $LastRightParenthesis = strripos($description, ")");
        $LastLeftParenthesis = strripos($description, "(");
        $PreLength = $LastRightParenthesis - $LastLeftParenthesis - 1;
        $onlyPrefix = substr($description, ($LastLeftParenthesis + 1), $PreLength);
        return $onlyPrefix;
    }

    protected function getPricedRate($str = '', $line = '', $rates_index = '') {
        $rate = array();
        $KB = 0;
        $MB = 0;
        $pricingMethod = array();
        $initialLength = strlen($str);
        $startWithNum = "";
        $nat_data = 0;
        $line_2 = $line[2];
        $line_1 = $line[1];
        $line_0 = $line[0];
        if (($line_0 === "Roaming Data") && ((substr_count($str, "€")) > 2)) {
            $lastSlaPosition = iconv_strrpos($str, "/");
            $lastEuroPosition = iconv_strrpos($str, "€");
            $price = iconv_substr($str, $lastEuroPosition + 1, $lastSlaPosition - $lastEuroPosition - 1);
            $volume = iconv_substr($str, $lastSlaPosition + 1, 2);
            if ($volume === "KB") {
                $rate['rates']['data']['BASE']['rate'][] = array(
                    'from' => 0,
                    'to' => 'UNLIMITED',
                    'interval' => 1,
                    'price' => (float) $price,
                    'uom_display' => array(
                        'range' => 'kb1000',
                        'interval' => 'kb1000',
                    )
                );
            } else {
                if ($volume === "MB") {
                    $rate['rates']['data']['BASE']['rate'][] = array(
                        'from' => 0,
                        'to' => 'UNLIMITED',
                        'interval' => 1,
                        'price' => (float) $price,
                        'uom_display' => array(
                            'range' => 'mb1000',
                            'interval' => 'mb1000',
                        )
                    );
                }
            }
            return $rate;
        }
        if (strpos($str, "-") === 0) {
            return (-1);
        }
        if (strpos($line[$rates_index], "See") !== false) {
            $this->setGeneralError("Price cell should have numbers, but instead it has words, like See");
            return -1;
        }
        if (strpos($line[$rates_index], "Allowance") !== false) {
            $this->setGeneralError("Price cell should have numbers, but instead it has words, like Allowance");
            return -1;
        }
        if (strpos($line[$rates_index], "0-") !== false) {
            $lastEuro = iconv_strrpos($line_2, "€");
            $strLen = iconv_strlen($line_2);
            $price = iconv_substr($line_2, $lastEuro + 1, $strLen - $lastEuro - 1);
            $rate['rates']['data']['BASE']['rate'][] = array(
                'from' => 0,
                'to' => 'UNLIMITED',
                'interval' => 1,
                'price' => (float) $price,
                'uom_display' => array(
                    'range' => 'kb1000',
                    'interval' => 'kb1000',
                )
            );
            $nat_data = 1;
            return $rate;
        } else {
            if (strpos($str, "€") === false) {
                $this->setGeneralError('price doesnt start with €,' . $line_0 . ' ' . $line_1);
            }
        }
        if (strpos($str, "€") == 0) {
            $startWithNum = iconv_substr($str, 1, ($initialLength - 1));
            $startWithNumLen = iconv_strlen($startWithNum);
        }
        if ($nat_data != 1) {
            $firstSlashPos = strpos($startWithNum, "/");
            $pricingMethod['first_price'] = iconv_substr($startWithNum, 0, $firstSlashPos);
            $afterFirstSlash = iconv_substr($startWithNum, $firstSlashPos + 1, $startWithNumLen - $firstSlashPos - 1);
        }
        // €x/sec
        if (iconv_substr($afterFirstSlash, 0, 3) == "sec") {
            $rate['rates']['call']['BASE']['rate'][] = array(
                'from' => 0,
                'to' => 'UNLIMITED',
                'interval' => 1,
                'price' => (float) $pricingMethod['first_price'],
                'uom_display' => array(
                    'range' => 'seconds',
                    'interval' => 'seconds',
                )
            );
        }
        // €x/min
        if (iconv_substr($afterFirstSlash, 0, 3) == "min") {
            $rate['rates']['call']['BASE']['rate'][] = array(
                'from' => 0,
                'to' => 'UNLIMITED',
                'interval' => 60,
                'price' => (float) $pricingMethod['first_price'],
                'uom_display' => array(
                    'range' => 'minutes',
                    'interval' => 'minutes',
                )
            );
        }
        if (iconv_substr($afterFirstSlash, 0, 3) == "SMS") {
            $rate['rates']['sms']['BASE']['rate'][] = array(
                'from' => 0,
                'to' => 'UNLIMITED',
                'interval' => 1,
                'price' => (float) $pricingMethod['first_price'],
                'uom_display' => array(
                    'range' => 'counter',
                    'interval' => 'counter',
                )
            );
        }
        if (iconv_substr($afterFirstSlash, 0, 3) == "mms") {
            $rate['rates']['mms']['BASE']['rate'][] = array(
                'from' => 0,
                'to' => 'UNLIMITED',
                'interval' => 1,
                'price' => (float) $pricingMethod['first_price'],
                'uom_display' => array(
                    'range' => 'counter',
                    'interval' => 'counter',
                )
            );
        }
        if (iconv_substr($afterFirstSlash, 0, 3) == "MB") {
            $rate['rates']['data']['BASE']['rate'][] = array(
                'from' => 0,
                'to' => 'UNLIMITED',
                'interval' => 1000000,
                'price' => (float) $pricingMethod['first_price'],
                'uom_display' => array(
                    'range' => 'mb1000',
                    'interval' => 'mb1000',
                )
            );
        }



        // €x/y secs or €x/y min 
        if (is_numeric(iconv_substr($afterFirstSlash, 0, 1)) && $nat_data != 1) {
            $spacePos = strpos($afterFirstSlash, " ");
            $interval = (int) (iconv_substr($afterFirstSlash, 0, $spacePos));
            $secOrMin = iconv_substr($afterFirstSlash, $spacePos + 1, 3);
            $dataInterval = (int) (iconv_substr($afterFirstSlash, 0, 1));
            $OrData = iconv_substr($afterFirstSlash, 1, 2);
            //€x/y secs
            if ($secOrMin == "sec") {
                $rate['rates']['call']['BASE']['rate'][] = array(
                    'from' => 0,
                    'to' => 'UNLIMITED',
                    'interval' => (int) $interval,
                    'price' => (float) $pricingMethod['first_price'],
                    'uom_display' => array(
                        'range' => "seconds",
                        'interval' => "seconds",
                    )
                );
            } else {//€x/y min
                if ($secOrMin == "min") {
                    $rate['rates']['call']['BASE']['rate'][] = array(
                        'from' => 0,
                        'to' => 'UNLIMITED',
                        'interval' => (int) ($interval * 60),
                        'price' => (float) ($pricingMethod['first_price']),
                        'uom_display' => array(
                            'range' => "minutes",
                            'interval' => "minutes",
                        )
                    );
                } elseif ($OrData === "KB") {
                    $rate['rates']['data']['BASE']['rate'][] = array(
                        'from' => 0,
                        'to' => 'UNLIMITED',
                        'interval' => (int) (1000 * $dataInterval),
                        'price' => (float) ($pricingMethod['first_price']),
                        'uom_display' => array(
                            'range' => "kb1000",
                            'interval' => "kb1000",
                        )
                    );
                } else {
                    $this->setGeneralError('No sec/min after interval size, after slash');
                }
            }
        }
        if (iconv_substr($afterFirstSlash, 0, 4) == "call") {
            $afterCall = iconv_substr($afterFirstSlash, 3, strlen($afterFirstSlash));
            if ($afterCall == "") {
                $rate['rates']['call']['BASE']['rate'][] = array(
                    'from' => 0,
                    'to' => 1,
                    'interval' => 1,
                    'price' => (float) $pricingMethod['first_price'],
                    'uom_display' => array(
                        'range' => "seconds",
                        'interval' => "seconds",
                    )
                );
                $rate['rates']['call']['BASE']['rate'][] = array(
                    'from' => 1,
                    'to' => 'UNLIMITED',
                    'interval' => 1,
                    'price' => 0,
                    'uom_display' => array(
                        'range' => "seconds",
                        'interval' => "seconds",
                    )
                );
            } else {
                if (((strpos($afterCall, "+")) === false) && $nat_data != 1) {
                    $this->setGeneralError('Price for a call, and then + sign is missing.');
                }
                $secondEuro = iconv_strpos($afterCall, "€");
                if ($secondEuro === false) {
                    $this->setGeneralError('No second €.');
                }
                $afterCallLen = iconv_strlen($afterCall);
                $secondSlash = iconv_strpos($afterCall, "/");
                $pricingMethod['second_price'] = iconv_substr($afterCall, $secondEuro + 1, $secondSlash - $secondEuro - 1);
                $secondInterval = iconv_substr($afterCall, $secondSlash + 1);
                if ($secondInterval == "sec") {
                    $rate['rates']['call']['BASE']['rate'][] = array(
                        'from' => 0,
                        'to' => 1,
                        'interval' => 1,
                        'price' => (float) $pricingMethod['first_price'] + $pricingMethod['second_price'],
                        'uom_display' => array(
                            'range' => "seconds",
                            'interval' => "seconds",
                        )
                    );
                    $rate['rates']['call']['BASE']['rate'][] = array(
                        'from' => 2,
                        'to' => 'UNLIMITED',
                        'interval' => 1,
                        'price' => (float) $pricingMethod['second_price'],
                        'uom_display' => array(
                            'range' => 'seconds',
                            'interval' => 'seconds',
                        )
                    );
                }
                if ($secondInterval == "min") {
                    $rate['rates']['call']['BASE']['rate'][] = array(
                        'from' => 0,
                        'to' => 60,
                        'interval' => 60,
                        'price' => (float) $pricingMethod['first_price'] + $pricingMethod['second_price'],
                        'uom_display' => array(
                            'range' => "minutes",
                            'interval' => "minutes",
                        )
                    );
                    $rate['rates']['call']['BASE']['rate'][] = array(
                        'from' => 60,
                        'to' => 'UNLIMITED',
                        'interval' => 60,
                        'price' => (float) $pricingMethod['second_price'],
                        'uom_display' => array(
                            'range' => 'minutes',
                            'interval' => 'minutes',
                        )
                    );
                }
                if (is_numeric(iconv_substr($secondInterval, 0, 1))) {

                    $spacePos = strpos($secondInterval, " ");
                    $interval = (int) (iconv_substr($secondInterval, 0, $spacePos));
                    $secOrMin = iconv_substr($secondInterval, $spacePos + 1, 3);
                    if ($secOrMin == "sec") {
                        $rate['rates']['call']['BASE']['rate'][] = array(
                            'from' => 0,
                            'to' => (int) ($interval),
                            'interval' => (int) $interval,
                            'price' => (float) $pricingMethod['first_price'] + $pricingMethod['second_price'],
                            'uom_display' => array(
                                'range' => "seconds",
                                'interval' => "seconds",
                            )
                        );
                        $rate['rates']['call']['BASE']['rate'][] = array(
                            'from' => (int) $interval,
                            'to' => 'UNLIMITED',
                            'interval' => (int) $interval,
                            'price' => (float) $pricingMethod['second_price'],
                            'uom_display' => array(
                                'range' => 'seconds',
                                'interval' => 'seconds',
                            )
                        );
                    } else {
                        if ($secOrMin == "min") {
                            $rate['rates']['call']['BASE']['rate'][] = array(
                                'from' => 0,
                                'to' => (int) (60 * $interval),
                                'interval' => (int) (60 * $interval),
                                'price' => (float) $pricingMethod['first_price'] + $pricingMethod['second_price'],
                                'uom_display' => array(
                                    'range' => "minutes",
                                    'interval' => "minutes",
                                )
                            );
                            $rate['rates']['call']['BASE']['rate'][] = array(
                                'from' => (int) (60 * $interval),
                                'to' => 'UNLIMITED',
                                'interval' => (int) (60 * $interval),
                                'price' => (float) $pricingMethod['second_price'],
                                'uom_display' => array(
                                    'range' => 'minutes',
                                    'interval' => 'minutes',
                                )
                            );
                        } else {
                            $this->setGeneralError('No sec/min after interval size, after slash');
                        }
                    }
                }
            }
        }
        return $rate;
    }

    protected function updatePlan($plan = '') {
        $id = $plan['_id']['$id'];
        $update_array = array(
            'rates' => $plan['rates'],
        );
        $updateUrl = "/billapi/plans/update";
        $updateReq = array('query' => json_encode(array('_id' => $id)), 'update' => json_encode($update_array));
        $updateReq["_t_"] = (string) time();
        $updateReq["_sig_"] = hash_hmac("sha512", json_encode($updateReq), 1);
        $response_1 = json_decode(sendRequest($updateUrl, $updateReq), true);
        return $response_1;
    }

    protected function InsertRateToPlans($line = '', $rate = '', $zone1 = '', $PostPaidMobilePlans = '', $date = '') {
        $currentPlan = null;
        foreach ($PostPaidMobilePlans as $key => $planName) {
            $price = $line[$key];
            $planKey = $this->getKey($planName);
            //$currentPlan = $this->getPlan($planKey);
            unset($currentPlan);
            $planRate = $this->getPricedRate($price, $line, $key);
            if (isset($planRate['rates'])) {
                $usageType = array_keys($planRate['rates'])[0];
                for ($t = 0; $t < count($planRate['rates'][$usageType]['BASE']['rate']); $t++) {
                    $this->plans_to_update_by_date[$planKey][$date][$rate['key']][$usageType]['rate'][] = $planRate['rates'][$usageType]['BASE']['rate'][$t];
                }
            }
        }
    }

    protected function getBillingIncrements($str = '') {
        $s = microtime(true);
        $pricing = array();
        $hyphenIndex = iconv_strpos($str, "-");
        if (iconv_strpos($str, "sec") !== false) {
            $pricing['interval'] = "seconds";
        }
        if (iconv_strpos($str, "min") !== false) {
            $pricing['interval'] = "minutes";
        }
        if (iconv_strpos($str, "secs") !== false) {
            $pricing['interval'] = "seconds";
        }
        $strLen = iconv_strlen($str);
        $IncrementsIndex = iconv_strpos($str, "increments");
        $IncrementsLen = iconv_strlen("increments");
        $subStr = rtrim(iconv_substr($str, 0, $strLen - $IncrementsLen));
        $lastSpace = iconv_strrpos($subStr, " ");
        $pricing['amount'] = iconv_substr($subStr, $lastSpace + 1, $hyphenIndex - $lastSpace - 1);
        return $pricing;
    }

    protected function Zone1Rates($from = '', $to = '', $cyprusPrefix = '', $mccmnc = '', $RoamingPrefixesList = '', $filePath = '') {
        $ratesCollection = Billrun_Factory::db()->ratesCollection();
        $rates = array();
        $rate = array();
        $postPaid = 0;
        $dateIndex = 0;
        $filename = self::ROAMING_PRICES_FOR_ZONE1;
        $Zone1DateFlag = 0;
        //$places = array("Back to Cyprus", "Local", "To EU", "To rest of the world", "Receiving", "SMS", "DATA");
        $zone1RatesFile = fopen($filePath[$filename], 'r');
        if ($zone1RatesFile) {
            while (($line = fgetcsv($zone1RatesFile)) !== FALSE) {
                $line_0 = $line[0];
                $line_1 = $line[1];
                $line_2 = $line[2];
                $line_3 = $line[3];
                $line_4 = $line[4];
                $line_5 = $line[5];
                $line_6 = $line[6];
                $line_7 = $line[7];
                $line_8 = $line[8];
                $line_9 = $line[9];
                $line_10 = $line[10];
                $line_11 = $line[11];
                $line_12 = $line[12];
                $line_13 = $line[13];
                unset($rate);
                if ($line_1 === "POSTPAID") {
                    $postPaid = 1;
                }
                if ($line_1 === "ZONES") {
                    for ($a = 0; $a < count($line); $a++) {
                        if ($line[$a] === "from") {
                            $dateIndex = $a;
                            $Zone1DateFlag = 1;                            
                        }
						if ($line[$a] === "account") { $accountNumIndex = $a; }
						if ($line[$a] === "objectID") { $objectIDIndex = $a; }
						if ($line[$a] === "product label") { $labelIndex = $a; }
						if ($line[$a] === "description") { $descriotionIndex = $a; }
						if ($line[$a] === "fis descr") { $fis_descrIndex = $a; }
						if ($line[$a] === "management reporting") { $managmentReportingIndex = $a; }
                    }
                    if($Zone1DateFlag == 0){
                        Billrun_Factory::log()->log('No date column was found in ' . $filename . ' file. "from" header was expected in the header line that starts with "ZONES". Assume the date is "now", for all of the rates.', Zend_Log::NOTICE);                        
                    }
                }
                if (($line[1] === "ZONES") && ($postPaid == 1)) {
                    for ($t = 0; $t < count($line); $t++) {
                        if ($line[$t] !== "") {
                            $places[$t] = $line[$t];
                        }
                    }
                }
                if (($line_1 === "ZONE 1-EU countries") && ($postPaid == 1)) {
                    foreach ($places as $key => $area) {
                        if ($area === "Back to Cyprus") {
                            $price = $this->priceForZone1($line[$key + 1]);
                            for ($a = 0; $a < count($cyprusPrefix); $a++) {
                                $rate['params']['roaming_prefix'][] = $cyprusPrefix[$a];
                            }
                            foreach ($mccmnc as $keymccmnc => $valmccmnc) {
                                if ($valmccmnc['the_zone'] === "Zone1") {
                                    for ($v = 0; $v < count($valmccmnc['mccmnc']); $v++) {
                                        $rate['params']['mccmnc'][] = $valmccmnc['mccmnc'][$v];
                                    }
                                }
                            }
                            if($Zone1DateFlag == 1 && preg_match(self::DATE_REGEX, $line[$dateIndex])){
                                $from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
                            }else{
                                $from = date(Billrun_Base::base_datetimeformat, time());
                            }
                            $rate['from'] = $from;
                            $rate['to'] = $to;
                            $rate['description'] = 'zone 1 ' . $area;
                            $rate['epic_description'] = !empty($line[$descriotionIndex]) ? $line[$descriotionIndex] : $rate['description'];
							$rate['key'] = $this->getKey(trim($rate['description']));
                            $rate['vatable'] = true;
                            $rate['pricing_method'] = "tiered";
                            $rate['tariff_category'] = "retail";
                            $rate['add_to_retail'] = true;
                            $rate['creation_time'] = $from;
							$rate['account_num'] = !empty($line[$accountNumIndex]) ? $line[$accountNumIndex] : "";
							$rate['object_id'] = !empty($line[$objectIDIndex]) ? $line[$objectIDIndex] : "";
							$rate['label'] = !empty($line[$labelIndex]) ? $line[$labelIndex] : "";
							$rate['fis_descr'] = !empty($line[$fis_descrIndex]) ? $line[$fis_descrIndex] : "";
							$rate['management_reporting'] = !empty($line[$managmentReportingIndex]) ? $line[$managmentReportingIndex] : "";
                            $rate['zone'] = "Zone1";
                            $rate['params']['national_rate'] = false;
                            $rate['params']['international_rate'] = false;
                            $rate['rates']['roaming_call']['BASE']['rate'][] = array(
                                'from' => 0,
                                'to' => 'UNLIMITED',
                                'interval' => 1,
                                'price' => (float) $price,
                                'uom_display' => array(
                                    'range' => 'seconds',
                                    'interval' => 'seconds',
                                )
                            );
                            $this->rates_updateOrcreate[$rate['key']] = $rate;
                            $this->rates_keys_and_dates[$rate['key']] = $from;
                            //echo 'done inserting ' . $rate['description'] . PHP_EOL;
                            unset($rate);
                        }
                        if ($area === "Local") {
                            $price = $this->priceForZone1($line[$key + 1]);

                            foreach ($mccmnc as $keymccmnc => $valmccmnc) {
                                if ($valmccmnc['the_zone'] === "Zone1") {
                                    for ($v = 0; $v < count($valmccmnc['mccmnc']); $v++) {
                                        $rate['params']['mccmnc'][] = $valmccmnc['mccmnc'][$v];
                                    }
                                }
                            }
                            if($Zone1DateFlag == 1 && preg_match(self::DATE_REGEX, $line[$dateIndex])){
                                $from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
                            }else{
                                $from = date(Billrun_Base::base_datetimeformat, time());
                            }
                            $rate['from'] = $from;
                            $rate['to'] = $to;
                            $rate['description'] = 'zone 1 ' . $area . ' ';
                            $rate['epic_description'] = !empty($line[$descriotionIndex]) ? $line[$descriotionIndex] : $rate['description'];
							$rate['params']['national_rate'] = false;
                            $rate['params']['international_rate'] = false;
                            $rate['key'] = $this->getKey(trim($rate['description']));
                            $rate['zone'] = "Zone1";
                            foreach ($RoamingPrefixesList as $keyCountry => $valCountry) {
                                if (array_key_exists("the_zone", $valCountry)) {
                                    if ($valCountry['the_zone'] == "Zone1") {
                                        for ($c = 0; $c < count($valCountry['prefix']); $c++) {
                                            $rate['params']['roaming_prefix'][] = $valCountry['prefix'][$c];
                                        }
                                    }
                                }
                            }
                            $rate['vatable'] = true;
                            $rate['pricing_method'] = "tiered";
                            $rate['tariff_category'] = "retail";
                            $rate['add_to_retail'] = true;
                            $rate['creation_time'] = $from;
							$rate['account_num'] = !empty($line[$accountNumIndex]) ? $line[$accountNumIndex] : "";
							$rate['object_id'] = !empty($line[$objectIDIndex]) ? $line[$objectIDIndex] : "";
							$rate['label'] = !empty($line[$labelIndex]) ? $line[$labelIndex] : "";
							$rate['fis_descr'] = !empty($line[$fis_descrIndex]) ? $line[$fis_descrIndex] : "";
							$rate['management_reporting'] = !empty($line[$managmentReportingIndex]) ? $line[$managmentReportingIndex] : "";
                            $rate['rates']['roaming_call']['BASE']['rate'][] = array(
                                'from' => 0,
                                'to' => 'UNLIMITED',
                                'interval' => 1,
                                'price' => (float) $price,
                                'uom_display' => array(
                                    'range' => 'seconds',
                                    'interval' => 'seconds',
                                )
                            );
                            $this->rates_updateOrcreate[$rate['key']] = $rate;
                            $this->rates_keys_and_dates[$rate['key']] = $from;
                            //echo 'done inserting ' . $rate['description'] . PHP_EOL;
                            unset($rate);
                        }
                        if ($area === "To rest of the world") {
                            $price = $this->priceForZone1($line[$key + 1]);
                            foreach ($mccmnc as $keymccmnc => $valmccmnc) {
                                if ($valmccmnc['the_zone'] === "Zone1") {
                                    for ($v = 0; $v < count($valmccmnc['mccmnc']); $v++) {
                                        $rate['params']['mccmnc'][] = $valmccmnc['mccmnc'][$v];
                                    }
                                }
                            }
                            if($Zone1DateFlag == 1 && preg_match(self::DATE_REGEX, $line[$dateIndex])){
                                $from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
                            }else{
                                $from = date(Billrun_Base::base_datetimeformat, time());
                            }
                            $rate['from'] = $from;
                            $rate['to'] = $to;
                            $rate['zone'] = "Zone1";
                            $rate['description'] = 'zone 1' . ' ' . $area;
                            $rate['epic_description'] = !empty($line[$descriotionIndex]) ? $line[$descriotionIndex] : $rate['description'];
							$rate['params']['national_rate'] = false;
                            $rate['params']['international_rate'] = false;
                            $rate['key'] = $this->getKey(trim($rate['description']));
                            foreach ($RoamingPrefixesList as $keyCountry => $valCountry) {
                                if (array_key_exists("the_zone", $valCountry)) {
                                    if (($valCountry['the_zone'] !== "Zone1") && ($valCountry['prefix'] !== $cyprusPrefix[0]) && ($valCountry['prefix'] !== $cyprusPrefix[1])) {
                                        for ($c = 0; $c < count($valCountry['prefix']); $c++) {
                                            $rate['params']['roaming_prefix'][] = $valCountry['prefix'][$c];
                                        }
                                    }
                                }
                            }
                            $rate['vatable'] = true;
                            $rate['pricing_method'] = "tiered";
                            $rate['tariff_category'] = "retail";
                            $rate['add_to_retail'] = true;
                            $rate['creation_time'] = $from;
							$rate['account_num'] = !empty($line[$accountNumIndex]) ? $line[$accountNumIndex] : "";
							$rate['object_id'] = !empty($line[$objectIDIndex]) ? $line[$objectIDIndex] : "";
							$rate['label'] = !empty($line[$labelIndex]) ? $line[$labelIndex] : "";
							$rate['fis_descr'] = !empty($line[$fis_descrIndex]) ? $line[$fis_descrIndex] : "";
							$rate['management_reporting'] = !empty($line[$managmentReportingIndex]) ? $line[$managmentReportingIndex] : "";
                            $rate['rates']['roaming_call']['BASE']['rate'][] = array(
                                'from' => 0,
                                'to' => 'UNLIMITED',
                                'interval' => 1,
                                'price' => (float) $price,
                                'uom_display' => array(
                                    'range' => 'seconds',
                                    'interval' => 'seconds',
                                )
                            );
                            $this->rates_updateOrcreate[$rate['key']] = $rate;
                            $this->rates_keys_and_dates[$rate['key']] = $from;
                            unset($rate);

                        }
                        if ($area === "Receiving") {
                            $price = $this->priceForZone1($line[$key + 1]);
                            foreach ($RoamingPrefixesList as $keyCountry => $valCountry) {
                                if (array_key_exists("the_zone", $valCountry)) {
                                    if ($valCountry['the_zone'] == "Zone1") {
                                        $mccmncWasFound = 0;
                                        foreach ($mccmnc as $keymccmnc => $valmccmnc) {
                                            if ($keymccmnc === $keyCountry) {
                                                $mccmncWasFound = 1;
                                                for ($v = 0; $v < count($valmccmnc['mccmnc']); $v++) {
                                                    $rate['params']['mccmnc'][] = $valmccmnc['mccmnc'][$v];
                                                }
                                            }
                                        }
                                        if ($mccmncWasFound == 0) {
                                            $this->setEntityWarning($keyCountry, 'Didnt find mccmnc for ' . $keyCountry);
                                        }
                                        if($Zone1DateFlag == 1 && preg_match(self::DATE_REGEX, $line[$dateIndex])){
                                            $from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
                                        }else{  
                                            $from = date(Billrun_Base::base_datetimeformat, time());
                                        }
                                        $rate['zone'] = "Zone1";
                                        $rate['from'] = $from;
                                        $rate['to'] = $to;
                                        $rate['description'] = 'zone 1 ' . $keyCountry . ' ' . $area;
                                        $rate['epic_description'] = !empty($line[$descriotionIndex]) ? $line[$descriotionIndex] : $rate['description'];										
										$rate['key'] = $this->getKey(trim($rate['description']));
                                        $rate['vatable'] = true;
                                        $rate['pricing_method'] = "tiered";
                                        $rate['tariff_category'] = "retail";
                                        $rate['add_to_retail'] = true;
                                        $rate['creation_time'] = $from;
										$rate['account_num'] = !empty($line[$accountNumIndex]) ? $line[$accountNumIndex] : "";
										$rate['object_id'] = !empty($line[$objectIDIndex]) ? $line[$objectIDIndex] : "";
										$rate['label'] = !empty($line[$labelIndex]) ? $line[$labelIndex] : "";
										$rate['fis_descr'] = !empty($line[$fis_descrIndex]) ? $line[$fis_descrIndex] : "";
										$rate['management_reporting'] = !empty($line[$managmentReportingIndex]) ? $line[$managmentReportingIndex] : "";
                                        $rate['params']['national_rate'] = false;
                                        $rate['params']['international_rate'] = false;
                                        $rate['rates']['roaming_call']['BASE']['rate'][] = array(
                                            'from' => 0,
                                            'to' => 'UNLIMITED',
                                            'interval' => 1,
                                            'price' => (float) $price,
                                            'uom_display' => array(
                                                'range' => 'seconds',
                                                'interval' => 'seconds',
                                            )
                                        );
                                        $this->rates_updateOrcreate[$rate['key']] = $rate;
                                        $this->rates_keys_and_dates[$rate['key']] = $from;
                                        unset($rate);
                                    }
                                }
                            }
                        }
                        if ($area === "SMS") {
                            $price = $this->priceForZone1($line[$key]);
                            foreach ($mccmnc as $keymccmnc => $valmccmnc) {
                                if ($valmccmnc['the_zone'] === "Zone1") {
                                    for ($v = 0; $v < count($valmccmnc['mccmnc']); $v++) {
                                        $rate['params']['mccmnc'][] = $valmccmnc['mccmnc'][$v];
                                    }
                                }
                            }
                            if($Zone1DateFlag == 1 && preg_match(self::DATE_REGEX, $line[$dateIndex])){
                                $from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
                            }else{
                                $from = date(Billrun_Base::base_datetimeformat, time());
                            }
                            $rate['zone'] = "Zone1";
                            $rate['from'] = $from;
                            $rate['to'] = $to;
                            $rate['description'] = 'zone 1' . ' ' . $area;
                            $rate['epic_description'] = !empty($line[$descriotionIndex]) ? $line[$descriotionIndex] : $rate['description'];
							$rate['key'] = $this->getKey(trim($rate['description']));
                            $rate['vatable'] = true;
                            $rate['pricing_method'] = "tiered";
                            $rate['tariff_category'] = "retail";
                            $rate['add_to_retail'] = true;
                            $rate['creation_time'] = $from;
							$rate['account_num'] = !empty($line[$accountNumIndex]) ? $line[$accountNumIndex] : "";
							$rate['object_id'] = !empty($line[$objectIDIndex]) ? $line[$objectIDIndex] : "";
							$rate['label'] = !empty($line[$labelIndex]) ? $line[$labelIndex] : "";
							$rate['fis_descr'] = !empty($line[$fis_descrIndex]) ? $line[$fis_descrIndex] : "";
							$rate['management_reporting'] = !empty($line[$managmentReportingIndex]) ? $line[$managmentReportingIndex] : "";
                            $rate['params']['national_rate'] = false;
                            $rate['params']['international_rate'] = false;
                            $rate['rates']['roaming_sms']['BASE']['rate'][] = array(
                                'from' => 0,
                                'to' => 'UNLIMITED',
                                'interval' => 1,
                                'price' => (float) $price,
                                'uom_display' => array(
                                    'range' => 'counter',
                                    'interval' => 'counter',
                                )
                            );
                            $this->rates_updateOrcreate[$rate['key']] = $rate;
                            $this->rates_keys_and_dates[$rate['key']] = $from;
                            unset($rate);
                        }
                        if ($area === "DATA") {
                            $rate['zone'] = "Zone1";
                            $price = $this->priceForZone1($line[$key + 1]);
                            foreach ($mccmnc as $keymccmnc => $valmccmnc) {
                                if ($valmccmnc['the_zone'] === "Zone1") {
                                    for ($v = 0; $v < count($valmccmnc['mccmnc']); $v++) {
                                        $rate['params']['mccmnc'][] = $valmccmnc['mccmnc'][$v];
                                    }
                                }
                            }
                            if($Zone1DateFlag == 1 && preg_match(self::DATE_REGEX, $line[$dateIndex])){
                                $from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
                            }else{
                                $from = date(Billrun_Base::base_datetimeformat, time());
                            }
                            $rate['from'] = $from;
                            $rate['to'] = $to;
                            $rate['description'] = 'zone 1' . ' ' . $area;
                            $rate['epic_description'] = !empty($line[$descriotionIndex]) ? $line[$descriotionIndex] : $rate['description'];
							$rate['key'] = $this->getKey(trim($rate['description']));
                            $rate['vatable'] = true;
                            $rate['pricing_method'] = "tiered";
                            $rate['tariff_category'] = "retail";
                            $rate['add_to_retail'] = true;
                            $rate['creation_time'] = $from;
							$rate['account_num'] = !empty($line[$accountNumIndex]) ? $line[$accountNumIndex] : "";
							$rate['object_id'] = !empty($line[$objectIDIndex]) ? $line[$objectIDIndex] : "";
							$rate['label'] = !empty($line[$labelIndex]) ? $line[$labelIndex] : "";
							$rate['fis_descr'] = !empty($line[$fis_descrIndex]) ? $line[$fis_descrIndex] : "";
							$rate['management_reporting'] = !empty($line[$managmentReportingIndex]) ? $line[$managmentReportingIndex] : "";
                            $rate['params']['national_rate'] = false;
                            $rate['params']['international_rate'] = false;
                            $rate['rates']['data']['BASE']['rate'][] = array(
                                'from' => 0,
                                'to' => 'UNLIMITED',
                                'interval' => 1000000,
                                'price' => (float) $price,
                                'uom_display' => array(
                                    'range' => 'mb1000',
                                    'interval' => 'mb1000',
                                )
                            );
                            $rate['params']['roaming_data'] = true;
                            $this->rates_updateOrcreate[$rate['key']] = $rate;
                            $this->rates_keys_and_dates[$rate['key']] = $from;
                            unset($rate);
                            return 1;
                        }
                    }
                }
            }
        }
    }

    protected function priceForZone1($price = '') {
        if (iconv_strpos($price, "€") === false) {
            $this->setGeneralError('No € in one of the prices for zone 1');
        }
        $euroIndex = iconv_strpos($price, "€");
        $priceLen = iconv_strlen($price);
        $onlyPrice = trim(iconv_substr($price, $euroIndex + 1, $priceLen - $euroIndex));
        return $onlyPrice;
    }

    protected function InsertRateToPlansBiB($lineIndex = '', $rate = '') {
        $currentPlan = "";
        //$BiBPlans = array('BROADBAND_IN_A_BOX_M40','BROADBAND_IN_A_BOX_L60','BROADBAND_IN_A_BOX_XL80','BROADBAND_IN_A_BOX_NEW_200GB','BROADBAND_IN_A_BOX_NEW_500GB','MOBILE_BROADBAND_ACCESS','');
        if ($lineIndex == 3) {
            $currentPlan = $this->getPlan("BROADBAND_IN_A_BOX_M40");
            $planeName = "BROADBAND_IN_A_BOX_M40";
        }
        if ($lineIndex == 4) {
            $currentPlan = $this->getPlan("BROADBAND_IN_A_BOX_L60");
            $planeName = "BROADBAND_IN_A_BOX_L60";
        }
        if ($lineIndex == 5) {
            $currentPlan = $this->getPlan("BROADBAND_IN_A_BOX_XL80");
            $planeName = "BROADBAND_IN_A_BOX_XL80";
        }
        if ($lineIndex == 6) {
            $currentPlan = $this->getPlan("BROADBAND_IN_A_BOX_NEW_200GB");
            $planeName = "BROADBAND_IN_A_BOX_NEW_200GB";
        }
        if ($lineIndex == 7) {
            $currentPlan = $this->getPlan("BROADBAND_IN_A_BOX_NEW_500GB");
            $planeName = "BROADBAND_IN_A_BOX_NEW_500GB";
        }
        if ($lineIndex == 8) {
            $currentPlan = $this->getPlan("MOBILE_BROADBAND_ACCESS");
            $planeName = "MOBILE_BROADBAND_ACCESS";
        }
        if (!isset($currentPlan['rates'][$rate['key']])) {
            for ($t = 0; $t < count($rate['rates'][$usageType]['BASE']['rate']); $t++) {
                $currentPlan['rates'][$rate['key']][$usageType]['rate'][] = $rate['rates'][$usageType]['BASE']['rate'][$t];
            }
        }
        $this->plans_to_update_by_date[$currentPlan['key']] = $currentPlan;
    }

    protected function getIntervalM2M($str = '', $key = '', $billingIncLine = '', $price = '') {
        $SlashPos = iconv_strrpos($str, "/");
        $strLen = iconv_strlen($str);
        $afterFirstSlash = iconv_substr($str, $SlashPos + 1, $strLen - $SlashPos - 1);
        if (is_numeric(iconv_substr($afterFirstSlash, 0, 1))) {
            $spacePos = strpos($afterFirstSlash, " ");
            $pricing = $this->getBillingIncrements($billingIncLine[$key]);
            $IntervalType = $pricing['interval'];
            $IntervalSize = $pricing['amount'];
            //$secOrMin = iconv_substr($afterFirstSlash, $spacePos + 1, 4);
            if ((iconv_strpos($str, "secs") !== false) || (iconv_strpos($str, "min") !== false)) {
                $rate['rates']['call']['BASE']['rate'][] = array(
                    'from' => 0,
                    'to' => 'UNLIMITED',
                    'interval' => (int) $IntervalSize,
                    'price' => (float) $price,
                    'uom_display' => array(
                        'range' => $IntervalType,
                        'interval' => $IntervalType,
                    )
                );
            } else {
                $rate['rates']['call']['BASE']['rate'][] = array(
                    'from' => 0,
                    'to' => 'UNLIMITED',
                    'interval' => (int) $IntervalSize,
                    'price' => (float) $price,
                    'uom_display' => array(
                        'range' => "seconds",
                        'interval' => "seconds",
                    )
                );
                $this->setEntityWarning($key, 'At M2M , cloumn ' . $key . ', the interval supposed to be secs or min, and its not. "seconds" was imported');
            }
        }
        return $rate;
    }

    protected function M2MRates($filesPath = '', $PostPaidMobileRates = '', $ratesCollection = '') {
        $filename = self::M2M_PLANS_FILE;
        $file = fopen($filesPath[$filename], 'r');
        $to = new MongoDate(strtotime('2119-05-01 00:00:00'));
        $rate = array();
        $nationalM2M = 0;
        $line = array();
        $natOutOfBoundle = null;
        $currentRate = null;
        $natOffNet = 0;
        $natSMSonet = null;
        $natSMSoffnet = null;
        $natonet = 0;
        $M2MDateFlag = 0;
        while (($line = fgetcsv($file)) !== FALSE) {
            $line_0 = $line[0];
            $line_1 = $line[1];
            $line_2 = $line[2];
            $line_3 = $line[3];
            $line_4 = $line[4];
            $line_5 = $line[5];
            $line_6 = $line[6];
            $line_7 = $line[7];
            $line_8 = $line[8];
            $line_9 = $line[9];
            $line_10 = $line[10];
            if ($line_0 == "Billing Increments for National Voice Traffic") {
                $billingIncrementsLine = $line;
                $billingIncrementsExist = 1;
            }
        }
        if ($billingIncrementsExist === 0) {
            $this->setError($filename, 'no "billing increments" line - didnt find billing icrements at cell 0, at "M2M plans v8.1.csv"');
            return -1;
        }
        $M2Mfile = fopen($filesPath[$filename], 'r');
        while (($line = fgetcsv($M2Mfile)) !== FALSE) {
            $line_0 = $line[0];
            $line_1 = $line[1];
            $line_2 = $line[2];
            $line_3 = $line[3];
            $line_4 = $line[4];
            $line_5 = $line[5];
            $line_6 = $line[6];
            $line_7 = $line[7];
            $line_8 = $line[8];
            $line_9 = $line[9];
            $line_10 = $line[10];
            if ($line_1 === "MTN Plan Name") {
                for ($a = 0; $a < count($line); $a++) {
                    if (($line[$a] !== "") && ($line[$a] !== "MTN Plan Name") && ($line[$a] !== "fro")) {
                        $M2MPlans[$a] = $line[$a];
                    }
                    if ($line[$a] === "from") {
                        $dateIndex = $a;
                        $M2MDateFlag = 1;
                    }
                }
                if($M2MDateFlag == 0){
                        Billrun_Factory::log()->log('No date column was found in ' . $filename . ' file. "from" header was expected in the header line that starts with "MTN Plan Name". Assume the date is "now", for all of the rates.', Zend_Log::NOTICE);
                        $from = date(Billrun_Base::base_datetimeformat, time());
                    }
            }
            if ($line_0 === "National Voice") {
                $nationalM2M = 1;
                continue;
            }
            if ($nationalM2M === 1) {
                if ($line_0 === "Nat'l Voice On-Net") {
                    $natonet = 1;
                    if ($line_1 === "Out of Bundle") {
                        for ($b = 0; $b < count($PostPaidMobileRates); $b++) {
                            if ($PostPaidMobileRates[$b]['key'] === "NATL_VOICE_ON_NET_OUT_OF_BUNDLE") {
                                $currentRate = $PostPaidMobileRates[$b];
                            }
                        }
                    } else {
                        $this->setError($filename, 'First column is Natl voice on-net, and the second column is not Out of Bundle');
                        return -1;
                    }
                }
                if ($line_0 === "Nat'l Voice Off-Net") {
                    $natOffNet = 1;
                }
                if (($natOffNet == 1) && ($line_1 === "Cyta")) {
                    for ($b = 0; $b < count($PostPaidMobileRates); $b++) {
                        if ($PostPaidMobileRates[$b]['key'] === "NATIONAL_VOICE_OFF_NET_CYTA") {
                            $currentRate = $PostPaidMobileRates[$b];
                        }
                    }
                } else {
                    if (($natOffNet == 1) && ($line_1 === "Primetel")) {
                        for ($b = 0; $b < count($PostPaidMobileRates); $b++) {
                            if ($PostPaidMobileRates[$b]['key'] === "NATIONAL_VOICE_OFF_NET_PRIMETEL") {
                                $currentRate = $PostPaidMobileRates[$b];
                            }
                        }
                    }
                }
                if ($line_0 === "Nat'l Voice Fix-Net") {
                    if ($line_1 === "Out of Bundle") {
                        for ($b = 0; $b < count($PostPaidMobileRates); $b++) {
                            if ($PostPaidMobileRates[$b]['key'] === "NATL_VOICE_FIX_NET_OUT_OF_BUNDLE") {
                                $currentRate = $PostPaidMobileRates[$b];
                            }
                        }
                    } else {
                        $this->setError($filename, 'Expected - Out of Bundle, after - Natl Voice Fix-Net, and didnt find');
                        return -1;
                    }
                }
                if ($line_0 === "Billing Increments for National Voice Traffic") {
                    continue 1;
                }
                if ($line_0 === "SMS Local") {
                    for ($b = 0; $b < count($PostPaidMobileRates); $b++) {
                        if ($PostPaidMobileRates[$b]['key'] === "NATL_SMS_ON_NET") {
                            $natSMSonet = $PostPaidMobileRates[$b];
                        }
                        if ($PostPaidMobileRates[$b]['key'] === "NATL_SMS_OFF_NET") {
                            $natSMSoffnet = $PostPaidMobileRates[$b];
                        }
                    }
                    if ($natSMSonet['rates']['local_sms']['BASE']['rate'][0]['price'] === $natSMSoffnet['rates']['local_sms']['BASE']['rate'][0]['price']) {
                        $currentRate = $natSMSonet;
                    } else {
                        $this->setError($filename, 'Different prices for nat sms on net and off net, so no rate for local sms');
                        return -1;
                    }
                }
                if ($line_0 === "SMS International") {
                    for ($b = 0; $b < count($PostPaidMobileRates); $b++) {
                        if ($PostPaidMobileRates[$b]['key'] === "INTERNATIONAL_SMS") {
                            $currentRate = $PostPaidMobileRates[$b];
                        }
                    }
                }
                if ((iconv_strpos($line_0, "SMS") === false) && ($nationalM2M == 1)) {
                    foreach ($M2MPlans as $key => $planName) {
                        $rate['key'] = $currentRate['key'];
                        if (($line[$key] !== "N/A") && ($line[$key] !== "n/a")) {
                            $returned_price = $this-> getPricedRate($line[$key], $line, $key);
                            $price = (float) $returned_price['rates']['call']['BASE']['rate'][0]['price'];
                            $returnedRate = $this-> getIntervalM2M($line[$key], $key, $billingIncrementsLine, $price);
                        } else {
                            $price = 0;
                            $returnedRate['rates']['call']['BASE']['rate'][] = array(
                                'from' => 0,
                                'to' => 'UNLIMITED',
                                'interval' => 1,
                                'price' => (float) $price,
                                'uom_display' => array(
                                    'range' => 'seconds',
                                    'interval' => 'seconds',
                                )
                            );
                        }
                        $usageType = 'local_call';
                        $planKey = $this->getKey($planName);
						$defaultFrom = $from = date(Billrun_Base::base_datetimeformat, time());
                        for ($i = 0; $i < count($returnedRate['rates']['call']['BASE']['rate']); $i++) {
                            if(($M2MDateFlag == 1) && (!empty($line[$dateIndex])) && preg_match(self::DATE_REGEX, $line[$dateIndex])){
								$from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
                            }else{
                                $from = $defaultFrom;
                            }
							$this->plans_to_update_by_date[$planKey][$from][$rate['key']][$usageType]['rate'][] = $returnedRate['rates']['call']['BASE']['rate'][$i];
                        }
                        unset($rate);
                    }
                } else {
                    if ($nationalM2M == 1) {
                        foreach ($M2MPlans as $key => $planName) {
                            $rate['key'] = $currentRate['key'];
                            if (($line[$key] !== "N/A") && ($line[$key] !== "n/a")) {
                                $price = (float) $this->priceForZone1($line[$key]);
                            } else {
                                $price = 0;
                            }
                            $rate['rates']['local_sms']['BASE']['rate'][] = array(
                                'from' => 0,
                                'to' => 'UNLIMITED',
                                'interval' => 1,
                                'price' => (float) $price,
                                'uom_display' => array(
                                    'range' => 'counter',
                                    'interval' => 'counter',
                                )
                            );
                            $usageType = 'local_sms';
                            $planKey = $this->getKey($planName);
							$defaultFrom = date(Billrun_Base::base_datetimeformat, time());
                            for ($i = 0; $i < count($rate['rates']['local_sms']['BASE']['rate']); $i++) {
                                if(($M2MDateFlag == 1) && (!empty($line[$dateIndex])) && preg_match(self::DATE_REGEX, $line[$dateIndex])){
									$from = date(Billrun_Base::base_datetimeformat, strtotime($line[$dateIndex]));
                                }else{
                                    $from = $defaultFrom;
                                }
								$this->plans_to_update_by_date[$planKey][$from][$rate['key']][$usageType]['rate'][] = $rate['rates']['local_sms']['BASE']['rate'][$i];
                            }
                            unset($rate);
                        }
                    }
                }
            }
        }
    }

    public function getOutput(){
        return $this->output;
    }
}
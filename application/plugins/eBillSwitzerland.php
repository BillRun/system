<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2022 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * ebill plugin
 *
 * @package  Application
 * @subpackage Plugins
 */
class eBillSwitzerlandPlugin extends Billrun_Plugin_BillrunPluginBase
{
	protected $xmlInvoice;
	protected $xmlOutputPath;
	protected $xmlFilename;
	protected $header_values;
	protected $delivery_info;
	protected $bill_headers;
	protected $address;
	protected $export_sftp_host;
	protected $export_sftp_user;
	protected $export_sftp_password;
	protected $export_sftp_remote_directory;
	protected $response_sftp_host;
	protected $response_sftp_user;
	protected $response_sftp_password;
	protected $response_sftp_remote_directory;
	protected $response_retention_months;
	protected $xmlFullPath;
	protected $lineItemCounter;
	protected $line_item_template;
	protected $string_keys;
	protected $optional_keys = [];
	protected $bill_summary_template;
	protected $abortGeneration = false;
	protected $should_generate_ebill;
	protected $creditor_reference_prefix;
	protected $plugin_name = 'eBillSwitzerland';

	public function __construct($options = [])
	{
		br_yaf_register_autoload('Utils', APPLICATION_PATH . '/application/helpers');
		$this->plugin_name = 'eBillSwitzerland';
		$this->abortGeneration = false;
		$this->xmlInvoice = [];
		$this->xmlOutputPath = '';
		$this->xmlFilename = '';
		$this->header_values = $this->flattenOrderedConfig(Billrun_Util::getIn($options, "header_values", false));
		if (is_array($this->header_values)) {
			Billrun_Util::setIn($this->xmlInvoice, 'Header', $this->header_values);
		} else {
			Billrun_Util::setIn($this->xmlInvoice, 'Header', []);
		}
		Billrun_Util::setIn($this->xmlInvoice, 'Body', []);
		$this->delivery_info = $this->flattenOrderedConfig(Billrun_Util::getIn($options, "delivery_info", false));
		Billrun_Util::setIn($this->xmlInvoice, 'Body.DeliveryInfo', []);
		$this->bill_headers = $this->flattenOrderedConfig(Billrun_Util::getIn($options, "bill_headers", false));
		Billrun_Util::setIn($this->xmlInvoice, 'Body.Bill.Header', []);
		Billrun_Util::setIn($this->xmlInvoice, 'Body.Bill.LineItems', []);
		$this->bill_summary_template = $this->flattenOrderedConfig(Billrun_Util::getIn($options, "bill_summary_template", false));
		Billrun_Util::setIn($this->xmlInvoice, 'Body.Bill.Summary', []);
		$this->address = Billrun_Util::getIn($options, "address", false);
		$this->export_sftp_host = Billrun_Util::getIn($options, "export_sftp_host", "");
		$this->export_sftp_user = Billrun_Util::getIn($options, "export_sftp_user", "");
		$this->export_sftp_password = Billrun_Util::getIn($options, "export_sftp_password", "");
		$this->export_sftp_remote_directory = Billrun_Util::getIn($options, "export_sftp_remote_directory", "");
		$this->response_sftp_host = Billrun_Util::getIn($options, "response_sftp_host", "");
		$this->response_sftp_user = Billrun_Util::getIn($options, "response_sftp_user", "");
		$this->response_sftp_password = Billrun_Util::getIn($options, "response_sftp_password", "");
		$this->response_sftp_remote_directory = Billrun_Util::getIn($options, "response_sftp_remote_directory", "");
		$this->response_retention_months = (int) Billrun_Util::getIn($options, "response_retention_months", 6);
		//$this->line_item_template = Billrun_Util::getIn($options, "line_item_template", false);
		$this->line_item_template = $this->flattenOrderedConfig(Billrun_Util::getIn($options, "line_item_template", false));
		$string_keys_array = Billrun_Util::getIn($options, "string_keys", false);
		if (is_array($string_keys_array)) {
			$this->string_keys = array_flip($string_keys_array);
		}
		$optional_keys_array = Billrun_Util::getIn($options, "optional_keys", false);
		if (is_array($optional_keys_array)) {
			$this->optional_keys = array_flip($optional_keys_array);
		}
		$this->should_generate_ebill = Billrun_Util::getIn($options, "should_generate_ebill", []);
		$this->creditor_reference_prefix = Billrun_Util::getIn($options, "creditor_reference_prefix", "");
	}

	public function getConfigurationDefinitions()
	{
		return [
			[
				"type" => "json",
				"field_name" => "header_values",
				"title" => "Header values",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
			],
			[
				"type" => "json",
				"field_name" => "string_keys",
				"title" => "Whitelisted String Keys",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
			],
			[
				"type" => "json",
				"field_name" => "optional_keys",
				"title" => "Optional Keys",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
			],
			[
				"type" => "json",
				"field_name" => "line_item_template",
				"title" => "Line Item Template",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
			],
			[
				"type" => "json",
				"field_name" => "delivery_info",
				"title" => "Delivery info",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
			],
			[
				"type" => "json",
				"field_name" => "bill_headers",
				"title" => "Bill headers",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
			],
			[
				"type" => "json",
				"field_name" => "address",
				"title" => "Full customer address",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
			],
			[
				"type" => "json",
				"field_name" => "bill_summary_template",
				"title" => "Bill Summary Template",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
			],
			[
				"type" => "string",
				"field_name" => "export_sftp_host",
				"title" => "Export SFTP Host (XML Invoice Destination After Confirmation)",
				"editable" => true,
				"display" => true,
				"nullable" => false,
			],
			[
				"type" => "string",
				"field_name" => "export_sftp_user",
				"title" => "Export SFTP Username",
				"editable" => true,
				"display" => true,
				"nullable" => false,
			],
			[
				"type" => "string",
				"field_name" => "export_sftp_password",
				"title" => "Export SFTP Password",
				"editable" => true,
				"display" => true,
				"nullable" => false,
			],
			[
				"type" => "string",
				"field_name" => "export_sftp_remote_directory",
				"title" => "Export SFTP Remote Directory",
				"editable" => true,
				"display" => true,
				"nullable" => false,
			],
			[
				"type" => "json",
				"field_name" => "should_generate_ebill",
				"title" => "Should Generate Ebill",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
			],
			[
				"type" => "string",
				"field_name" => "response_sftp_host",
				"title" => "Response SFTP Host (Status Files Source)",
				"editable" => true,
				"display" => true,
				"nullable" => false,
			],
			[
				"type" => "string",
				"field_name" => "response_sftp_user",
				"title" => "Response SFTP Username",
				"editable" => true,
				"display" => true,
				"nullable" => false,
			],
			[
				"type" => "string",
				"field_name" => "response_sftp_password",
				"title" => "Response SFTP Password",
				"editable" => true,
				"display" => true,
				"nullable" => false,
			],
			[
				"type" => "string",
				"field_name" => "response_sftp_remote_directory",
				"title" => "Response SFTP Remote Directory",
				"editable" => true,
				"display" => true,
				"nullable" => false,
			],
			[
				"type" => "number",
				"field_name" => "response_retention_months",
				"title" => "Response Files Retention (Months)",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"default_value" => 6
			],
			[
				"type" => "string",
				"field_name" => "creditor_reference_prefix",
				"title" => "Creditor Reference Prefix",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true

			]
		];
	}

	public function afterGeneratorEntity($generator, $accountBillrun, $lines)
	{
		$this->generateAndOutputXml($generator, $accountBillrun, $lines);
	}

	public function cronHour()
	{
		$this->processResponseStatusFiles();
	}


	protected function generateAndOutputXml($generator, $accountBillrun, $lines)
	{
		if (!$this->shouldGenerateInvoice($accountBillrun)) {
			return;
		}
		if (!$this->validateInvoiceTotalCost($accountBillrun, $lines)) {
			return;
		}
		$exportDir = $generator->getExportDirectory();
		$baseDir = dirname(rtrim($exportDir, '/\\'));
		$billrunKey = isset($accountBillrun['billrun_key']) ? $accountBillrun['billrun_key'] : '';
		Billrun_Factory::log("eBill Plugin: Starting XML generation for AID: " . $accountBillrun['aid'] . ", Key: " . $billrunKey, Zend_Log::INFO);
		$this->xmlOutputPath = $baseDir . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'ebillSwitzerland' . DIRECTORY_SEPARATOR . $billrunKey . DIRECTORY_SEPARATOR;
		if (isset($accountBillrun['file_name'])) {
			$baseName = preg_replace('/\.[^.]+$/', '', $accountBillrun['file_name']);
			$this->xmlFilename = $baseName . '.xml';
		} else {
			Billrun_Factory::log("eBill Plugin: Billrun did not contain file_name. Aborting XML generation.", Zend_Log::ALERT);
			return;
		}

		$this->buildDeliveryInfo($accountBillrun);
		$this->buildBillHeader($accountBillrun);
		$this->buildBillSummary($accountBillrun);
		$this->formatInvoice();

		$fullXmlPath = $this->xmlOutputPath . $this->xmlFilename;
		$this->xmlFullPath = $fullXmlPath;

		if (empty($this->xmlOutputPath) || empty($this->xmlFilename)) {
			Billrun_Factory::log("eBill Plugin: Output path or filename is not set. Path: '{$this->xmlOutputPath}', Filename: '{$this->xmlFilename}'", Zend_Log::ALERT);
			return;
		}

		if (!is_dir($this->xmlOutputPath)) {
			if (!mkdir($this->xmlOutputPath, 0777, true)) {
				Billrun_Factory::log("eBill Plugin: Failed to create output directory: " . $this->xmlOutputPath, Zend_Log::ALERT);
				return;
			}
		}

		try {
			$xml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><Envelope type=\"string\"></Envelope>");
			$this->arrayToXml($this->xmlInvoice, $xml);
			$this->injectAppendix($xml, $accountBillrun);
			if ($this->abortGeneration) {
				Billrun_Factory::log("eBill Plugin ABORTED: Cannot generate XML due to missing critical data (see previous logs).", Zend_Log::ALERT);
				return;
			}
			$dom = dom_import_simplexml($xml)->ownerDocument;
			$dom->formatOutput = true;

			if ($dom->save($this->xmlFullPath) !== false) {
				Billrun_Factory::log("eBill Plugin: XML invoice saved to: " . $this->xmlFullPath, Zend_Log::INFO);
                $billrunOptions = [
                    'aid' => $accountBillrun['aid'],
                    'billrun_key' => $accountBillrun['billrun_key']
                ];
				$billrunWrapper = Billrun_Billrun::getInstance($billrunOptions);
				if ($billrunWrapper) {
					$billrunWrapper->setPluginField($this->plugin_name, 'invoice_swiss_ebill_xml', $this->xmlFullPath);
					if (!$billrunWrapper->save()) {
						Billrun_Factory::log("eBill Plugin Error: Failed to save XML file at: " . $this->xmlFullPath, Zend_Log::ALERT);
					}
				} else {
					Billrun_Factory::log("eBill Plugin Error: Billrun not found for AID: " . $accountBillrun['aid'] . ", Key: " . $accountBillrun['billrun_key'], Zend_Log::WARN);
				}

                
		} else {
				Billrun_Factory::log("eBill Plugin Error: Failed to save XML file at: " . $this->xmlFullPath, Zend_Log::ALERT);
			}
		} catch (Exception $e) {
			Billrun_Factory::log("eBill Plugin Error: Error generating XML: " . $e->getMessage(), Zend_Log::ALERT);
		}
	}


	private function arrayToXml($array, &$xml)
	{
		$repeatingKeys = ['LineItem', 'TaxDetail'];
		foreach ($array as $key => $value) {
			if (!is_numeric($key)) {
				if (in_array($key, $repeatingKeys) && is_array($value)) {
					foreach ($value as $itemData) {
						$child = $xml->addChild($key);
						$this->arrayToXml($itemData, $child);
					}
					continue;
				}
				if (is_array($value)) {
					$child = $xml->addChild($key);
					$this->arrayToXml($value, $child);
				} else {
					$xml->addChild($key, htmlspecialchars($value ?? ''));
				}
			}
		}
	}



	protected function buildDeliveryInfo($accountBillrun)
	{
		$deliveryInfo = $this->populateFromConfig($this->delivery_info, $accountBillrun);
		Billrun_Util::setIn($this->xmlInvoice, 'Body.DeliveryInfo', $deliveryInfo);
	}


	protected function buildBillHeader($accountBillrun)
	{
		$populatedBillHeader = $this->populateFromConfig($this->bill_headers, $accountBillrun);
		Billrun_Util::setIn($this->xmlInvoice, 'Body.Bill.Header', $populatedBillHeader);
		$this->buildAddress($accountBillrun);
		if (!empty($this->creditor_reference_prefix)) {
			$creditorRef = Utils_Ebill::buildCreditorReference($this->creditor_reference_prefix, $accountBillrun['aid']);
			Billrun_Util::setIn($this->xmlInvoice, 'Body.Bill.Header.PaymentInformation.IBAN.CreditorReference', $creditorRef);
		}
		$totalAfterVat = Billrun_Util::getIn($accountBillrun, 'totals.after_vat_rounded', 0);
		if ($totalAfterVat <= 0) {
			$PaymentType = 'CREDIT';
		} else {
			$PaymentType  = 'IBAN';
		}
		Billrun_Util::setIn($this->xmlInvoice, 'Body.Bill.Header.PaymentInformation.PaymentType', $PaymentType);
	}

	protected function buildAddress($accountBillrun)
	{
		$addressPaths = is_array($this->address) ? $this->address : [];

		$street     = Billrun_Util::getIn($accountBillrun, $addressPaths['street'] ?? '', '');
		$houseNum   = Billrun_Util::getIn($accountBillrun, $addressPaths['house_number'] ?? '', '');
		$zip        = Billrun_Util::getIn($accountBillrun, $addressPaths['zip_code'] ?? '', '');
		$city       = Billrun_Util::getIn($accountBillrun, $addressPaths['city'] ?? '', '');
		$country    = Billrun_Util::getIn($accountBillrun, $addressPaths['country'] ?? '', '');

		$parts = [
			$street,
			$houseNum,
			$zip,
			$city,
			$country
		];

		$fullAddress = implode(' ', array_filter($parts));
		Billrun_Util::setIn($this->xmlInvoice, 'Body.Bill.Header.ReceiverParty.PartyType.Address.Address1', $fullAddress);
		Billrun_Util::setIn($this->xmlInvoice, 'Body.Bill.Header.InvoiceReceivingParty.Address.Address1', $fullAddress);
	}

	protected function formatDates()
	{
		$datePaths = [
			'Body.Bill.Header.AchievementDate.StartDateAchievement',
			'Body.Bill.Header.AchievementDate.EndDateAchievement',
			'Body.Bill.Header.PaymentInformation.PaymentDueDate',
			'Body.DeliveryInfo.DeliveryDate',
			'Body.Bill.Header.DocumentDate'

		];

		foreach ($datePaths as $path) {
			$dateValue = Billrun_Util::getIn($this->xmlInvoice, $path);
			try {
				if (is_object($dateValue) && isset($dateValue->sec)) {
					$formattedDate = date('Y-m-d', (int)$dateValue->sec);
					Billrun_Util::setIn($this->xmlInvoice, $path, $formattedDate);
				}
			} catch (Exception $e) {
			}
		}
	}

	protected function formatVat()
	{
		$path = 'Body.Bill.Header.SenderParty.TaxLiability';
		$taxValue = Billrun_Util::getIn($this->xmlInvoice, $path);
		if ($taxValue === 0 || $taxValue === '0') {
			Billrun_Util::setIn($this->xmlInvoice, $path, 'FRE');
		} else {
			Billrun_Util::setIn($this->xmlInvoice, $path, 'VAT');
		}
	}

	protected function formatLanguage()
	{
		$path = 'Body.Bill.Header.Language';
		$language = Billrun_Util::getIn($this->xmlInvoice, $path, '');
		if (!empty($language) && strpos($language, '_') !== false) {
			Billrun_Util::setIn($this->xmlInvoice, $path, explode('_', $language)[0]);
		}
	}

	protected function formatEBillAccountID()
	{
		$path = 'Body.DeliveryInfo.eBillAccountID';
		$eBillAccountID = Billrun_Util::getIn($this->xmlInvoice, $path, '');
		if (!empty($eBillAccountID)) {
			Billrun_Util::setIn($this->xmlInvoice, $path, str_pad($eBillAccountID, 17, '0', STR_PAD_LEFT));
		}
	}

	protected function formatInvoice()
	{
		$this->formatDates();
		$this->formatVat();
		$this->formatLanguage();
		$this->formatEBillAccountID();
	}

	protected function uploadToSftp()
	{
		$localFilePath = $this->xmlFullPath;
		$fileName = $this->xmlFilename;

		$host = $this->export_sftp_host;
		$user = $this->export_sftp_user;
		$password = $this->export_sftp_password;
		$remoteDirConf = $this->export_sftp_remote_directory;

		if (empty($host) || empty($user)) {
			Billrun_Factory::log("eBill Plugin: SFTP configuration missing (host or user). Skipping upload.", Zend_Log::ALERT);
			return;
		}

		if (empty($localFilePath) || empty($fileName)) {
			Billrun_Factory::log("eBill Plugin: Missing file path or name for SFTP upload. Skipping upload.", Zend_Log::ALERT);
			return;
		}

		$auth = [
			'password' => $password
		];

		try {
			$connection = new Billrun_Ssh_Seclibgateway($host, $auth, []);
			Billrun_Factory::log("eBill Plugin: Connecting to SFTP server: " . $host, Zend_Log::DEBUG);

			if (!$connection->connect($user)) {
				Billrun_Factory::log("eBill Plugin: SFTP Connection failed. Skipping upload", Zend_Log::ALERT);
				return;
			}
			$remoteDir = !empty($remoteDirConf) ? rtrim($remoteDirConf, '/') : '';
			if (!empty($remoteDir)) {
				if (!$connection->getConnection()->is_dir($remoteDir)) {
					Billrun_Factory::log("eBill Plugin: Remote directory does not exist. Creating: " . $remoteDir, Zend_Log::DEBUG);
					$connection->getConnection()->mkdir($remoteDir, 0770, true);
				}
				$remotePath = $remoteDir . '/' . $fileName;
			} else {
				$remotePath = $fileName; // Upload to root if no dir specified
			}

			Billrun_Factory::log("eBill Plugin: Uploading " . $fileName . " to " . $remotePath, Zend_Log::DEBUG);

			$response = $connection->put($localFilePath, $remotePath);

			if ($response) {
				Billrun_Factory::log("eBill Plugin: Uploaded " . $fileName . " to SFTP successfully.", Zend_Log::INFO);
			} else {
				Billrun_Factory::log("eBill Plugin: Failed to upload " . $fileName . "to SFTP", Zend_Log::ALERT);
			}
		} catch (Exception $e) {
			Billrun_Factory::log("eBill Plugin: SFTP upload exception: " . $e->getMessage(), Zend_Log::ALERT);
		}
	}

	public function afterInvoiceConfirmed($bill, $invoice)
	{
		if (!$this->shouldGenerateInvoice($invoice)) {
			return;
		}

		$xmlPath = $this->getEbillXmlPath($invoice);
		if (!empty($xmlPath)) {
			$this->xmlFullPath = $xmlPath;
			$this->xmlFilename = basename($this->xmlFullPath);
			Billrun_Factory::log("eBill Plugin: Uploading confirmed invoice XML from: " . $this->xmlFullPath, Zend_Log::INFO);
			$this->uploadToSftp();
		} else {
			Billrun_Factory::log("eBill Plugin: No 'invoice_swiss_ebill_xml' path found for invoice confirmation, eBill invoice was not uploaded to SFTP", Zend_Log::ALERT);
		}
	}

	public function beforeInvoiceConfirmed($bill, $invoice, &$should_be_confirmed)
	{
		if (!$this->shouldGenerateInvoice($invoice)) {
			return;
		}
			
		if (empty($this->getEbillXmlPath($invoice))) {
			$should_be_confirmed = false;
			Billrun_Factory::log("eBill Plugin: Blocking invoice confirmation. 'invoice_swiss_ebill_xml' path is missing for invoice ID: " . ($invoice['invoice_id'] ?? 'unknown'), Zend_Log::ERR);
		}
	}

	protected function populateFromConfig($config, $dataSource)
	{
		$populatedData = is_array($config) ? $config : [];
		$plugin = $this;
		$isOptionalString = $this->string_keys;
		$isOptionalKey = $this->optional_keys;

		array_walk_recursive($populatedData, function (&$value, $key) use ($dataSource, $plugin, $isOptionalString, $isOptionalKey) {
			$foundValue = Billrun_Util::getIn($dataSource, $value);
			if ($foundValue !== null) {
				$value = $foundValue;
				return;
			}
			if (isset($isOptionalString[$key])) {
				return;
			}
			if (isset($isOptionalKey[$key])) {
				$value = '';
				return;
			}
			Billrun_Factory::log("eBill Plugin: Data path missing for tag '$key'. Path: '$value'", Zend_Log::ALERT);
			$plugin->abortGeneration = true;
		});
		return $populatedData;
	}

	public function addLineItemToEbill($usage)
	{
		if (empty($this->line_item_template) || !is_array($this->line_item_template)) {
			Billrun_Factory::log("eBill Plugin: Missing or invalid 'line_item_template' configuration.", Zend_Log::ALERT);
			$this->abortGeneration = true;
			return;
		}

		$this->lineItemCounter++;
		$lineItemId = (string)$this->lineItemCounter;
		$lineItemData = $this->populateFromConfig($this->line_item_template, $usage);
		$type = Billrun_Util::getIn($usage, 'type');
		$usaget = Billrun_Util::getIn($usage, 'usaget');
		if ($type === 'service' || ($type === 'flat')) {
			$lineItemData['ProductID'] = Billrun_Util::getIn($usage, 'name');
		} elseif ($usaget === 'discount') {
			$lineItemData['ProductID'] = Billrun_Util::getIn($usage, 'key');
		} else {
			$lineItemData['ProductID'] = Billrun_Util::getIn($usage, 'arate_key');
		}
		$usageSid = Billrun_Util::getIn($usage, 'sid', '1');
		if ($usageSid === 0 || $usageSid === '0') {
			$lineItemData['LineItemType'] = 'GLOBALALLOWANCEANDCHARGE';
		} else {
			$lineItemData['LineItemType'] = 'NORMAL';
		}
		$lineItemData['Tax']['TaxDetail'] = $this->getCalculatedLineItemTaxDetails($usage);
		$lineItemData['LineItemID'] = $lineItemId;
		$lineItemsContainer = Billrun_Util::getIn($this->xmlInvoice, 'Body.Bill.LineItems') ?: [];

		if (!isset($lineItemsContainer['LineItem'])) {
			$lineItemsContainer['LineItem'] = [];
		}
		$lineItemsContainer['LineItem'][] = $lineItemData;
		Billrun_Util::setIn($this->xmlInvoice, 'Body.Bill.LineItems', $lineItemsContainer);
	}

	protected function getCalculatedLineItemTaxDetails($usage)
	{
		$sid = $usage['sid'] ?? 'unknown';

		$aprice = Billrun_Util::getIn($usage, 'aprice', null);
		if ($aprice === null) {
			Billrun_Factory::log("eBill Plugin ABORT: Line Item for sid: '$sid' is missing mandatory field 'aprice'.", Zend_Log::ALERT);
			$this->abortGeneration = true;
			return [];
		}

		$rawTaxes = Billrun_Util::getIn($usage, 'tax_data.taxes', []);

		if (empty($rawTaxes)) {
			Billrun_Factory::log("eBill Plugin ABORT: Line Item for sid: '$sid' is missing 'tax_data.taxes' entry.", Zend_Log::ALERT);
			$this->abortGeneration = true;
			return [];
		}

		$taxDetailsList = [];

		foreach ($rawTaxes as $index => $taxItem) {
			$rate = Billrun_Util::getIn($taxItem, 'tax', null);
			$amount = Billrun_Util::getIn($taxItem, 'amount', null);

			if ($rate === null) {
				Billrun_Factory::log("eBill Plugin ABORT: Line Item for sid: '$sid' (Tax index $index) is missing tax rate field ('tax').", Zend_Log::ALERT);
				$this->abortGeneration = true;
				return [];
			}

			if ($amount === null) {
				Billrun_Factory::log("eBill Plugin ABORT: Line Item for sid: '$sid' (Tax index $index) is missing tax amount field ('amount').", Zend_Log::ALERT);
				$this->abortGeneration = true;
				return [];
			}

			$baseInclusive = $aprice + $amount;

			$taxDetailsList[] = [
				'Rate' => $rate * 100,
				'Amount' => $amount,
				'BaseAmountExclusiveTax' => $aprice,
				'BaseAmountInclusiveTax' => $baseInclusive
			];
		}

		return $taxDetailsList;
	}

	protected function buildBillSummary($accountBillrun)
	{
		$summaryData = $this->populateFromConfig($this->bill_summary_template, $accountBillrun);
		$pastBalance = Billrun_Util::getIn($accountBillrun, 'totals.past_balance.after_vat', 0);
		if ($pastBalance < 0) {
			$summaryData['TotalAmountDue'] = Billrun_Util::getIn($accountBillrun, 'totals.current_balance.after_vat');
		} else {
			$summaryData['TotalAmountDue'] = Billrun_Util::getIn($accountBillrun, 'totals.after_vat');
		}
		$aid = $accountBillrun['aid'] ?? 'unknown';
		$totals_before_vat = Billrun_Util::getIn($accountBillrun, 'totals.before_vat', null);

		if ($totals_before_vat === null) {
			Billrun_Factory::log("eBill Plugin ABORT: Bill Summary for AID '$aid' is missing mandatory field 'totals.before_vat'.", Zend_Log::ALERT);
			$this->abortGeneration = true;
			return;
		}

		$rawTaxes = Billrun_Util::getIn($accountBillrun, 'totals.tax_data.taxes', []);

		if (empty($rawTaxes)) {
			Billrun_Factory::log("eBill Plugin ABORT: Bill Summary for AID '$aid' is missing 'totals.tax_data.taxes' entry.", Zend_Log::ALERT);
			$this->abortGeneration = true;
			return;
		}

		$taxDetailsList = [];

		foreach ($rawTaxes as $index => $taxItem) {
			$rate = Billrun_Util::getIn($taxItem, 'tax', null);
			$amount = Billrun_Util::getIn($taxItem, 'amount', null);

			if ($rate === null) {
				Billrun_Factory::log("eBill Plugin ABORT: Bill Summary for AID '$aid' (Tax index $index) is missing tax rate field ('tax').", Zend_Log::ALERT);
				$this->abortGeneration = true;
				return;
			}

			if ($amount === null) {
				Billrun_Factory::log("eBill Plugin ABORT: Bill Summary for AID '$aid' (Tax index $index) is missing tax amount field ('amount').", Zend_Log::ALERT);
				$this->abortGeneration = true;
				return;
			}
			$baseInclusive = $totals_before_vat + $amount;

			$taxDetailsList[] = [
				'Rate' => $rate * 100,
				'Amount' => $amount,
				'BaseAmountExclusiveTax' => $totals_before_vat,
				'BaseAmountInclusiveTax' => $baseInclusive
			];
		}

		$summaryData['Tax']['TaxDetail'] = $taxDetailsList;

		Billrun_Util::setIn($this->xmlInvoice, 'Body.Bill.Summary', $summaryData);
	}

	protected function injectAppendix(SimpleXMLElement $xml, $accountBillrun)
	{
		$pdfPath = Billrun_Util::getIn($accountBillrun, 'invoice_file', '');

		if (empty($pdfPath) || !file_exists($pdfPath)) {
			Billrun_Factory::log("eBill Plugin ABORT: Invoice PDF missing or not found at path: '$pdfPath'", Zend_Log::ALERT);
			$this->abortGeneration = true;
			return;
		}

		$pdfContent = @file_get_contents($pdfPath);
		if ($pdfContent === false) {
			Billrun_Factory::log("eBill Plugin ABORT: Failed to read invoice PDF content at path: '$pdfPath'", Zend_Log::ALERT);
			$this->abortGeneration = true;
			return;
		}

		$base64 = base64_encode($pdfContent);

		if (!isset($xml->Body)) {
			$xml->addChild('Body');
		}

		$appendix = $xml->Body->addChild('Appendix');
		$document = $appendix->addChild('Document');
		$document->addAttribute('MimeType', 'x-Application/PDFAppendix');

		$domNode = dom_import_simplexml($document);
		$domNode->textContent = $base64;
	}

	protected function shouldGenerateInvoice($accountBillrun)
	{
		$rules = $this->should_generate_ebill;

		if (empty($rules) || empty($rules['path'])) {
			return false;
		}

		$path = $rules['path'];
		$actualValue = Billrun_Util::getIn($accountBillrun, $path);
		if (!empty($rules['is_boolean'])) {
			return (bool) $actualValue;
		}

		$expectedValue = isset($rules['value']) ? $rules['value'] : null;
		return $actualValue == $expectedValue;
	}


	public function processResponseStatusFiles()
	{
		$host = $this->response_sftp_host;
		$user = $this->response_sftp_user;
		$password = $this->response_sftp_password;
		$remoteDirConf = $this->response_sftp_remote_directory;

		if (empty($host) || empty($user)) {
			Billrun_Factory::log("eBill Plugin: Response SFTP configuration missing (host or user). Skipping response files processing.", Zend_Log::ERR);
			return;
		}

		$auth = ['password' => $password];
		$connection = null;
		try {
			$connection = new Billrun_Ssh_Seclibgateway($host, $auth, []);
			Billrun_Factory::log("eBill Plugin: Connecting to Response SFTP server: " . $host, Zend_Log::DEBUG);

			if (!$connection->connect($user)) {
				Billrun_Factory::log("eBill Plugin: Response SFTP connection failed. Skipping response files processing.", Zend_Log::ERR);
				return;
			}

			$remoteDir = !empty($remoteDirConf) ? rtrim($remoteDirConf, '/') : '';
			$listDir = $remoteDir !== '' ? $remoteDir : '.';
			$entries = $connection->getListOfFiles($listDir, false);

			if (empty($entries)) {
				Billrun_Factory::log("eBill Plugin: No response status files found on SFTP at " . $listDir, Zend_Log::INFO);
				return;
			}

			$today = date('Y-m-d');
			$retentionMonths = $this->response_retention_months > 0 ? $this->response_retention_months : 6;
			$cutoffTimestamp = strtotime('-' . $retentionMonths . ' months');

			foreach ($entries as $entry) {
				$fileName = basename($entry);
				$remoteFilePath = $remoteDir !== '' ? $remoteDir . '/' . $fileName : $fileName;

				if (preg_match('/\.(\d{4}-\d{2}-\d{2})$/', $fileName, $matches)) {
					$processedTimestamp = strtotime($matches[1]);
					if ($processedTimestamp !== false && $processedTimestamp < $cutoffTimestamp) {
						if ($connection->deleteFile($remoteFilePath)) {
							Billrun_Factory::log("eBill Plugin: Deleted processed response file older than " . $retentionMonths . " months from SFTP: " . $fileName, Zend_Log::INFO);
						} else {
							Billrun_Factory::log("eBill Plugin: Failed to delete old processed file from SFTP: " . $fileName, Zend_Log::NOTICE);
						}
					}
					continue;
				}

				if (!preg_match('/\.xml$/i', $fileName)) {
					continue;
				}
				$xmlContent = $connection->getString($remoteFilePath);
				if (empty($xmlContent)) {
					Billrun_Factory::log("eBill Plugin: Failed to download response status file: " . $remoteFilePath, Zend_Log::ERR);
					continue;
				}
				$this->processSingleResponseStatusFile($xmlContent, $fileName);
				$newRemotePath = $remoteFilePath . '.' . $today;
				if (!$connection->renameFile($remoteFilePath, $newRemotePath)) {
					Billrun_Factory::log("eBill Plugin: Failed to mark processed status file with date on SFTP: " . $fileName, Zend_Log::NOTICE);
				}
			}
		} catch (Exception $e) {
			Billrun_Factory::log("eBill Plugin: Response SFTP exception: " . $e->getMessage(), Zend_Log::ALERT);
		} finally {
			if ($connection !== null) {
				$connection->disconnect();
			}
		}
	}

	protected function processSingleResponseStatusFile($xmlContent, $fileName)
	{
		Billrun_Factory::log("eBill Plugin: Processing response status file: " . $fileName, Zend_Log::INFO);

		try {
			$xml = new SimpleXMLElement($xmlContent);

			if (isset($xml->Body->DeliveryDate)) {
				foreach ($xml->Body->DeliveryDate as $deliveryDate) {
					if (isset($deliveryDate->OK_Result->Bill)) {
						foreach ($deliveryDate->OK_Result->Bill as $bill) {
							$this->updateBillrunStatus($bill, 'OK_Signed', $fileName);
						}
					}

					if (isset($deliveryDate->NOK_Result->Bill)) {
						foreach ($deliveryDate->NOK_Result->Bill as $bill) {
							$this->updateBillrunStatus($bill, 'NOK_Result', $fileName);
						}
					}
				}
			}

			if (isset($xml->Body->RejectedBills->Bill)) {
				foreach ($xml->Body->RejectedBills->Bill as $bill) {
					$this->updateBillrunStatus($bill, 'Rejected', $fileName);
				}
			}
		} catch (Exception $e) {
			Billrun_Factory::log("eBill Plugin Error: processing response status file " . $fileName . ": " . $e->getMessage(), Zend_Log::ERR);
		}
	}

	protected function updateBillrunStatus($billElement, $statusType, $fileName = '')
	{
		$transactionIdStr = (string)$billElement->TransactionID;
		if ($transactionIdStr === '') {
			return;
		}

		$billrunWrapperOptions = ['invoice_id' => (int)$transactionIdStr];
		$billrunWrapper = Billrun_Billrun::getInstance($billrunWrapperOptions);
		if (!$billrunWrapper) {
			Billrun_Factory::log("eBill Plugin: Invoice ID " . $transactionIdStr . " not found in DB. Skipping status update.", Zend_Log::ERR);
			return;
		}

		$fieldName  = 'ebill_swiss_responses';

		$responseList = $billrunWrapper->getPluginField($this->plugin_name, $fieldName);

		if (!is_array($responseList)) {
			$responseList = [];
		}

		foreach ($responseList as $item) {
			if (isset($item['file_name']) && $item['file_name'] === $fileName) {
				return;
			}
		}

		$responseObj = [
			'status_type'      => $statusType,
			'scanned_on'       => new Mongodloid_Date(),
			'transaction_id'   => $transactionIdStr,
			'ebill_account_id' => (string)$billElement->EBillAccountID,
			'esr_reference'    => (string)$billElement->ESRReference,
			'total_amount'     => (string)$billElement->TotalAmount,
			'reason_code'      => (string)$billElement->ReasonCode,
			'reason_text'      => (string)$billElement->ReasonText,
			'date'             => (string)$billElement->Date,
			'file_name'        => $fileName
		];

		$responseObj = array_filter($responseObj, function ($value) {
			return !is_null($value) && $value !== '';
		});

		$responseList[] = $responseObj;
		$billrunWrapper->setPluginField($this->plugin_name, $fieldName, $responseList);
		if (!$billrunWrapper->save()) {
			Billrun_Factory::log("eBill Plugin Error: Failed to update status for Invoice ID " . $transactionIdStr, Zend_Log::ERR);
		}
	}

	/**
	 * “Converts the configuration array (that was meant to keep tag order for xml invoice) into a flat structure.
	 */
	protected function flattenOrderedConfig($config)
	{
		if (empty($config) || !is_array($config)) {
			return $config;
		}

		$flattened = [];
		foreach ($config as $item) {
			if (is_array($item)) {
				$flattened = array_merge($flattened, $item);
			}
		}
		return $flattened;
	}

	/**
	 * Helper to extract the eBill XML path from the plugins structure.
	 */
	protected function getEbillXmlPath($invoice)
	{
		return Billrun_Billrun::getPluginFieldFromBillrunObject(
            $invoice, 
            'eBillSwitzerland', 
            'invoice_swiss_ebill_xml'
        );
	}

	/**
	 * Validates that the sum of the line items' aprice equals the invoice's totals.before_vat
	 * @param array $accountBillrun
	 * @return bool True if valid, false if there is a mismatch
	 */
	protected function validateInvoiceTotalCost($accountBillrun, $lines)
	{
		$totalLineItemsAprice = 0;
		if (!empty($lines) && is_array($lines)) {
			foreach ($lines as $line) {
				$totalLineItemsAprice += (float)Billrun_Util::getIn($line, 'aprice', 0);
			}
		}

		$totalsBeforeVat = (float)Billrun_Util::getIn($accountBillrun, 'totals.before_vat', 0);

		// Compare using rounding to avoid floating-point precision issues
		if (round($totalLineItemsAprice, 5) !== round($totalsBeforeVat, 5)) {
			$aid = $accountBillrun['aid'];
			Billrun_Factory::log("eBill Plugin: invoice total cost (totals.before_vat: {$totalsBeforeVat}) does not match eBill total cost (sum of aprice: {$totalLineItemsAprice}). Aborting XML generation for AID: {$aid}", Zend_Log::ALERT);
			return false;
		}

		return true;
	}
	
}

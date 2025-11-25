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

	/**
	 * Holds the structured data for the XML invoice.
	 * @var array
	 */
	protected $xmlInvoice;

	/**
	 * Holds the final output path for the XML file.
	 * @var string
	 */
	protected $xmlOutputPath;

	/**
	 * Holds the final filename for the XML file, captured from billrun object.
	 * @var string
	 */
	protected $xmlFilename;
	protected $header_values;
	protected $delivery_info;
	protected $bill_headers;
	protected $address;
	protected $sftp_host;
	protected $sftp_user;
	protected $sftp_password;
	protected $sftp_remote_directory;
	protected $xmlFullPath;
	protected $lineItemCounter;
    protected $line_item_template;
	protected $string_keys;
	protected $bill_summary_template;
	protected $abortGeneration = false;

	public function __construct($options = [])
	{	
		$this->abortGeneration = false;
		$this->xmlInvoice = [];
		$this->xmlOutputPath = '';
		$this->xmlFilename = '';
		$this->line_item_template = 0;
		$this->header_values = Billrun_Util::getIn($options, "header_values", false);
		if (is_array($this->header_values)) {
			Billrun_Util::setIn($this->xmlInvoice, 'Header', $this->header_values);
		} else {
			Billrun_Util::setIn($this->xmlInvoice, 'Header', []);
		}
		Billrun_Util::setIn($this->xmlInvoice, 'Body', []);
		$this->delivery_info = Billrun_Util::getIn($options, "delivery_info", false);
		Billrun_Util::setIn($this->xmlInvoice, 'Body.DeliveryInfo', []);
		$this->bill_headers = Billrun_Util::getIn($options, "bill_headers", false);
		Billrun_Util::setIn($this->xmlInvoice, 'Body.Bill.Header', []);
		Billrun_Util::setIn($this->xmlInvoice, 'Body.Bill.LineItems', []);
		$this->bill_summary_template = Billrun_Util::getIn($options, "bill_summary_template", false);
        Billrun_Util::setIn($this->xmlInvoice, 'Body.Bill.Summary', []);
		$this->address = Billrun_Util::getIn($options, "address", false);
		$this->sftp_host = Billrun_Util::getIn($options, "sftp_host", "");
		$this->sftp_user = Billrun_Util::getIn($options, "sftp_user", "");
		$this->sftp_password = Billrun_Util::getIn($options, "sftp_password", "");
		$this->sftp_remote_directory = Billrun_Util::getIn($options, "sftp_remote_directory", "");
		$this->line_item_template = Billrun_Util::getIn($options, "line_item_template", false);
		$string_keys_array = Billrun_Util::getIn($options, "string_keys", false);
		if (is_array($string_keys_array)) {
			// Turn it into a map for lookup
			$this->string_keys = array_flip($string_keys_array);
		}

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
                "title" => "Whitelisted Static String Keys",
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
				"title" => "Address for receiver type",
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
				"field_name" => "sftp_host",
				"title" => "SFTP Host",
				"editable" => true,
				"display" => true,
				"nullable" => false,
			],
			[
				"type" => "string",
				"field_name" => "sftp_user",
				"title" => "SFTP Username",
				"editable" => true,
				"display" => true,
				"nullable" => false,
			],
			[
				"type" => "string",
				"field_name" => "sftp_password",
				"title" => "SFTP Password",
				"editable" => true,
				"display" => true,
				"nullable" => false,
			],
			[
				"type" => "string",
				"field_name" => "sftp_remote_directory",
				"title" => "SFTP Remote Directory",
				"editable" => true,
				"display" => true,
				"nullable" => false,
			]
		];
	}

	/**
	 * Adds a field and value to the XML invoice data array using dot notation.
	 * Triggered by: 'addFieldToEbillInvoice'
	 *
	 * @param string $path The dot-separated path (e.g., "Header.InvoiceID").
	 * @param mixed $value The value to associate with the field.
	 */
	public function addFieldToEbillInvoice($path, $value)
	{
		Billrun_Util::setIn($this->xmlInvoice, $path, $value);
	}



	/**
	 */
	public function afterGeneratorEntity($generator, $accountBillrun, $lines)
	{
		$this->generateAndOutputXml($generator, $accountBillrun, $lines);
	}

	protected function generateAndOutputXml($generator, $accountBillrun, $lines)
	{
		$exportDir = $generator->getExportDirectory();
		$baseDir = dirname(rtrim($exportDir, '/\\'));
		$billrunKey = isset($accountBillrun['billrun_key']) ? $accountBillrun['billrun_key'] : 'unknown_key';
		$this->xmlOutputPath = $baseDir . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'ebillSwitzerland' . DIRECTORY_SEPARATOR . $billrunKey . DIRECTORY_SEPARATOR;

		if (isset($accountBillrun['file_name'])) {
			$baseName = preg_replace('/\.[^.]+$/', '', $accountBillrun['file_name']);
			$this->xmlFilename = $baseName . '.zip'; // Outer ZIP file
			$innerXmlFilename = $baseName . '.xml';  // Inner XML file
		} else {
			$this->xmlFilename = 'unknown-invoice.zip';
			$innerXmlFilename = 'unknown-invoice.xml';
			Billrun_Factory::log("Ebill Plugin: 'accountBillrun' did not contain file_name. Using default.", Zend_Log::WARN);
		}

		$this->buildDeliveryInfo($accountBillrun);
		$this->buildBillHeader($accountBillrun);
		$this->buildBillSummary($accountBillrun);
		$this->formatInvoice();


		// Build the full path and filename
		$fullXmlPath = $this->xmlOutputPath . $this->xmlFilename;
		$this->xmlFullPath = $fullXmlPath;


		if (empty($this->xmlOutputPath) || empty($this->xmlFilename)) {
			Billrun_Factory::log("Ebill Plugin Error: Output path or filename is not set. Path: '{$this->xmlOutputPath}', Filename: '{$this->xmlFilename}'", Zend_Log::ERR);
			return;
		}

		if (!is_dir($this->xmlOutputPath)) {
			if (!mkdir($this->xmlOutputPath, 0777, true)) {
				Billrun_Factory::log("Ebill Plugin Error: Failed to create output directory: " . $this->xmlOutputPath, Zend_Log::ERR);
				return;
			}
		}

		try {
			// XML Generation logic
			if ($this->abortGeneration) {
				Billrun_Factory::log("Ebill Plugin ABORTED: Cannot generate XML due to missing critical data (see previous logs).", Zend_Log::ALERT);
				return;
			}
			$xml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><Envelope type=\"string\"></Envelope>");
			$this->arrayToXml($this->xmlInvoice, $xml);
			$dom = dom_import_simplexml($xml)->ownerDocument;
			$dom->formatOutput = true;
			$xmlString = $dom->saveXML();
			$zip = new ZipArchive();
			$opened = $zip->open($this->xmlFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

			if ($opened !== true) {
				Billrun_Factory::log("Ebill Plugin Error: Could not open ZIP file at: " . $this->xmlFullPath . " (Code: $opened)", Zend_Log::ERR);
			} else {
				// Add the XML string content as a file inside the ZIP
				$zip->addFromString($innerXmlFilename, $xmlString);
				if ($zip->close()) {
					Billrun_Factory::log("Ebill Plugin: ZIP xml invoice saved to: " . $this->xmlFullPath, Zend_Log::INFO);

					Billrun_Factory::db()->billrunCollection()->update(
						[
							'aid' => $accountBillrun['aid'],
							'billrun_key' => $accountBillrun['billrun_key']
						],
						['$set' => ['invoice_swiss_ebill_xml' => $this->xmlFullPath]]
					);
				} else {
					Billrun_Factory::log("Ebill Plugin Error: Failed to close/save ZIP file at: " . $this->xmlFullPath, Zend_Log::ERR);
				}
			}
		} catch (Exception $e) {
			Billrun_Factory::log("Ebill Plugin Error: Error generating XML/ZIP: " . $e->getMessage(), Zend_Log::ERR);
		}
	}

	/**
	 * The small, private helper function that recursively
	 * builds the XML structure from the array.
	 *
	 * @param array $array The data array
	 * @param SimpleXMLElement $xml The XML element to append children to
	 */
	private function arrayToXml($array, &$xml)
	{
		foreach ($array as $key => $value) {

			// Rule 1: Key is a non-numeric string (a tag name)
			if (!is_numeric($key)) {

				if (($key === 'LineItem' ) && is_array($value)) {
					// The value is the array list of item objects [0 => item1, 1 => item2, ...]
					// Loop through the list to create a clean, single tag for each.
					foreach ($value as $itemData) {
						// Add a new 'LineItem' tag for each element in the list.
						$child = $xml->addChild($key);
						// Recurse into the item's data. This level of recursion is now correct.
						$this->arrayToXml($itemData, $child);
					}
					continue; // Skip the generic rules below for this key
				}

				// --- GENERIC RULES (for all other non-repeating tags) ---
				if (is_array($value)) {
					$child = $xml->addChild($key);
					$this->arrayToXml($value, $child);
				} else {
					$xml->addChild($key, htmlspecialchars($value ?? ''));
				}
			} else {
				// Rule 2: Key is numeric (This handles the indices inside the list, 
				// but this block should rarely be hit if the structure is defined correctly.)
				if (is_array($value)) {
					// Recurse to flatten the structure if needed (e.g., skips array index)
					$this->arrayToXml($value, $xml);
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

	protected function formatInvoice()
	{
		$this->formatDates();
		$this->formatVat();
	}

	protected function uploadToSftp()
	{
		$localFilePath = $this->xmlFullPath;
		$fileName = $this->xmlFilename;

		$host = $this->sftp_host;
		$user = $this->sftp_user;
		$password = $this->sftp_password;
		$remoteDirConf = $this->sftp_remote_directory;

		if (empty($host) || empty($user)) {
			Billrun_Factory::log("Ebill Plugin: SFTP configuration missing (host or user). Skipping upload.", Zend_Log::WARN);
			return;
		}

		if (empty($localFilePath) || empty($fileName)) {
			Billrun_Factory::log("Ebill Plugin: Missing file path or name for SFTP upload.", Zend_Log::WARN);
			return;
		}

		$auth = [
			'password' => $password
		];

		try {
			$connection = new Billrun_Ssh_Seclibgateway($host, $auth, []);
			Billrun_Factory::log("Ebill Plugin: Connecting to SFTP server: " . $host, Zend_Log::INFO);

			if (!$connection->connect($user)) {
				Billrun_Factory::log("Ebill Plugin: SFTP Connection failed.", Zend_Log::ALERT);
				return;
			}

			$remoteDir = !empty($remoteDirConf) ? rtrim($remoteDirConf, '/') : '';

			// Create remote directory if it doesn't exist
			if (!empty($remoteDir)) {
				if (!$connection->getConnection()->is_dir($remoteDir)) {
					Billrun_Factory::log("Ebill Plugin: Remote directory does not exist. Creating: " . $remoteDir, Zend_Log::DEBUG);
					$connection->getConnection()->mkdir($remoteDir, 0770, true);
				}
				$remotePath = $remoteDir . '/' . $fileName;
			} else {
				$remotePath = $fileName; // Upload to root if no dir specified
			}

			Billrun_Factory::log("Ebill Plugin: Uploading " . $fileName . " to " . $remotePath, Zend_Log::INFO);

			$response = $connection->put($localFilePath, $remotePath);

			if ($response) {
				Billrun_Factory::log("Ebill Plugin: Uploaded " . $fileName . " successfully.", Zend_Log::INFO);
			} else {
				Billrun_Factory::log("Ebill Plugin: Failed to upload " . $fileName, Zend_Log::ALERT);
			}
		} catch (Exception $e) {
			Billrun_Factory::log("Ebill Plugin: SFTP Error: " . $e->getMessage(), Zend_Log::ERR);
		}
	}

	public function afterInvoiceConfirmed($bill, $invoice)
	{
		if (isset($invoice['invoice_swiss_ebill_xml']) && !empty($invoice['invoice_swiss_ebill_xml'])) {

			$this->xmlFullPath = $invoice['invoice_swiss_ebill_xml'];
			$this->xmlFilename = basename($this->xmlFullPath);
			Billrun_Factory::log("Ebill Plugin: Uploading confirmed invoice XML from: " . $this->xmlFullPath, Zend_Log::INFO);
			$this->uploadToSftp();
		} else {
			Billrun_Factory::log("Ebill Plugin: No 'invoice_swiss_ebill_xml' path found for invoice confirmation.", Zend_Log::DEBUG);
		}
	}

	public function beforeInvoiceConfirmed($bill, $invoice, &$should_be_confirmed)
	{
		if (empty($invoice['invoice_swiss_ebill_xml'])) {
			$should_be_confirmed = false;
			Billrun_Factory::log("Ebill Plugin: Blocking invoice confirmation. 'invoice_swiss_ebill_xml' path is missing for invoice ID: " . ($invoice['invoice_id'] ?? 'unknown'), Zend_Log::ERR);
		}
	}

	protected function populateFromConfig($config, $dataSource)
	{
		$populatedData = is_array($config) ? $config : [];
		$plugin = $this; 
        $isOptionalString = $this->string_keys;

		array_walk_recursive($populatedData, function (&$value, $key) use ($dataSource, $plugin, $isOptionalString) {
			if(isset($isOptionalString[$key])){
				return;
			}
			$foundValue = Billrun_Util::getIn($dataSource, $value);

			if ($foundValue === null) {
				Billrun_Factory::log("Ebill Plugin: Data path missing for tag '$key'. Path: '$value'", Zend_Log::CRIT);
				$plugin->abortGeneration = true;
			} else {
				$value = $foundValue;
			}
		});
		return $populatedData;
	}

public function addLineItemToEbill($usage)
    {
        if (empty($this->line_item_template) || !is_array($this->line_item_template)) {
            Billrun_Factory::log("Ebill Plugin Error: Missing or invalid 'line_item_template' configuration.", Zend_Log::ERR);
            return;
        }

        $this->lineItemCounter++;
        $lineItemId = (string)$this->lineItemCounter;
        $lineItemData = $this->populateFromConfig($this->line_item_template, $usage);
        
        // Conditional Logic
        $usageSid = Billrun_Util::getIn($usage, 'sid', '1');
        
        if ($usageSid === 0 || $usageSid === '0') {
            $lineItemData['LineItemType'] = 'GLOBALALLOWANCEANDCHARGE';
        } else {
            $lineItemData['LineItemType'] = 'NORMAL';
        }
        $lineItemData['Tax']['TaxDetail'] = $this->getCalculatedTaxDetails($usage);
        $lineItemData['LineItemID'] = $lineItemId;
        $lineItemsContainer = Billrun_Util::getIn($this->xmlInvoice, 'Body.Bill.LineItems') ?: [];
        
        if (!isset($lineItemsContainer['LineItem'])) {
            $lineItemsContainer['LineItem'] = [];
        }
        $lineItemsContainer['LineItem'][] = $lineItemData;
        Billrun_Util::setIn($this->xmlInvoice, 'Body.Bill.LineItems', $lineItemsContainer);
    }

	protected function getCalculatedTaxDetails($usage)
	{
		$sid = $usage['sid'] ?? 'unknown';

		$aprice = Billrun_Util::getIn($usage, 'aprice', null);
		if ($aprice === null) {
			Billrun_Factory::log("Ebill Plugin ABORT: Line Item '$sid' is missing mandatory field 'aprice'.", Zend_Log::CRIT);
			$this->abortGeneration = true;
			return [];
		}

		$rawTaxes = Billrun_Util::getIn($usage, 'tax_data.taxes', []);

		if (count($rawTaxes) > 1) {
			Billrun_Factory::log("Ebill Plugin ABORT: Line Item '$sid' has multiple taxes. Only one tax is allowed per line.", Zend_Log::CRIT);
			$this->abortGeneration = true;
			return [];
		}

		if (empty($rawTaxes) || !isset($rawTaxes[0])) {
			Billrun_Factory::log("Ebill Plugin ABORT: Line Item '$sid' is missing 'tax_data.taxes' entry.", Zend_Log::CRIT);
			$this->abortGeneration = true;
			return [];
		}

		$taxItem = $rawTaxes[0];
		$rate = Billrun_Util::getIn($taxItem, 'tax', null);
		$amount = Billrun_Util::getIn($taxItem, 'amount', null);

		if ($rate === null) {
			Billrun_Factory::log("Ebill Plugin ABORT: Line Item '$sid' is missing tax rate field ('tax_data.taxes.0.tax').", Zend_Log::CRIT);
			$this->abortGeneration = true;
			return [];
		}

		if ($amount === null) {
			Billrun_Factory::log("Ebill Plugin ABORT: Line Item '$sid' is missing tax amount field ('tax_data.taxes.0.amount').", Zend_Log::CRIT);
			$this->abortGeneration = true;
			return [];
		}

		$baseInclusive = $aprice + $amount;

		return [[
			'Rate' => $rate,
			'Amount' => $amount,
			'BaseAmountExclusiveTax' => $aprice,
			'BaseAmountInclusiveTax' => $baseInclusive
		]];
	}

	protected function buildBillSummary($accountBillrun)
	{
		// 1. Populate from Config
		// This keeps "TotalAmountDue" => "TODO" (because it is Whitelisted)
		// This keeps "Tax" => "CALCULATED_BY_BILLRUN" (temporary)
		$summaryData = $this->populateFromConfig($this->bill_summary_template, $accountBillrun);

		// 2. Overwrite Tax Structure
		// We delete the "CALCULATED_BY_BILLRUN" string and insert the array.
		$summaryData['Tax'] = [
			'TaxDetail' => [], // Empty list, ready for your logic later
			'TotalTax' => Billrun_Util::getIn($accountBillrun, 'totals.vat', 0)
		];

		// 3. Save to XML
		Billrun_Util::setIn($this->xmlInvoice, 'Body.Bill.Summary', $summaryData);
	}
}

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
 * @since    5.16
 */
class israelInvoicePlugin extends Billrun_Plugin_BillrunPluginBase {
	
	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'israelInvoice';

    /**
     * @var boolean
     */
    protected $valid_config = null;

    /**
     * access token cache
     * @var Billrun_Cache
     */
    protected $cache = null;

    /**
     * @var boolean
     */
    protected $use_cache = true;
    protected const ACCESS_TOKEN_CACHE_KEY = "israel_invoice_access_token";
    /**
     * Invoices approval refresh token
     * @var string
     */
    protected $refresh_token;
    
    /**
     * Invoice amount thresholds and dates
     * @var array
     */
    protected $approval_amount_thresholds;

    /**
     * Should cancel invoice generation if an error occurred
     * @var boolean
     */
    protected $cancel_invoice_generation_on_error;

    /**
     * Keeps the government invoices approval api address
     * @var string
     */
    protected $invoice_approval_api;
	
    /**
     * Keeps the government access token api addess
     * @var string
     */
    protected $new_access_token_api;

    /**
     * Keeps the company vat registration number
     * @var int
     */
    protected $company_vat_number;

    /**
     * Keeps the business registration number
     * @var int
     */
    protected $union_vat_number;

    /**
     * customer field name
     * @var string
     */
    protected $account_vat_number_field_name;

    /**
     * @var int
     */
    protected $accounting_software_number;

    /**
     * Invoice approval should be only for accounts with account vat number field mapped
     * @var boolean
     */
    protected $approve_accounts_with_vat_number_field;

    /**
     * @var boolean
     */
    protected $apply_to_refund_invoices;

    /**
     * Tax application key & secret
     * @var string
     */
    protected $client_key;
    protected $client_secret;
    protected $plugin_configuration = null;

	public function __construct($options = array()) {
        $this->refresh_token = Billrun_Util::getIn($options, "refresh_token", "");
        $this->client_key = Billrun_Util::getIn($options, "client_key", "");
        $this->client_secret = Billrun_Util::getIn($options, "client_secret", "");
        $this->approval_amount_thresholds = $this->getApprovalAmountThresholds($options);
        $this->cancel_invoice_generation_on_error = Billrun_Util::getIn($options, "cancel_invoice_generation_on_error", true);
        $this->new_access_token_api = Billrun_Util::getIn($options, "access_token_api", "https://ita-api.taxes.gov.il/shaam/tsandbox/longtimetoken/oauth2/token");
        $this->invoice_approval_api = Billrun_Util::getIn($options, "invoice_approval_api", "https://ita-api.taxes.gov.il/shaam/tsandbox/Invoices/v1/Approval");
        $this->company_vat_number = Billrun_Util::getIn($options, "company_vat_number", 0);
        $this->union_vat_number = Billrun_Util::getIn($options, "union_vat_number", 0);
        $this->account_vat_number_field_name = $this->getAccountNumberFieldName($options);
        $this->accounting_software_number = Billrun_Util::getIn($options, "accounting_software_number", 99999999);
        $this->apply_to_refund_invoices = Billrun_Util::getIn($options, "apply_to_refund_invoices", false);
        $this->approve_accounts_with_vat_number_field = Billrun_Util::getIn($options, "approve_accounts_with_vat_number_field", true);
	}

    public function getApprovalAmountThresholds($options) {
        $config_thresholds = Billrun_Util::getIn($options, "invoice_thresholds", null);
        if (empty($config_thresholds)) {
            return array([
                    'amount' => 25000,
                    'from' => '2024-05-01T00:00:00',
                    'to' => '2025-01-01T00:00:00'
                ],[
                    'amount' => 20000,
                    'from' => '2025-01-01T00:00:00',
                    'to' => '2026-01-01T00:00:00'
                ],[
                    'amount' => 15000,
                    'from' => '2026-01-01T00:00:00',
                    'to' => '2027-01-01T00:00:00'
                ],[
                    'amount' => 10000,
                    'from' => '2027-01-01T00:00:00',
                    'to' => '2028-01-01T00:00:00'
                ],[
                    'amount' => 5000,
                    'from' => '2028-01-01T00:00:00',
                    'to' => '2029-01-01T00:00:00'
                ]
            );
        }
        return $config_thresholds;
    }

    public function getAccountNumberFieldName($options) {
        return Billrun_Util::getIn($options, "account_vat_number_field", 
            Billrun_Util::getIn($options, "account_corporate_number_field", Billrun_Util::getIn($options, "account_id_number_field", "account_vat_number")));
    }

    public function checkConfigurationValidation() {
        if (!is_null($this->valid_config)) {
            return $this->valid_config;
        }
        if($this->isEnabled() && empty($this->refresh_token) || empty($this->client_key) ||  empty($this->client_secret) || empty($this->company_vat_number) || empty($this->union_vat_number) || empty($this->accounting_software_number)) {
            throw new Exception("Missing Israel invoice plugin configuration");
        } else {
            $this->valid_config = true;
        }
    }

    /**
	 * Function to get invoice approval number, if needed, and recreate the invoice again, with it
	 * @param array $invoice_bill - invoice bill data
     * @param array $invoice_data - invoice billrun data
	 */
	public function beforeInvoiceConfirmed(&$invoice_bill, $invoice_data, &$should_be_confirmed) {
        $this->checkConfigurationValidation();
        try {
            $inv_id = $invoice_bill['invoice_id'];
            if (!$this->invoiceNeedsApproval($invoice_data, $invoice_bill)) {
                Billrun_Factory::log("Israel Invoice:invoice " . $inv_id . " shouldn't get approval number", Zend_Log::DEBUG);
                return;
            } else {
                Billrun_Factory::log("Israel Invoice:invoice " . $inv_id . " should get approval number", Zend_Log::DEBUG);
            }
            Billrun_Factory::log("Trying to get access token", Zend_Log::DEBUG);
            list($response, $success, $api_output) = $this->getAccessToken();
            $access_token = $response;
            if (!$success) {
                throw new Exception("Couldn't get access token, error-" . json_encode($response) . " - " . $api_output);
            }
            Billrun_Factory::log("Israel Invoice:build invoice " . $inv_id . " approval API request body", Zend_Log::DEBUG);
            $request = $this->buildRequest($invoice_bill, $invoice_data);
            Billrun_Factory::log("Israel Invoice:build invoice " . $inv_id . " approval API curl object", Zend_Log::DEBUG);
            list($curl, $request_data) = $this->buildCurl($request, $access_token);
            Billrun_Factory::log("Israel Invoice:Run approval API with data params - " . $request_data, Zend_Log::DEBUG);
            list($response, $api_output) = $this->getInvoiceApproval($curl);
            Billrun_Factory::log("Israel Invoice:Received approval API response for invoice " . $inv_id . "- " . json_encode($response), Zend_Log::DEBUG);
            if ($this->validateApprovalResponse($response)) {
                Billrun_Factory::log("Israel Invoice:Approval API response is valid for invoice " . $inv_id, Zend_Log::DEBUG);
                $invoice_data['invoice_confirmation_number'] = $response['Confirmation_Number'];
                Billrun_Factory::log("Saving confirmation number to the billrun object, for invoice " . $inv_id, Zend_Log::DEBUG);
                $this->updateBillrunObject($invoice_data);
                Billrun_Factory::log("Regenerating invoice file for invoice " . $inv_id, Zend_Log::DEBUG);
                $this->recreateInvoiceFile($invoice_data);
            } else {
                Billrun_Factory::log("Israel Invoice:Approval API response is not valid for invoice " . $inv_id . ". Headers output - " . $api_output, Zend_Log::DEBUG);
                if ($this->cancel_invoice_generation_on_error) {
                    throw new Exception("Israel Invoice:invoice " . $inv_id . " will not be confirmed. Headers output - " . $api_output);
                }
            }
        } catch (Exception $ex) {
			Billrun_Factory::log("Israel Invoice:invoice " . $inv_id . " will not be confirmed. Error " . $ex->getCode() . ": " . $ex->getMessage(), Zend_Log::ALERT);
			$should_be_confirmed = false;
			return;
		}
	}

    public function invoiceNeedsApproval($invoice_data) {
        Billrun_Factory::log("Israel Invoice:check if invoice " . $invoice_data['invoice_id'] . " needs an approval number", Zend_Log::DEBUG);
        if ($this->apply_to_refund_invoices && ($invoice_data['totals']['before_vat'] > 0)) {
            Billrun_Factory::log("Invoice " . $invoice_data['invoice_id'] . " didn't pass the 'refund' check", Zend_Log::DEBUG);
            return false;
        }
        $invoice_tax = 0;
        //Check if all the invoice items are non taxable (non-vatable)
        foreach ($invoice_data['totals']['taxes'] as $tax_key => $tax_amount) {
            $invoice_tax += abs($tax_amount);
        }
        if ($invoice_tax == 0) {
            Billrun_Factory::log("Invoice " . $invoice_data['invoice_id'] . " didn't pass the 'tax' check", Zend_Log::DEBUG);
            return false;
        }
        $relative_date = $this->getInvoiceRelativeDate($invoice_data);
        $found_relevant_threshold = false;
        foreach ($this->approval_amount_thresholds as $index => $threshold) {
            $from = strtotime($threshold['from']);
            $to =  strtotime($threshold['to']);
            if (($index == 0) && ($relative_date < $from)) {
                Billrun_Factory::log("Didn't find relevant threshold dates&amount for invoice " . $invoice_data['invoice_id'] . " relative date", Zend_Log::ERR);
                return false;
            }
            if (($from <= $relative_date) && ($to > $relative_date)) {
                $found_relevant_threshold = true;
                if ($invoice_data['totals']['before_vat'] < $threshold['amount']) {
                    Billrun_Factory::log("Invoice " . $invoice_data['invoice_id'] . " didn't pass the 'threshold' check", Zend_Log::DEBUG);
                    return false;
                }
            }
        }
        if (!$found_relevant_threshold) {
            Billrun_Factory::log("Didn't find relevant threshold dates&amount for invoice " . $invoice_data['invoice_id'] . " relative date", Zend_Log::ERR);
            return false;
        }

        if ($this->approve_accounts_with_vat_number_field && is_null(Billrun_Util::getIn($invoice_data, 'attributes.' . $this->account_vat_number_field_name, null))) {
            Billrun_Factory::log("Invoice " . $invoice_data['invoice_id'] . " didn't pass the 'vat_number_field' exists check", Zend_Log::DEBUG);
            return false;
        }

        $this->cache = Billrun_Factory::cache();
        if (empty($this->cache)) {
            throw new Exception("Couldn't create Billrun cache for israel invoice access token");
        }

        return true;
    }

    public function getAccessToken() {
        if ($this->use_cache && !empty($this->cache)) {
            Billrun_Factory::log("Trying to pull access token from cache", Zend_Log::DEBUG);
            $access_token = $this->cache->get($this->getAccessTokenCacheKey());
            if (!empty($access_token)) {
                Billrun_Factory::log("Successfully pulled access token from cache", Zend_Log::DEBUG);
                return [$access_token, true, ""];
            } else {
                Billrun_Factory::log("Coludn't pull access token from cache. Trying via access token API", Zend_Log::ERR);
            }
        }
        // Build the POST data
        $postData = array(
            'client_id' => $this->client_key,
            'client_secret' => $this->client_secret,
            'scope' => "scope",
            'refresh_token' => $this->refresh_token,
            'grant_type' => "refresh_token"
        );

        // Initialize cURL session
        $ch = curl_init($this->new_access_token_api);

        // Set cURL options
        $api_output = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $api_output);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

        Billrun_Factory::log("Trying to get new access token, using refresh token that starts with " . substr($this->refresh_token, 0, 10), Zend_Log::DEBUG);
        // Execute cURL request
        $response = json_decode(curl_exec($ch), true);
        rewind($api_output);
        $output = stream_get_contents($api_output);
        $valid_access_token = false;
        if (isset($response['access_token'])) {
            $this->setRefreshToken($response['refresh_token']);
            $response = $response['access_token'];
            $this->cache->set(self::ACCESS_TOKEN_CACHE_KEY, $response, null, 13800);
            $valid_access_token = true;
        }
        // Close cURL session
        curl_close($ch);
        return [$response,$valid_access_token,$output];
    }

    public function getAccessTokenCacheKey() {
        return self::ACCESS_TOKEN_CACHE_KEY;
    }
    public function setRefreshToken($token) {
        $config = new ConfigModel();			
        if (is_null($this->plugin_configuration)) {
            $this->plugin_configuration = current(array_filter($config->getFromConfig('plugins',[]), function ($plugin) {
                return isset($plugin['name']) && ($plugin['name'] === 'israelInvoicePlugin');
            }));
        }
        $this->plugin_configuration['configuration']['values']['refresh_token'] = $token;
        $config->updateConfig('plugin', $this->plugin_configuration);
    }

    /**
     * Function to build approval API request body
     * @param array $invoice_bill - invoice bill data
     * @param array $invoice_data - invoice billrun data
     */
    public function buildRequest($invoice_bill, $invoice_data) {
        $customer_vat_number = Billrun_Util::getIn($invoice_data, 'attributes.' . $this->account_vat_number_field_name, null);
        $amount_before_discount = round($invoice_data['totals']['after_vat'] - $invoice_data['totals']['discount']['after_vat'],2);
        $request = [
            "Invoice_ID" => strval($invoice_bill['invoice_id']), //BillRun invoice id
            "Invoice_Type" => 305, //tax invoice code
            "Vat_Number" => $this->company_vat_number,
            "Invoice_Date" => date('Y-m-d', $invoice_bill['invoice_date']->sec),
            "Invoice_Issuance_Date" => date('Y-m-d', $invoice_data['confirmation_time']->sec),
            "Accounting_Software_Number" => $this->accounting_software_number,
            "Amount_Before_Discount" => round($amount_before_discount, 2),
            "Discount" => round($invoice_data['totals']['discount']['after_vat'], 2),
            "Payment_Amount_Including_VAT" => round($invoice_bill['due'], 2),
            "Payment_Amount" => round($invoice_bill['due_before_vat'], 2),
            "VAT_Amount" => round($invoice_bill['due'] - $invoice_bill['due_before_vat'], 2),
            "Union_Vat_Number" => $this->union_vat_number,
            "Invoice_Reference_Number" => strval($invoice_bill['invoice_id']),
            "Customer_VAT_Number" => $customer_vat_number
        ];
        return $request;
    }

    /**
     * Function to run invoice approval API
     * @param $curl
     * Returns API response
     */
    public function getInvoiceApproval($curl) {
        $verbose_output = fopen('php://temp', 'w+');
        curl_setopt($curl, CURLOPT_STDERR, $verbose_output);
        // Execute cURL request
        $response = curl_exec($curl);
        rewind($verbose_output);
        $output_contents = stream_get_contents($verbose_output);

        // Close cURL session
        curl_close($curl);
        return [json_decode($response, true), $output_contents];
    }

    /**
     * Function to build approval API curl object
     * @param array $request - request body
     * Returns curl object
     */
    public function buildCurl($request, $access_token) {
        // Initialize cURL session
        $curl = curl_init();

        // Set the URL
        curl_setopt($curl, CURLOPT_URL, $this->invoice_approval_api);

        // Set request type to POST
        curl_setopt($curl, CURLOPT_POST, true);

        //Set headers
        $headers = array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $access_token
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        // Set request body
        $data = $request;
        ini_set('serialize_precision','-1');
        $data_string = json_encode($data, JSON_PRESERVE_ZERO_FRACTION);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);

        // Set option to receive response as a string
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_VERBOSE, true);
    
        return [$curl,$data_string];
    }

    public function validateApprovalResponse($response) {
        if (isset($response['Status']) && ($response['Status'] == 200) && ($response['Confirmation_Number'] !== 0)) {
            return true;
        }
        return false;
    }

    public function updateBillrunObject($invoice_data) {
        Billrun_Factory::db()->billrunCollection()->update(array('invoice_id' => $invoice_data['invoice_id'],'billrun_key' => $invoice_data['billrun_key'], 'aid' => $invoice_data['aid']), array('$set'=> array('invoice_confirmation_number' => $invoice_data['invoice_confirmation_number'])));
    }

    public function recreateInvoiceFile($invoice_data) {
        Billrun_Factory::log()->log("Trying to regenerate invoice " . $invoice_data['invoice_id'], Zend_Log::DEBUG);
        $options = [
            'stamp' => $invoice_data['billrun_key'],
            'accounts' => strval($invoice_data['aid']),
            'type' => 'invoice_export'
        ];
        try{
            $generator = Billrun_Generator::getInstance($options);
        } catch(Exception $ex){
            Billrun_Factory::log()->log('Something went wrong while building the generator. Invoice ' . $invoice_data['invoice_id'] . " wasn't regenerated", Zend_Log::ALERT);
            return false;
        }
        if (!$generator) {
            Billrun_Factory::log()->log('Generator cannot be loaded, invoice ' . $invoice_data['invoice_id'] . " wasn't regenerated", Zend_Log::ALERT);
            return false;
        }
        Billrun_Factory::log()->log('Generator loaded to regenerate invoice ' . $invoice_data['invoice_id'], Zend_Log::DEBUG);
        $res = $generator->regenerateInvoice();
        if (!$res) {
            Billrun_Factory::log()->log("Invoice " . $invoice_data['invoice_id'] . " couldn't be re-generate", Zend_Log::ALERT);
            return false;
        }
        return true;
    }


    /**
     * Plugin configuration fields
     */
    public function getConfigurationDefinitions() {
		return [
            [
				"type" => "string",
				"field_name" => "client_key",
				"title" => "Taxes API client key",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
			], [
				"type" => "string",
				"field_name" => "client_secret",
				"title" => "Taxes API client secret",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
            ], [
				"type" => "string",
				"field_name" => "refresh_token",
				"title" => "Invoice approval API refresh token",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
			], [
                "type" => "json",
                "field_name" => "invoice_thresholds",
                "title" => "Invoice threshold amounts by date rang",
                "editable" => true,
                "display" => true,
                "nullable" => false,
                "default" => json_encode("[{'amount':25000, 'from':'2024-05-01T00:00:00', 'to':'2224-05-01T00:00:00'}]")
            ], [
				'type' => 'boolean',
				'field_name' => 'cancel_invoice_generation_on_error',
				'title' => 'Cancel invoice generation on error',
				'mandatory' => false,
				'editable' => true,
				'display' => true,
				'nullable' => false,
				'default' => true
			], [
				'type' => 'boolean',
				'field_name' => 'apply_to_refund_invoices',
				'title' => 'Approve refund invoices',
				'mandatory' => false,
				'editable' => true,
				'display' => true,
				'nullable' => false,
				'default' => false
			], [
				"type" => "string",
				"field_name" => "invoice_approval_api",
				"title" => "Approval API",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true,
                "default_value" => "https://ita-api.taxes.gov.il/shaam/tsandbox/Invoices/v1/Approval"
			], [
				"type" => "string",
				"field_name" => "new_access_token_api",
				"title" => "Access token API",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true,
                "default_value" => "https://openapi.taxes.gov.il/shaam/tsandbox/longtimetoken/oauth2/token"
			], [
				"type" => "number",
				"field_name" => "company_vat_number",
				"title" => "Company VAT number",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
			], [
				"type" => "number",
				"field_name" => "union_vat_number",
				"title" => "Union VAT number",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
			], [
				"type" => "boolean",
				"field_name" => "approve_accounts_with_vat_number_field",
				"title" => "Approve only accounts with vat number field",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => false,
                "default_value" => true
			], [
				"type" => "string",
				"field_name" => "account_vat_number_field",
				"title" => "Account vat number field name",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => false
            ], [
				"type" => "string",
				"field_name" => "account_corporate_number_field",
				"title" => "Account corporate number field name",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => false
            ], [
				"type" => "string",
				"field_name" => "account_id_number_field",
				"title" => "Account id number field name",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => false
            ], [
				"type" => "number",
				"field_name" => "accounting_software_number",
				"title" => "Accounting software number",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true,
                "default_value" => 99999999
			]
		];
	}

    public function getInvoiceRelativeDate($invoice_data) {
        return $invoice_data['invoice_date']->sec;
    }
}
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
        $this->approval_amount_thresholds = Billrun_Util::getIn($options, "invoice_thresholds", null);
        $this->cancel_invoice_generation_on_error = Billrun_Util::getIn($options, "cancel_invoice_generation_on_error", true);
        $this->new_access_token_api = Billrun_Util::getIn($options, "access_token_api", "https://openapi.taxes.gov.il/shaam/tsandbox/longtimetoken/oauth2/token");
        $this->invoice_approval_api = Billrun_Util::getIn($options, "invoice_approval_api", "https://ita-api.taxes.gov.il/shaam/tsandbox/Invoices/v1/Approval");
        $this->company_vat_number = Billrun_Util::getIn($options, "company_vat_number", 0);
        $this->union_vat_number = Billrun_Util::getIn($options, "union_vat_number", 0);
        $this->account_vat_number_field_name = Billrun_Util::getIn($options, "account_vat_number_field", "account_vat_number");
        $this->accounting_software_number = Billrun_Util::getIn($options, "accounting_software_number", 99999999);
        $this->apply_to_refund_invoices = Billrun_Util::getIn($options, "apply_to_refund_invoices", false);
        $this->checkConfigurationValidation();
	}

    public function checkConfigurationValidation() {
        if(empty($this->refresh_token) || empty($this->client_key) ||  empty($this->client_secret) || empty($this->company_vat_number) || empty($this->union_vat_number) || empty($this->accounting_software_number)) {
            throw new Exception("Missing Israel invoice plugin configuration");
        }
    }

    /**
	 * Function to get invoice approval number, if needed, and recreate the invoice again, with it
	 * @param array $invoice_bill - invoice bill data
     * @param array $invoice_data - invoice billrun data
	 */
	public function beforeInvoiceConfirmed(&$invoice_bill, $invoice_data) {
        try {
            $inv_id = $invoice_bill['invoice_id'];
            if (!$this->invoiceNeedsApproval($invoice_data)) {
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
                Billrun_Factory::log("Israel Invoice:Approval API response is valid for invoice " . $inv_id . ". Regenerating invoice file", Zend_Log::DEBUG);            
                $invoice_data['invoice_confirmation_number'] = $response['Confirmation_Number'];
                $this->recreateInvoiceFile($invoice_data);
            } else {
                Billrun_Factory::log("Israel Invoice:Approval API response is not valid for invoice " . $inv_id . ". Headers output - " . $api_output, Zend_Log::DEBUG);
                if ($this->cancel_invoice_generation_on_error) {
                    throw new Exception("Israel Invoice:invoice " . $inv_id . " will not be confirmed. Headers output - " . $api_output);
                }
            }
        } catch (Exception $ex) {
			Billrun_Factory::log("Israel Invoice:invoice " . $inv_id . " will not be confirmed. Error " . $ex->getCode() . ": " . $ex->getMessage(), Zend_Log::ALERT);
			$invoice_bill['should_be_confirmed'] = false;
			return;
		}
	}

    public function invoiceNeedsApproval($invoice_data) {
        Billrun_Factory::log("Israel Invoice:check if invoice " . $invoice_data['invoice_id'] . " needs an approval number", Zend_Log::DEBUG);
        if (!$this->apply_to_refund_invoices && ($invoice_data['totals']['before_vat'] < 0)) {
            return false;
        }
        $invoice_tax = 0;
        //Check if all the invoice items are non taxable (non-vatable)
        foreach ($invoice_data['totals']['taxes'] as $tax_key => $tax_amount) {
            $invoice_tax += $tax_amount;
        }
        if ($invoice_tax == 0) {
            return false;
        }
        $relative_date = $this->getInvoiceRelativeDate($invoice_data);
        foreach ($this->approval_amount_thresholds as $threshold) {
            $from = strtotime($threshold['from']);
            $to =  strtotime($threshold['to']);
            if (($from <= $relative_date) && ($to > $relative_date) && !($invoice_data['totals']['before_vat'] > $threshold['amount'])) {
                return false;
            }
        }

        return true;
    }

    public function getAccessToken() {
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
            $valid_access_token = true;
        }
        // Close cURL session
        curl_close($ch);
        return [$response,$valid_access_token,$output];
    }

    public function setRefreshToken($token) {
        $config = new ConfigModel();			
        if (is_null($this->plugin_configuration)) {
            $this->plugin_configuration = current(array_filter($config->getFromConfig('plugins',[]), function ($plugin) {
                return $plugin['name'] === 'israelInvoicePlugin';
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
        $customer_vat_number = Billrun_Util::getIn($invoice_bill, 'foreign'. $this->account_vat_number_field_name, 18);
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
        return $response['Confirmation_Number'] !== 0;
    }

    public function recreateInvoiceFile($invoice_data) {
        $billrun = $invoice_data['billrun_key'];
        $basePdfGenCmd = 'php -t '.APPLICATION_PATH.'/ '.APPLICATION_PATH.'/public/index.php --env '.Billrun_Factory::config()->getEnv()." --generate --type invoice_export --stamp {$billrun}";
        exec("$basePdfGenCmd accounts={$invoice_data['aid']}");
        Billrun_Factory::db()->billrunCollection()->update(array('invoice_id' => $invoice_data['invoice_id'],'billrun_key' => $invoice_data['billrun_key'], 'aid' => $invoice_data['aid']), array('$set'=> array('invoice_confirmation_number' => $invoice_data['invoice_confirmation_number'])));
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
                "nullable" => false
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
				"type" => "string",
				"field_name" => "account_vat_number_field",
				"title" => "Account VAT number field name",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
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
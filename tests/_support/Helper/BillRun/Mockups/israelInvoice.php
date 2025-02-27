<?php
namespace Helper\BillRun\Mockups;

// here you can define custom actions
// all public methods declared in helper class will be available in $I
use Codeception\Module\REST;

class israelInvoice extends \Helper\BillRun\Mockups\Mockup
{
  public function getUrl()
  {
    return $this->getDomain() . 'plugins/israelInvoice';
  }

  public function setIsraelInvoiceSettings(\AcceptanceTester $a, $data = [])
  {
    $data = array_merge($this->InvoicePluginConfiguration(), $data);
    $a->setPluginSettings($data);
  }


  public function InvoicePluginConfiguration()
  {

    return [
      "name" => "israelInvoicePlugin",
      "enabled" => true,
      "system" => true,
      "hide_from_ui" => false,
      "configuration" =>
        [
          "values" =>
            [
              "company_vat_number" => 1,
              "client_secret" => "11",
              "client_key" => "22",
              "account_vat_number_field" => "company_id",
              "approve_accounts_with_vat_number_field" => false,
              "union_vat_number" => 12222,
              "user_id" => 234,
              "account_corporate_number_field" => "",
              "invoice_approval_api" => $this->getUrl() . "/israelInvoice/ita-.taxes.gov.il/shaam/tsandbox/Invoices/v2/Approval",
              "refresh_token" => "123456",
              "account_id_number_field" => "",
              "accounting_software_number" => 4545454,
              "new_access_token_api" => $this->getUrl() . "/israelInvoice/openapi.taxes.gov.il/shaam/tsandbox/longtimetoken/oauth2/token",
              "cancel_invoice_generation_on_error" => true,
              "access_token_api" => $this->getUrl() . "/israelInvoice/ita-api.taxes.gov.il/shaam/tsandbox/longtimetoken/oauth2/token",
              "apply_to_refund_invoices" => false
            ],
        ],
      "label" => "Israel Invoice",
    ];

  }
}




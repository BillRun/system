<?php
namespace Helper\BillRun\Mockups;

// here you can define custom actions
// all public methods declared in helper class will be available in $I
use Codeception\Module\REST;

class israelInvoice extends \Helper\BillRun\Mockups\Mockup
{
  public function getUrl()
  {
    return $this->getDomain() . 'plugins/israel-tax';
  }

  /**
   * Set the israelInvoice plugin settings.
   * $data is merged recursively, so nested overrides work, e.g.
   *   ['configuration' => ['values' => ['approve_accounts_with_vat_number_field' => true]]]
   */
  public function setIsraelInvoiceSettings(\AcceptanceTester $a, $data = [])
  {
    $data = array_replace_recursive($this->InvoicePluginConfiguration(), $data);
    $a->setPluginSettings($data);
  }

  /**
   * Make sure the account has the custom field the plugin reads the customer
   * vat number from (account_vat_number_field), so it is passed through to
   * the billrun document attributes.
   */
  public function addAccountVatNumberField(\AcceptanceTester $a, $fieldName = 'company_id')
  {
    $fields = $a->getSettings('subscribers.account', [])['details']['fields'];
    foreach ($fields as $field) {
      if ($field['field_name'] === $fieldName) {
        return;
      }
    }
    $fields[] = [
      "system" => false,
      "select_list" => false,
      "display" => true,
      "editable" => true,
      "multiple" => false,
      "searchable" => false,
      "field_name" => $fieldName,
      "unique" => false,
      "title" => $fieldName,
      "mandatory" => false,
      "type" => "text"
    ];
    $a->setSettings('subscribers.account.fields', $fields);
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

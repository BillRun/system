<?php

class IsraelInvoiceTest extends \Codeception\Test\Unit
{
   
/**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
     
        $this->tester->setIsraelInvoiceSettings( $this->tester);
        // the plugin reads the customer vat number from account field "company_id" (account_vat_number_field)
        $this->tester->addAccountVatNumberField($this->tester, 'company_id');
        $this->tester->cleanDB();
    }

    protected function _after()
    {
    }
     

    protected function createTestData($account =[],$subscriber =[],$plan =[]){
        $this->tester->generatePlan($plan);
        $plan = json_decode($this->tester->grabResponse(), true)['entity'];
        $this->tester->createAccountWithAllMandatoryCustomFields($account);
        $account = json_decode($this->tester->grabResponse(), true)['entity'];
        $subscriber =  $this->tester->generateSubscriber(
            [
                'aid' => $account['aid'],
                'plan' => $plan['name']
            ]
        );
        $subscriber = json_decode($this->tester->grabResponse(), true)['entity'];
        return ['account'=>$account,'subscriber'=>$subscriber,'plan'=>$plan];
    }
    public $defaultOptions = array(
        "type" => "customer",
        "stamp" => "202410",
        "page" => 0,
        "size" => 100,
        'fetchonly' => true,
        'generate_pdf' => 0,
        "force_accounts" => array(123)
    );

    protected $epsilon = 0.00001;
   
    //invoice price is under israel invoice threshold - shouldn't get approval number
    public function test_priceUnderThethreshold()
    {
       $data =  $this->createTestData([],[],['name' => 'PLAN_B'.time(), "price" => [
            [
                "price" => 100,
                "from" => 0,
                "to" => "UNLIMITED"
            ]
        ]]);
        $this->defaultOptions['force_accounts'] = [$data['account']['aid']];
        $this->defaultOptions["stamp"] = "202501";
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->getFromCollection('billrun', array('billrun_key' => '202501', 'aid' =>  $data['account']['aid']));
        $output = $this->tester->confirmInvoices(['stamp'=>$billrun['billrun_key'],'invoices'=>$billrun['invoice_id']]);
        $messages[0] = "Invoice {$billrun['invoice_id']} didn't pass the 'threshold' check";
        $messages[1] = "Invoice:invoice {$billrun['invoice_id']} shouldn't get approval number";
        $output->seeInShellOutput($messages[0] );
        $output->seeInShellOutput($messages[1] );
        $this->tester->seeInCollection('bills', ['billrun_key' => '202501', 'aid' =>  $data['account']['aid'],'invoice_confirmation_number'=>['$exists'=>false]]);

    }

      //invoice price is equel to the  israel invoice threshold - should get approval number
    public function test_priceBeforeVatEquelToThethreshold()
    {
       $data =  $this->createTestData([],[],['name' => 'PLAN_B'.time().time(), "price" => [
            [
                "price" => 10000,
                "from" => 0,
                "to" => "UNLIMITED"
            ]
        ]]);
        $this->defaultOptions['force_accounts'] = [$data['account']['aid']];
        $this->defaultOptions["stamp"] = "202602";
        $billrunKey =$this->defaultOptions["stamp"];
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->getFromCollection('billrun', array('billrun_key' => $billrunKey, 'aid' =>  $data['account']['aid']));
        $output = $this->tester->confirmInvoices(['stamp'=> $billrunKey ,'invoices'=>$billrun['invoice_id']]);
        $messages = [
            "Israel Invoice:build invoice {$billrun['invoice_id']} approval API request body",
            "Israel Invoice:build invoice {$billrun['invoice_id']} approval API curl object",  
            "Israel Invoice:Run approval API with data params",
            "Israel Invoice:Approval API response is valid for invoice {$billrun['invoice_id']}",
            "Saving confirmation number to the billrun object, for invoice {$billrun['invoice_id']}",
            "Regenerating invoice file for invoice {$billrun['invoice_id']}",
            "Trying to regenerate invoice {$billrun['invoice_id']}",
            "Generator loaded to regenerate invoice {$billrun['invoice_id']}"
         ];
         
         foreach($messages as $message) {
            $output->seeInShellOutput($message);
         }
         $this->tester->seeInCollection('bills', ['billrun_key' => $billrunKey, 'aid' =>  $data['account']['aid'],'invoice_confirmation_number'=>['$exists'=>true,'$ne'=>null]]);

    }

    /**
     * BRCD-4598 helpers: run a cycle + confirm for one account whose invoice is above the threshold
     */
    protected function runAndConfirmAboveThreshold($accountOverride = [])
    {
        $data = $this->createTestData($accountOverride, [], ['name' => 'PLAN_B' . strtoupper(uniqid()), "price" => [
            [
                "price" => 10000,
                "from" => 0,
                "to" => "UNLIMITED"
            ]
        ]]);
        $this->defaultOptions['force_accounts'] = [$data['account']['aid']];
        $this->defaultOptions["stamp"] = "202602";
        $billrunKey = $this->defaultOptions["stamp"];
        $this->tester->runCycle($this->defaultOptions);
        $billrun = $this->tester->getFromCollection('billrun', array('billrun_key' => $billrunKey, 'aid' => $data['account']['aid']));
        $output = $this->tester->confirmInvoices(['stamp' => $billrunKey, 'invoices' => $billrun['invoice_id']]);
        return ['aid' => $data['account']['aid'], 'billrun_key' => $billrunKey, 'billrun' => $billrun, 'output' => $output];
    }

    // BRCD-4598: approve_accounts_with_vat_number_field=true + private account (company_id exists but empty)
    // -> must NOT get an approval number
    public function test_flagTrue_privateAccountWithEmptyVatNumber_shouldNotBeApproved()
    {
        $this->tester->setIsraelInvoiceSettings($this->tester, ['configuration' => ['values' => ['approve_accounts_with_vat_number_field' => true]]]);
        $res = $this->runAndConfirmAboveThreshold(['company_id' => '']);
        $this->assertSame('', $res['billrun']['attributes']['company_id'], 'company_id should be passed through to the billrun attributes as an empty string');
        $res['output']->seeInShellOutput("Invoice {$res['billrun']['invoice_id']} didn't pass the numeric 'vat_number_field' check");
        $res['output']->seeInShellOutput("Israel Invoice:invoice {$res['billrun']['invoice_id']} shouldn't get approval number");
        $res['output']->dontSeeInShellOutput("Israel Invoice:Run approval API with data params");
        $this->tester->seeInCollection('bills', ['billrun_key' => $res['billrun_key'], 'aid' => $res['aid'], 'invoice_confirmation_number' => ['$exists' => false]]);
    }

    // BRCD-4598: approve_accounts_with_vat_number_field=true + private account (no company_id at all)
    // -> must NOT get an approval number
    public function test_flagTrue_privateAccountWithoutVatNumberField_shouldNotBeApproved()
    {
        $this->tester->setIsraelInvoiceSettings($this->tester, ['configuration' => ['values' => ['approve_accounts_with_vat_number_field' => true]]]);
        $res = $this->runAndConfirmAboveThreshold();
        $res['output']->seeInShellOutput("Invoice {$res['billrun']['invoice_id']} didn't pass the numeric 'vat_number_field' check");
        $res['output']->seeInShellOutput("Israel Invoice:invoice {$res['billrun']['invoice_id']} shouldn't get approval number");
        $this->tester->seeInCollection('bills', ['billrun_key' => $res['billrun_key'], 'aid' => $res['aid'], 'invoice_confirmation_number' => ['$exists' => false]]);
    }

    // BRCD-4598: approve_accounts_with_vat_number_field=true + business account (company_id set)
    // -> approved, and the customer vat number is sent to the tax authority
    public function test_flagTrue_businessAccountWithVatNumber_shouldBeApproved()
    {
        $this->tester->setIsraelInvoiceSettings($this->tester, ['configuration' => ['values' => ['approve_accounts_with_vat_number_field' => true]]]);
        $res = $this->runAndConfirmAboveThreshold(['company_id' => '123456789']);
        $res['output']->seeInShellOutput("Israel Invoice:invoice {$res['billrun']['invoice_id']} should get approval number");
        $res['output']->seeInShellOutput('"customer_vat_number":123456789');
        $res['output']->seeInShellOutput("Israel Invoice:Approval API response is valid for invoice {$res['billrun']['invoice_id']}");
        $this->tester->seeInCollection('bills', ['billrun_key' => $res['billrun_key'], 'aid' => $res['aid'], 'invoice_confirmation_number' => ['$exists' => true, '$ne' => null]]);
    }

    // BRCD-4598: approve_accounts_with_vat_number_field=false + private account
    // -> approved with the default private customer vat number (999999998)
    public function test_flagFalse_privateAccount_shouldBeApproved()
    {
        $this->tester->setIsraelInvoiceSettings($this->tester, ['configuration' => ['values' => ['approve_accounts_with_vat_number_field' => false]]]);
        $res = $this->runAndConfirmAboveThreshold(['company_id' => '']);
        $res['output']->seeInShellOutput("Israel Invoice:invoice {$res['billrun']['invoice_id']} should get approval number");
        $res['output']->seeInShellOutput('"customer_vat_number":999999998');
        $res['output']->seeInShellOutput("Israel Invoice:Approval API response is valid for invoice {$res['billrun']['invoice_id']}");
        $this->tester->seeInCollection('bills', ['billrun_key' => $res['billrun_key'], 'aid' => $res['aid'], 'invoice_confirmation_number' => ['$exists' => true, '$ne' => null]]);
    }

    // BRCD-4598 (MR 6962): approve_accounts_with_vat_number_field=true + non numeric company_id
    // -> must NOT get an approval number
    public function test_flagTrue_accountWithNonNumericVatNumber_shouldNotBeApproved()
    {
        $this->tester->setIsraelInvoiceSettings($this->tester, ['configuration' => ['values' => ['approve_accounts_with_vat_number_field' => true]]]);
        $res = $this->runAndConfirmAboveThreshold(['company_id' => 'AB123456']);
        $res['output']->seeInShellOutput("Invoice {$res['billrun']['invoice_id']} didn't pass the numeric 'vat_number_field' check");
        $res['output']->seeInShellOutput("Israel Invoice:invoice {$res['billrun']['invoice_id']} shouldn't get approval number");
        $res['output']->dontSeeInShellOutput("Israel Invoice:Run approval API with data params");
        $this->tester->seeInCollection('bills', ['billrun_key' => $res['billrun_key'], 'aid' => $res['aid'], 'invoice_confirmation_number' => ['$exists' => false]]);
    }

}

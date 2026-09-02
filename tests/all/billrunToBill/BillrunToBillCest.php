<?php

/**
 * BRCD-5445: confirm step (billrunToBill generator) in external subscribers mode.
 * Confirming several invoices should create a bill per account carrying the
 * account foreign fields, while loading all the accounts from the CRM in a
 * single batched gad request instead of one request per account.
 */
class BillrunToBillCest
{
    const CRM_GAD_REQUEST_LOG = 'Sending request to http://mockup:8081/crm/gad';

    protected $stamp = '202512';
    protected $planName = 'PLAN_5445';
    protected $accounts = [
        54451 => ['category' => 'CAT_54451', 'nationality' => 'NAT_54451', 'bank_name' => 'BANK_54451'],
        54452 => ['category' => 'CAT_54452', 'nationality' => 'NAT_54452', 'bank_name' => 'BANK_54452'],
    ];

    public function _before(AcceptanceTester $I)
    {
        ini_set('error_reporting', E_ALL & ~E_WARNING & ~E_NOTICE);
        $I->enableExternalModeSettings();
        $I->cleanDB();
        $this->ensureBillsForeignFieldsSettings($I);
    }

    public function _after(AcceptanceTester $I)
    {
        $I->enableDBModeSettings();
    }

    /**
     * Make sure the bills account foreign fields the test asserts on are configured.
     */
    protected function ensureBillsForeignFieldsSettings(AcceptanceTester $I)
    {
        $foreignFields = [
            [
                'field_name' => 'foreign.account.category',
                'title' => 'Customer category',
                'foreign' => ['entity' => 'account', 'field' => 'category'],
                'conditions' => [],
            ],
            [
                'field_name' => 'foreign.account.nationality',
                'title' => 'Account nationality',
                'foreign' => ['entity' => 'account', 'field' => 'nationality'],
                'conditions' => [],
            ],
            [
                'field_name' => 'foreign.account.bank_name',
                'title' => 'Bank name',
                'foreign' => ['entity' => 'account', 'field' => 'bank_name'],
                'conditions' => [],
            ],
        ];
        $fields = $I->getSettings('bills', [])['details']['fields'] ?? [];
        $existingNames = array_column($fields, 'field_name');
        $missing = array_values(array_filter($foreignFields, function ($field) use ($existingNames) {
            return !in_array($field['field_name'], $existingNames);
        }));
        if (!empty($missing)) {
            $I->setSettings('bills.fields', array_merge($fields, $missing));
        }
        Billrun_Config::getInstance()->loadDbConfig();
    }

    public function confirmTwoAccountsCreatesBillsWithSingleCrmCall(AcceptanceTester $I)
    {
        $I->generatePlan(['name' => $this->planName]);

        $cycleOptions = [
            'type' => 'customer',
            'stamp' => $this->stamp,
            'page' => 0,
            'size' => 100,
            'fetchonly' => true,
            'generate_pdf' => 0,
        ];
        // the CRM mockup serves one aid per billable request - run the cycle per account
        $invoiceIds = [];
        foreach (array_keys($this->accounts) as $aid) {
            $cycleOptions['force_accounts'] = [$aid];
            $I->runCycle($cycleOptions);
            $billrun = $I->grabFromCollection('billrun', ['billrun_key' => $this->stamp, 'aid' => $aid]);
            $I->assertNotEmpty($billrun['invoice_id'], "no invoice was generated for aid $aid");
            $invoiceIds[$aid] = $billrun['invoice_id'];
        }

        $I->clearLogFile();
        $I->confirmInvoices(['stamp' => $this->stamp, 'invoices' => implode(',', $invoiceIds)]);

        foreach ($this->accounts as $aid => $foreignValues) {
            $I->seeNumElementsInCollection('bills', 1, [
                'aid' => $aid,
                'billrun_key' => $this->stamp,
                'type' => 'inv',
                'invoice_id' => $invoiceIds[$aid],
                'foreign.account.category' => $foreignValues['category'],
                'foreign.account.nationality' => $foreignValues['nationality'],
                'foreign.account.bank_name' => $foreignValues['bank_name'],
            ]);
            $I->seeInCollection('billrun', [
                'aid' => $aid,
                'billrun_key' => $this->stamp,
                'invoice_id' => $invoiceIds[$aid],
                'billed' => 1,
            ]);
        }

        // BRCD-5445: all the accounts are loaded in one batched CRM gad request
        $I->seeCountInLogFile(self::CRM_GAD_REQUEST_LOG, 1);
        $I->seeInLogFile('"key":"aid","operator":"in","value":[' . implode(',', array_keys($this->accounts)) . ']');
    }
}

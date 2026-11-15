<?php

/**
 * BRCD-5445: confirm step (billrunToBill generator) in external subscribers mode.
 * Confirming several invoices should create a bill per account carrying the
 * account foreign fields, while loading all the accounts from the CRM in a
 * single batched gad request instead of one request per account.
 */
class BillrunToBillCest
{
    protected $stamp = '202512';
    protected $planName = 'PLAN_5445';

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

    /**
     * Build a test accounts map - the foreign values follow the crm_data fixtures
     * pattern (CAT_<aid> / NAT_<aid> / BANK_<aid>).
     */
    protected function buildAccounts(array $aids)
    {
        $accounts = [];
        foreach ($aids as $aid) {
            $accounts[$aid] = [
                'category' => 'CAT_' . $aid,
                'nationality' => 'NAT_' . $aid,
                'bank_name' => 'BANK_' . $aid,
            ];
        }
        return $accounts;
    }

    public function confirmTwoAccountsCreatesBillsWithSingleCrmCall(AcceptanceTester $I)
    {
        $accounts = $this->buildAccounts([54451, 54452]);

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
        foreach (array_keys($accounts) as $aid) {
            $cycleOptions['force_accounts'] = [$aid];
            $I->runCycle($cycleOptions);
            $billrun = $I->grabFromCollection('billrun', ['billrun_key' => $this->stamp, 'aid' => $aid]);
            $I->assertNotEmpty($billrun['invoice_id'], "no invoice was generated for aid $aid");
            $invoiceIds[$aid] = $billrun['invoice_id'];
        }

        $I->clearLogFile();
        $I->confirmInvoices(['stamp' => $this->stamp, 'invoices' => implode(',', $invoiceIds)]);

        foreach ($accounts as $aid => $foreignValues) {
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
        $I->seeCountInLogFile($I->getExternalRequestLog('account'), 1);
    }

    /**
     * BRCD-5445: the confirmation jobs flow with the accounts cache preload enabled.
     * 4 accounts with gad_limit=2 - the main Confirm job preloads the accounts in 2
     * GAD batches (2 accounts per CRM request), and each of the 4 per-account
     * Confirm_Account child jobs takes its own account from the cache: the bills
     * carry the right per-account foreign fields while the only GAD requests of the
     * whole confirmation are the 2 preload ones - none by the child jobs, although
     * every account is confirmed by a separate job.
     */
    public function confirmJobsTakeAccountsFromPreloadedCache(AcceptanceTester $I)
    {
        // in-memory config only: the cycle, the main job and the child jobs all run
        // inside the test process (like Helper\Aggregate::runCycle does), so nothing
        // is written to the config collection. the effective values are captured
        // first and restored on finally - all the tests share one PHP process
        $config = \Billrun_Factory::config();
        $originalConfig = [
            'subscribers.account.gad_limit' => $config->getConfigValue('subscribers.account.gad_limit', 5000, 'int'),
            'subscribers.account.external_cache_enabled' => $config->getConfigValue('subscribers.account.external_cache_enabled', false),
            'billrun.confirm.accounts_cache.enabled' => $config->getConfigValue('billrun.confirm.accounts_cache.enabled', false),
            'billrun.confirm.cache.gad.prefix' => $config->getConfigValue('billrun.confirm.cache.gad.prefix', ''),
            'worker.enabled' => $config->getConfigValue('worker.enabled', false),
        ];
        $config->setConfigValue('subscribers.account.gad_limit', 2);
        $config->setConfigValue('subscribers.account.external_cache_enabled', true);
        $config->setConfigValue('billrun.confirm.accounts_cache.enabled', true);
        $config->setConfigValue('billrun.confirm.cache.gad.prefix', 'confirmed_'); // confirmation cache namespace
        $config->setConfigValue('worker.enabled', true); // the confirmation jobs mode
        $cache = \Billrun_Factory::cache();
        $I->assertNotEmpty($cache, 'the general cache is not configured in this environment');
        // accounts cached by a previous run would hide the preload GAD requests
        $cache->clean();
        try {
            $this->confirmViaJobsAndAssert($I);
        } finally {
            // nothing may leak out of this test: drop leftover queue messages (a failed
            // assertion aborts the drain loop mid-way), the cached accounts, and return
            // the config overrides to the values they had before
            \Billrun_Factory::db()->getCollection('jobs_messages')->remove(['_id' => ['$exists' => true]]);
            $cache->clean();
            foreach ($originalConfig as $key => $value) {
                $config->setConfigValue($key, $value);
            }
            // the account singleton keeps the last cache prefix set on it - return
            // it to the regular one
            if (method_exists(\Billrun_Factory::account(), 'setCachePrefix')) {
                \Billrun_Factory::account()->setCachePrefix($config->getConfigValue('subscribers.account.external_cache_prefix', 'ext_acc_'));
            }
        }
    }

    protected function confirmViaJobsAndAssert(AcceptanceTester $I)
    {
        $accounts = $this->buildAccounts([54451, 54452, 54453, 54454]);

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
        foreach (array_keys($accounts) as $aid) {
            $cycleOptions['force_accounts'] = [$aid];
            $I->runCycle($cycleOptions);
            $billrun = $I->grabFromCollection('billrun', ['billrun_key' => $this->stamp, 'aid' => $aid]);
            $I->assertNotEmpty($billrun['invoice_id'], "no invoice was generated for aid $aid");
            $invoiceIds[$aid] = $billrun['invoice_id'];
        }

        $I->clearLogFile();
        // the confirmation jobs flow: push the main Confirm job and drain the jobs
        // queue synchronously - the same execution the jobs worker performs
        \Billrun_Factory::db()->getCollection('jobs_messages')->remove(['_id' => ['$exists' => true]]);
        \Billrun_Jobsmanager::getInstance()->push('Confirm', ['billrun_key' => $this->stamp]);
        $executedJobs = 0;
        while ($executedJobs < 10 && ($job = \Billrun_Jobsmanager::getInstance()->pull()) !== false) {
            $executedJobs++;
            $job->execute(['config' => (array) $job->config]);
        }
        $I->assertEquals(5, $executedJobs, 'expected a main Confirm job and a Confirm_Account child job per account');

        // every child job created its account bill with the right per-account foreign
        // fields - the account data was taken from the cache (see the GAD counts below)
        foreach ($accounts as $aid => $foreignValues) {
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

        // gad_limit=2 over 4 accounts: the main job preloaded 2 GAD batches, each CRM
        // request carrying 2 accounts as per-aid queries (the form the cache is keyed by)
        $I->seeCountInLogFile('Preloading 2 accounts to the confirmation cache', 2);
        $I->seeCountInLogFile('Preloaded 2 accounts to the confirmation cache', 2);
        foreach (array_keys($accounts) as $aid) {
            $I->seeInLogFile('"key":"aid","operator":"equal","value":' . $aid);
        }
        // the main job created a child job per account, each child ran on its own
        // account and loaded it from the cache - one cache hit per account (each
        // carrying its own per-aid query), and the 2 preload requests are the only
        // GAD requests of the whole confirmation
        foreach (array_keys($accounts) as $aid) {
            $I->seeInLogFile('Going to create job confirm account ' . $aid);
            $I->seeInLogFile('confirm cycle start for ' . $aid);
            $I->seeInLogFile('Loaded external data from cache (confirmed_) for query params: [{"key":"aid","operator":"equal","value":' . $aid . '}]');
        }
        $I->seeCountInLogFile('Going to create job confirm account', 4);
        $I->seeCountInLogFile('Loaded external data from cache', 4);
        $I->seeCountInLogFile($I->getExternalRequestLog('account'), 2);
    }
}

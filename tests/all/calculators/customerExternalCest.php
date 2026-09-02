<?php

/**
 * external-DB variant of customerCest.
 *
 */
class customerExternalCest
{
    public static $isIPSet = false;

    public function _before(ApiTester $I)
    {
        if (!self::$isIPSet) {
            $this->setUP($I);
            self::$isIPSet = true;
            Billrun_Config::getInstance()->loadDbConfig();
        }
        $I->cleanDB();
        
        $I->enableExternalModeSettings();
        $I->resetBillrunInstances();
    }

    public function _after(ApiTester $I)
    {
        // enableExternalModeSettings writes to the config collection; revert
        // so the next test in the suite is not affected.
        $I->enableDBModeSettings();
    }

    protected function setUP(ApiTester $I, $inputProcessor = null)
    {
        $inputProcessor = $inputProcessor ?: $this->inputProcessor;
        $I->setSettings('file_types', $inputProcessor);
        $I->setSettings('usage_types', [
            [
                "usage_type" => "call",
                "label" => "call",
                "property_type" => "time",
                "invoice_uom" => "seconds",
                "input_uom" => "seconds",
            ],
        ]);
        Billrun_Factory::config()->setConfigValue('queue.calculators', ["customer", "rate", "pricing"]);
    }

    /**
     * BRCD-4564 in external-DB mode: processing two files in one run with
     * the Customer calculator in bulk mode against the external CRM. The
     * second file's subscribers must trigger a fresh loadSubscribers (GSD)
     * call; before the fix the cached subscriber map from file 1 was reused.
     */
    public function testCustomerBulkModeExternalDbProcessingTwoFiles(ApiTester $I): void
    {
        // Bulk + external scoped to this test only. Use overrideConfigValue
        // so the flag actually lands in the config collection — setConfigValue
        // alone only sets in-memory and isn't visible when running the suite.
        $I->overrideConfigValue('customer.calculator.bulk', true);

        // Plan referenced by the BRCD-4564 CRM fixtures must exist locally
        // so the customer calc's plan_ref lookup resolves.
        $I->generatePlan(['name' => 'PLAN_BRCD4564', 'from' => '2025-01-01']);

        $I->generateRate([
            'tariff_category' => 'retail',
            'key' => 'CALL',
            'from' => '2025-01-01',
            'rates' => [
                'call' => [
                    'BASE' => [
                        'rate' => [
                            [
                                'from' => 0,
                                'to' => 'UNLIMITED',
                                'interval' => 1,
                                'price' => 1,
                                'uom_display' => [
                                    'range' => 'seconds',
                                    'interval' => 'seconds',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        // Single processor run over a directory of CSV files; the cached
        // Customer calc singleton is reused across them and must reload
        // subscribers from the CRM for each file. The CSVs are shared with
        // customerCest — both Cests identify subscribers by firstname.
        $I->processByPath([
            'type' => 'customerExternalCest',
            'path' => 'tests/all/calculators/test_files/brcd4564',
        ]);

        // File 1 — three CDRs for firstname 0531234567, resolved to the CRM
        // fixture at crm_data/45640001.json (aid 45640001 / sid 45640001).
        $I->assertEquals(3, $I->grabCollectionCount('lines', [
            'aid' => 45640001,
            'sid' => 45640001,
        ]), 'file 1 lines should be enriched with the CRM subscriber for 0531234567');

        // File 2 — two CDRs for firstname 0539999999, resolved to
        // crm_data/45640002.json. Regression assertion for BRCD-4564: before
        // the fix these were 0 because the bulk subscriber cache from file 1
        // was reused and the second file's GSD lookup was skipped.
        $I->assertEquals(2, $I->grabCollectionCount('lines', [
            'aid' => 45640002,
            'sid' => 45640002,
        ]), 'file 2 lines should be enriched with the CRM subscriber for 0539999999 (BRCD-4564)');

        // No CDR from either file should be left without subscriber enrichment.
        $I->assertEquals(0, $I->grabCollectionCount('lines', [
            'uf.firstname' => ['$in' => ['0531234567', '0539999999']],
            'sid' => ['$exists' => false],
        ]), 'no line should be left without sid (BRCD-4564)');
        $I->overrideConfigValue('customer.calculator.bulk', false);

    }

    public $inputProcessor = [
        "file_type" => "customerExternalCest",
        "parser" => [
            "type" => "separator",
            "line_types" => [
                "H" => "/^none$/",
                "D" => "//",
                "T" => "/^none$/",
            ],
            "separator" => ",",
            "structure" => [
                ["name" => "firstname", "checked" => true],
                ["name" => "date", "checked" => true],
                ["name" => "rate", "checked" => true],
                ["name" => "volume", "checked" => true],
            ],
            "csv_has_header" => true,
            "csv_has_footer" => false,
        ],
        "processor" => [
            "type" => "Usage",
            "date_field" => "date",
            "default_usaget" => "call",
            "default_unit" => "seconds",
            "default_volume_src" => ["volume"],
        ],
        "customer_identification_fields" => [
            "call" => [
                [
                    "target_key" => "firstname",
                    "src_key" => "firstname",
                    "conditions" => [["field" => "usaget", "regex" => "/.*/"]],
                    "clear_regex" => "//",
                ],
            ],
        ],
        "rate_calculators" => [
            "retail" => [
                "call" => [
                    [
                        [
                            "type" => "match",
                            "rate_key" => "key",
                            "line_key" => "rate",
                        ],
                    ],
                ],
            ],
        ],
        "pricing" => ["call" => []],
        "enabled" => true,
        "filters" => [],
        "receiver" => [
            "type" => "ftp",
            "connections" => [
                [
                    "receiver_type" => "ftp",
                    "passive" => false,
                    "delete_received" => false,
                    "user" => "admin",
                    "password" => "12345678",
                    "host" => "127.0.0.1",
                    "name" => "a",
                    "remote_directory" => "/home",
                ],
            ],
        ],
    ];
}

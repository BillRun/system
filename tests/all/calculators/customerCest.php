<?php

/**
 * DB variant of customerCest
 */
class customerCest
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
        $I->resetBillrunInstances();
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
     * BRCD-4564: processing two files in one run with the Customer calculator
     * in bulk mode. Subscribers of the second file must be loaded fresh;
     * before the fix loadSubscribers() was skipped on file 2 because the
     * calc's $subscribers cache was already populated from file 1, so file
     * 2's CDRs failed customer enrichment.
     */
    public function testCustomerBulkModeProcessingTwoFiles(ApiTester $I): void
    {
        // Bulk mode is only required for this scenario — keep it scoped here
        // so the rest of the suite is not affected.
        $I->overrideConfigValue('customer.calculator.bulk', true);

        $I->generatePlan(['name' => 'TEST_PLAN_BRCD4564_' . microtime(true) * 10000, 'from' => '2025-01-01']);
        $planDetails = json_decode($I->grabResponse(), true)['entity'];

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

        $I->createAccountWithAllMandatoryCustomFields(['firstname' => 'accountA_brcd4564']);
        $accountA = json_decode($I->grabResponse(), true)['entity'];

        $I->generateSubscriber([
            'from' => '2025-01-01',
            'firstname' => '0531234567',
            'aid' => $accountA['aid'],
            'plan' => $planDetails['name'],
        ]);
        $subscriberA = json_decode($I->grabResponse(), true)['entity'];

        $I->createAccountWithAllMandatoryCustomFields(['firstname' => 'accountB_brcd4564']);
        $accountB = json_decode($I->grabResponse(), true)['entity'];

        $I->generateSubscriber([
            'from' => '2025-01-01',
            'firstname' => '0539999999',
            'aid' => $accountB['aid'],
            'plan' => $planDetails['name'],
        ]);
        $subscriberB = json_decode($I->grabResponse(), true)['entity'];

        // Single processor run over a directory of CSV files; the cached
        // Customer calc singleton is reused across them.
        $I->processByPath([
            'type' => 'customerCest',
            'path' => 'tests/all/calculators/test_files/brcd4564',
        ]);

        // File 1 — three CDRs for subscriber A, all enriched with A's aid/sid.
        $I->assertEquals(3, $I->grabCollectionCount('lines', [
            'aid' => $accountA['aid'],
            'sid' => $subscriberA['sid'],
        ]), 'file 1 lines should be enriched with subscriber A');

        // File 2 — two CDRs for subscriber B. Regression assertion for
        // BRCD-4564: before the fix these were 0 because the bulk subscriber
        // cache from file 1 was reused and B's stamps did not match.
        $I->assertEquals(2, $I->grabCollectionCount('lines', [
            'aid' => $accountB['aid'],
            'sid' => $subscriberB['sid'],
        ]), 'file 2 lines should be enriched with subscriber B (BRCD-4564)');

        // No CDR from either file should be left without subscriber enrichment.
        $I->assertEquals(0, $I->grabCollectionCount('lines', [
            'uf.firstname' => ['$in' => ['0531234567', '0539999999']],
            'sid' => ['$exists' => false],
        ]), 'no line should be left without sid (BRCD-4564)');
        $I->overrideConfigValue('customer.calculator.bulk', false);

    }

    public $inputProcessor = [
        "file_type" => "customerCest",
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

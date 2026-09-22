<?php

/**
 * teldasFullProcessCest
 *
 * Full-pipeline test (real input processor -> teldas plugin hooks -> queue
 * calculators -> lines) for a zero-duration call to a teldas INA number that
 * has NO tariff (status ALLNO, tariffProfile null). The line must be mapped to
 * ina_vas_call, rated with the teldas rate (not the generic one), saved to the
 * queue, and produce no alerts.
 *
 * All generic teldas fixtures/helpers live in \Helper\Teldas.
 */
class teldasFullProcessCest
{
    /** Synthetic input-processor / file_type / line_type name. */
    const FILE_TYPE = 'TeldasTestInput';

    /** Calling subscriber (A-party) used for customer identification. */
    const CALLER_NUMBER = '0700000001';

    /** Shared constants (sourced from the helper to avoid duplication). */
    const INA_NUMBER       = \Helper\Teldas::INA_NUMBER;
    const TELDAS_RATE_KEY  = \Helper\Teldas::TELDAS_RATE_KEY;
    const GENERIC_RATE_KEY = \Helper\Teldas::GENERIC_RATE_KEY;

    public static $isIPSet = false;

    public function _before(ApiTester $I)
    {
        if (!self::$isIPSet) {
            $this->setUP($I);
            self::$isIPSet = true;
            \Billrun_Config::getInstance()->loadDbConfig();
        }
        $I->cleanDB();
        $I->resetBillrunInstances();
        $I->cleanTeldasCollections();
        $I->setTimezone('Europe/Zurich');
        // Register teldas in config so it is attached during both parsing and the
        // queue calculators (single registration - see the helper).
        $I->enableTeldasPlugin($I->teldasPluginOptions(self::FILE_TYPE));
        $I->clearLogFile();
    }

    public function _after(ApiTester $I)
    {
        $I->disableTeldasPlugin();
        $I->cleanTeldasCollections();
        $I->restoreTimezone();
    }

    protected function setUP(ApiTester $I)
    {
        $I->setSettings('file_types', $this->inputProcessor());
        $I->setSettings('usage_types', [
            [
                'usage_type'    => 'call',
                'label'         => 'call',
                'property_type' => 'time',
                'invoice_uom'   => 'seconds',
                'input_uom'     => 'seconds',
            ],
            [
                'usage_type'    => 'ina_vas_call',
                'label'         => 'ina_vas_call',
                'property_type' => 'time',
                'invoice_uom'   => 'seconds',
                'input_uom'     => 'seconds',
            ],
        ]);
        \Billrun_Factory::config()->setConfigValue('queue.calculators', ['customer', 'rate', 'pricing']);
    }

    /* ---------- the test ---------- */

    public function testInaNoTariffZeroDuration_mappedToTeldasRate_savedToQueue_noAlerts(ApiTester $I): void
    {
        $I->haveNoTariffInaNumber();
        $fixtures = $I->createTeldasFixtures(self::CALLER_NUMBER);
        $account    = $fixtures['account'];
        $subscriber = $fixtures['subscriber'];

        $I->processByPath([
            'type' => self::FILE_TYPE,
            'path' => 'tests/all/plugin/teldas/test_files/teldas_ina_no_tariff.csv',
        ]);

        // The call is saved (not dropped) and mapped to ina_vas_call + teldas rate.
        // It stays in_queue because a no-tariff line fails pricing (the last
        // calculator) and is not dequeued.
        $I->assertEquals(1, $I->grabCollectionCount('lines', [
            'uf.Subscriber_Number' => self::INA_NUMBER,
            'in_queue'             => true,
            'usaget'               => 'ina_vas_call',
            'usagev'               => 0,
            'arate_key'            => self::TELDAS_RATE_KEY,
            'aid'                  => $account['aid'],
            'sid'                  => $subscriber['sid'],
        ]), 'the zero-duration no-tariff INA call must be saved to the queue, not dropped');

        // It must NOT have fallen back to the generic (non-teldas) rate.
        $I->assertEquals(0, $I->grabCollectionCount('lines', [
            'uf.Subscriber_Number' => self::INA_NUMBER,
            'arate_key'            => self::GENERIC_RATE_KEY,
        ]), 'an ina_vas_call must not be rated with the generic call rate');

        // A no-tariff ALLNO number is an expected case: no alert must be logged,
        // neither from the teldas plugin nor from the pricing calculator falling
        // back on a null aprice.
        $I->dontSeeInLogFile('is missing or invalid for line');
        $I->dontSeeInLogFile('Too many pricing retries for line stamp');
    }

    /* ---------- input processor config ---------- */

    protected function inputProcessor()
    {
        return [
            'file_type' => self::FILE_TYPE,
            'parser' => [
                'type' => 'separator',
                'line_types' => ['H' => '/^none$/', 'D' => '//', 'T' => '/^none$/'],
                'separator' => ',',
                'structure' => [
                    ['name' => 'Caller_Number', 'checked' => true],
                    ['name' => 'Call_start', 'checked' => true],
                    ['name' => 'Subscriber_Number', 'checked' => true],
                    ['name' => 'Duration_Seconds', 'checked' => true],
                ],
                'csv_has_header' => true,
                'csv_has_footer' => false,
            ],
            'processor' => [
                'type' => 'Usage',
                'date_field' => 'Call_start',
                'default_usaget' => 'call',
                'default_unit' => 'seconds',
                'default_volume_src' => ['Duration_Seconds'],
                // Two usage types: a generic "call", and "ina_vas_call" which the
                // teldas plugin assigns ("by plugin") when the dialed number is an
                // INA number.
                'usaget_mapping' => [
                    [
                        'src_field' => 'Subscriber_Number',
                        'conditions' => [[
                            'src_field' => 'Subscriber_Number',
                            'pattern'   => 'by plugin',
                            'op'        => '$eq',
                            'op_label'  => 'Equals',
                        ]],
                        'pattern'     => 'by plugin',
                        'usaget'      => 'ina_vas_call',
                        'unit'        => 'seconds',
                        'volume_type' => 'field',
                        'volume_src'  => ['Duration_Seconds'],
                    ],
                    [
                        'src_field' => 'Subscriber_Number',
                        'conditions' => [[
                            'src_field' => 'Subscriber_Number',
                            'pattern'   => '/^(?!\\s*$).+/',
                            'op'        => '$regex',
                            'op_label'  => 'Regex',
                        ]],
                        'pattern'     => '/^(?!\\s*$).+/',
                        'usaget'      => 'call',
                        'unit'        => 'seconds',
                        'volume_type' => 'field',
                        'volume_src'  => ['Duration_Seconds'],
                    ],
                ],
            ],
            'customer_identification_fields' => [
                'call' => [[
                    'target_key' => 'firstname',
                    'src_key'    => 'Caller_Number',
                    'conditions' => [['field' => 'usaget', 'regex' => '/.*/']],
                    'clear_regex' => '//',
                ]],
                'ina_vas_call' => [[
                    'target_key' => 'firstname',
                    'src_key'    => 'Caller_Number',
                    'conditions' => [['field' => 'usaget', 'regex' => '/.*/']],
                    'clear_regex' => '//',
                ]],
            ],
            'rate_calculators' => [
                'retail' => [
                    // ina_vas_call -> teldas rate (hard-coded by being an INA call).
                    'ina_vas_call' => [[[
                        'type'     => 'match',
                        'rate_key' => 'key',
                        'line_key' => 'computed',
                        'computed' => [
                            'type'      => 'condition',
                            'line_keys' => [['key' => 'Subscriber_Number']],
                            'operator'  => '$exists',
                            'must_met'  => true,
                            'projection' => [
                                'on_true'  => ['key' => 'hard_coded', 'regex' => '', 'value' => self::TELDAS_RATE_KEY],
                                'on_false' => [],
                            ],
                        ],
                    ]]],
                    // generic call -> non-teldas rate.
                    'call' => [[[
                        'type'     => 'match',
                        'rate_key' => 'key',
                        'line_key' => 'computed',
                        'computed' => [
                            'type'      => 'condition',
                            'line_keys' => [['key' => 'Subscriber_Number']],
                            'operator'  => '$exists',
                            'must_met'  => true,
                            'projection' => [
                                'on_true'  => ['key' => 'hard_coded', 'regex' => '', 'value' => self::GENERIC_RATE_KEY],
                                'on_false' => [],
                            ],
                        ],
                    ]]],
                ],
            ],
            'pricing' => [
                'call'         => [],
                'ina_vas_call' => [
                    'aprice_field' => 'Duration_Seconds',
                    'tax_included' => true,
                ],
            ],
            'enabled' => true,
            'filters' => [],
            'receiver' => [
                'type' => 'ftp',
                'connections' => [[
                    'receiver_type' => 'ftp', 'passive' => false, 'delete_received' => false,
                    'user' => 'admin', 'password' => '12345678', 'host' => '127.0.0.1',
                    'name' => 'a', 'remote_directory' => '/home',
                ]],
            ],
        ];
    }
}

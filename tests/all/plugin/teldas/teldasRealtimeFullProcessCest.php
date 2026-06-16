<?php

/**
 * teldasRealtimeFullProcessCest
 *
 * Realtime counterpart of teldasFullProcessCest: drives a /realtime request for
 * a call to a teldas INA number that has NO tariff (status ALLNO,
 * tariffProfile null) with zero duration, and verifies that the teldas plugin
 * maps it to ina_vas_call (via afterRealtimeProcessorParsing), it routes to the
 * teldas rate (not the generic one), a line is saved, and no alert is logged.
 *
 * /realtime is handled inside the web container, so the teldas plugin is enabled
 * through config (setPluginSettings) rather than by attaching it to the
 * in-process dispatcher. All generic teldas fixtures/helpers live in
 * \Helper\Teldas.
 */
class teldasRealtimeFullProcessCest
{
    /** Realtime input-processor / file_type / line_type name. */
    const FILE_TYPE = 'TeldasRealtimeTestInput';

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
        // /realtime is handled by the web container, which loads plugins from
        // config - so register teldas in config (not the test-process dispatcher).
        $I->enableTeldasPluginInConfig($I->teldasPluginOptions(self::FILE_TYPE));
        $I->setTimezone('Europe/Zurich');
        $I->clearLogFile();
    }

    public function _after(ApiTester $I)
    {
        $I->disableTeldasPluginInConfig();
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

    public function testRealtimeInaNoTariffZeroDuration_mappedToTeldasRate_noAlerts(ApiTester $I): void
    {
        $I->haveNoTariffInaNumber();
        $fixtures = $I->createTeldasFixtures('0700000001', 'TELDAS_RT_PLAN_');
        $subscriber = $fixtures['subscriber'];

        $I->sendInitialRequestCdr(self::FILE_TYPE, [
            'sid'               => $subscriber['sid'],
            'Call_start'        => '2026-01-15T10:00:00+01:00',
            'Subscriber_Number' => self::INA_NUMBER,
            'Duration_Seconds'  => 0,
        ]);

        // The plugin mapped the realtime CDR to ina_vas_call and it routed to the
        // teldas rate; a line was saved with zero usage.
        $I->verifyCollectionRecord('lines', [
            'uf.Subscriber_Number' => self::INA_NUMBER,
            'usaget'               => 'ina_vas_call',
            'usagev'               => 0,
            'arate_key'            => self::TELDAS_RATE_KEY,
            'sid'                  => $subscriber['sid'],
        ]);

        // It must NOT have fallen back to the generic (non-teldas) rate.
        $I->assertEquals(0, $I->grabCollectionCount('lines', [
            'uf.Subscriber_Number' => self::INA_NUMBER,
            'arate_key'            => self::GENERIC_RATE_KEY,
        ]), 'an ina_vas_call must not be rated with the generic call rate');

        // A no-tariff ALLNO number is an expected case: no alert must be logged.
        $I->dontSeeInLogFile('is missing or invalid for line');
        $I->dontSeeInLogFile('Too many pricing retries for line stamp');
    }

    /* ---------- realtime input processor config ---------- */

    protected function inputProcessor()
    {
        return [
            'file_type' => self::FILE_TYPE,
            'type'      => 'realtime',
            'parser' => [
                'type' => 'json',
                'separator' => '',
                'structure' => [
                    ['name' => 'sid', 'checked' => true],
                    ['name' => 'Call_start', 'checked' => true],
                    ['name' => 'Subscriber_Number', 'checked' => true],
                    ['name' => 'Duration_Seconds', 'checked' => true],
                ],
                'custom_keys' => ['sid', 'Call_start', 'Subscriber_Number', 'Duration_Seconds'],
                'csv_has_header' => false,
                'csv_has_footer' => false,
                'line_types' => ['H' => '/^none$/', 'D' => '//', 'T' => '/^none$/'],
            ],
            'processor' => [
                'type' => 'Realtime',
                'date_field' => 'Call_start',
                'default_usaget' => 'call',
                'default_unit' => 'seconds',
                'default_volume_src' => ['Duration_Seconds'],
                'orphan_files_time' => '6 hours',
                // Two usage types: a generic "call", and "ina_vas_call" which the
                // teldas plugin assigns ("by plugin") for an INA number.
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
                    'target_key' => 'sid',
                    'src_key'    => 'sid',
                    'conditions' => [['field' => 'usaget', 'regex' => '/.*/']],
                    'clear_regex' => '//',
                ]],
                'ina_vas_call' => [[
                    'target_key' => 'sid',
                    'src_key'    => 'sid',
                    'conditions' => [['field' => 'usaget', 'regex' => '/.*/']],
                    'clear_regex' => '//',
                ]],
            ],
            // Rate is chosen by usaget: ina_vas_call -> teldas rate, call -> generic.
            'rate_calculators' => [
                'retail' => [
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
            'realtime' => [
                'postpay_charge' => true,
            ],
            'response' => [
                'encode' => 'json',
                'fields' => [
                    ['response_field_name' => 'requestType', 'row_field_name' => 'request_type'],
                    ['response_field_name' => 'sid',         'row_field_name' => 'sid'],
                    ['response_field_name' => 'grantedVolume', 'row_field_name' => 'usagev'],
                ],
            ],
            'unify' => [],
            'enabled' => true,
        ];
    }
}

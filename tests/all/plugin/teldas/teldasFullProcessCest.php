<?php

/**
 * teldasFullProcessCest
 *
 * Full-pipeline test (real input processor -> teldas plugin hooks -> queue
 * calculators -> lines)
 * */
class teldasFullProcessCest
{
    /** Synthetic input-processor / file_type / line_type name. */
    const FILE_TYPE = 'TeldasTestInput';

    /** Dialed INA number with no tariff (the BRCD-5292 fixture). */
    const INA_NUMBER = '0800000523';

    /** Calling subscriber (A-party) used for customer identification. */
    const CALLER_NUMBER = '0700000001';

    const TELDAS_RATE_KEY  = 'TELDAS_INA';
    const GENERIC_RATE_KEY = 'REGULAR_CALL';

    public static $isIPSet = false;

    protected $teldasCollections = [
        'plugin_teldas_ina_numbers',
        'plugin_teldas_tariffs_profiles',
        'plugin_teldas_tariff_switching_classes',
        'plugin_teldas_non_working_days',
    ];

    /** Same plugin options shape as production; line_type matches FILE_TYPE. */
    protected $pluginOptions = [
        'url'      => 'https://ws.test.numberportability.ch',
        'user'     => 'test',
        'password' => 'test',
        'ina_number_prefixes' => '/^(0800|0848|0900|0901|0906|0840|0842|0844|0878)|^18[0-9][0-9]$/',
        'matching_paths' => [
            [
                'line_type' => self::FILE_TYPE,
                'duration'  => [
                    'path'              => 'uf.Duration_Seconds',
                    'divide_to_seconds' => 1,
                ],
                'subscriber_number' => [
                    'path'       => 'uf.Subscriber_Number',
                    'conversion' => [
                        ['pattern' => '/^41(?=\\d{4}$)/',  'replacement' => ''],
                        ['pattern' => '/^41(?=\\d{5}+)/', 'replacement' => '0'],
                    ],
                ],
                'usage' => ['type' => 'ina_vas_call'],
            ],
        ],
        'update_online'    => true,
        'update_offline-a' => true,
    ];

    /** @var \teldasPlugin|null plugin instance attached to the dispatcher */
    protected $teldasPluginInstance = null;

    public function _before(ApiTester $I)
    {
        if (!self::$isIPSet) {
            $this->setUP($I);
            self::$isIPSet = true;
            \Billrun_Config::getInstance()->loadDbConfig();
        }
        $I->cleanDB();
        $I->resetBillrunInstances();
        $this->cleanTeldasCollections();
        $I->setTimezone('Europe/Zurich');
        $this->enableTeldasPlugin();
        $I->clearLogFile();
    }

    public function _after(ApiTester $I)
    {
        $this->disableTeldasPlugins();
        $this->cleanTeldasCollections();
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

    /* ---------- teldas plugin enable/disable (in-process dispatcher) ---------- */

    protected function enableTeldasPlugin()
    {
        $plugin = new \teldasPlugin($this->pluginOptions);
        $plugin->setAvailability(true);
        $plugin->setOptions(array_merge($this->pluginOptions, ['enabled' => true]));
        \Billrun_Dispatcher::getInstance()->attach($plugin);
        $this->teldasPluginInstance = $plugin;
    }

    /**
     * Remove every teldasPlugin observer from the (process-wide singleton)
     * dispatcher. Billrun_Spl_Subject::detach() has an off-by-one bug for the
     * observer at index 0, so we filter the observers list via reflection
     * instead, leaving any other plugins attached by earlier tests untouched.
     */
    protected function disableTeldasPlugins()
    {
        $dispatcher = \Billrun_Dispatcher::getInstance();
        $prop = new \ReflectionProperty('Billrun_Spl_Subject', 'observers');
        $prop->setAccessible(true);
        $observers = $prop->getValue($dispatcher);
        $observers = array_values(array_filter($observers, function ($o) {
            return !($o instanceof \teldasPlugin);
        }));
        $prop->setValue($dispatcher, $observers);
        $this->teldasPluginInstance = null;
    }

    /* ---------- fixtures ---------- */

    protected function cleanTeldasCollections()
    {
        foreach ($this->teldasCollections as $name) {
            $collection = \Billrun_Factory::db()->getCollection($name);
            if ($collection) {
                $collection->remove(['_id' => ['$exists' => true]]);
            }
        }
    }

    protected function bsonDate($strOrTs)
    {
        $ts = is_string($strOrTs) ? strtotime($strOrTs) : (int) $strOrTs;
        return new \MongoDB\BSON\UTCDateTime($ts * 1000);
    }

    /** Insert the BRCD-5292 no-tariff / ALLNO INA revision verbatim. */
    protected function insertNoTariffInaNumber(ApiTester $I)
    {
        $I->haveInCollection('plugin_teldas_ina_numbers', [
            'subscriberNumber'      => self::INA_NUMBER,
            'status'                => 'ALLNO',
            'tspId'                 => 98000,
            'tariffProfile'         => null,
            'tariffProfileType'     => null,
            'accessAbroad'          => null,
            'activationDatetime'    => null,
            'expirationDatetime'    => null,
            'modificationDatetime'  => null,
            'terminationDatetime'   => null,
            'transactionDatetime'   => $this->bsonDate('2025-12-11 00:48:00'),
            'modifyPending'         => false,
            'transactionDatetimeTo' => null,
        ]);
    }

    protected function createAccountPlanSubscriberAndRates(ApiTester $I)
    {
        $I->generatePlan(['name' => 'TELDAS_TEST_PLAN_' . (int) (microtime(true) * 10000), 'from' => '2025-01-01']);
        $plan = json_decode($I->grabResponse(), true)['entity'];

        // Teldas rate - the INA (ina_vas_call) line must resolve to this one.
        $I->generateRate([
            'tariff_category' => 'retail',
            'key'             => self::TELDAS_RATE_KEY,
            'from'            => '2025-01-01',
            'rates' => [
                'ina_vas_call' => [
                    'BASE' => [
                        'rate' => [[
                            'from' => 0, 'to' => 'UNLIMITED', 'interval' => 1, 'price' => 0,
                            'uom_display' => ['range' => 'seconds', 'interval' => 'seconds'],
                        ]],
                    ],
                ],
            ],
        ]);

        // Generic (non-teldas) rate - must NOT be the one picked for an INA call.
        $I->generateRate([
            'tariff_category' => 'retail',
            'key'             => self::GENERIC_RATE_KEY,
            'from'            => '2025-01-01',
            'rates' => [
                'call' => [
                    'BASE' => [
                        'rate' => [[
                            'from' => 0, 'to' => 'UNLIMITED', 'interval' => 1, 'price' => 1,
                            'uom_display' => ['range' => 'seconds', 'interval' => 'seconds'],
                        ]],
                    ],
                ],
            ],
        ]);

        $I->createAccountWithAllMandatoryCustomFields(['firstname' => 'teldas_test_account']);
        $account = json_decode($I->grabResponse(), true)['entity'];

        $I->generateSubscriber([
            'from'      => '2025-01-01',
            'firstname' => self::CALLER_NUMBER,
            'aid'       => $account['aid'],
            'plan'      => $plan['name'],
        ]);
        $subscriber = json_decode($I->grabResponse(), true)['entity'];

        return [$account, $subscriber];
    }

    /* ---------- the test ---------- */

    public function testInaNoTariffZeroDuration_mappedToTeldasRate_savedToQueue_noAlerts(ApiTester $I): void
    {
        $this->insertNoTariffInaNumber($I);
        list($account, $subscriber) = $this->createAccountPlanSubscriberAndRates($I);

        $I->processByPath([
            'type' => self::FILE_TYPE,
            'path' => 'tests/all/plugin/teldas/test_files/teldas_ina_no_tariff.csv',
        ]);

        // The call is saved (not dropped): exactly one line for the dialed INA.
        $I->assertEquals(1, $I->grabCollectionCount('lines', [
            'uf.Subscriber_Number' => self::INA_NUMBER,
            'in_queue' => true,
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
        // back on a null aprice ("Price field ... is missing or invalid").
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

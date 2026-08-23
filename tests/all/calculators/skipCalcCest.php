<?php

/**
 * skipCalcCest
 *
 * Verifies the "filters" (skip_calc) feature of input processors in both flows:
 * - offline file processing (Billrun_Processor::process)
 * - realtime events (/realtime -> Billrun_Processor_Realtime::process)
 *
 * In both flows, a line matching a filter's conditions must get a skip_calc
 * attribute and skip the configured calculators, while non-matching lines are
 * calculated as usual. The realtime test is a regression test for skip_calc
 * being ignored in realtime: filterLines() looked up the file type config by
 * the processor type ('realtime') instead of the actual file_type name, so it
 * always returned early (broken since the drop_lines feature was added).
 */
class skipCalcCest
{
    const OFFLINE_FILE_TYPE = 'skip_calc_offline';
    const REALTIME_FILE_TYPE = 'SKIP_CALC_REALTIME_TEST';
    const RATE_KEY = 'SKIP_CALC_CALL';

    /** uf values that match the filters (skip_calc) conditions */
    const OFFLINE_SKIPPED_FIRSTNAME = '0530000001';
    const OFFLINE_REGULAR_FIRSTNAME = '0530000002';
    const REALTIME_SKIPPED_NUMBER = '0531111111';
    const REALTIME_REGULAR_NUMBER = '0532222222';

    public static $isIPSet = false;

    protected $accountDetails;
    protected $planDetails;
    protected $rateDetails;
    protected $subscriberDetails;

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

    protected function setUP(ApiTester $I)
    {
        $I->setSettings('file_types', $this->offlineInputProcessor());
        $I->setSettings('file_types', $this->realtimeInputProcessor());
        $I->setSettings('usage_types', [
            [
                'usage_type' => 'call',
                'label' => 'call',
                'property_type' => 'time',
                'invoice_uom' => 'seconds',
                'input_uom' => 'seconds'
            ]
        ]);
    }

    protected function createData(ApiTester $I)
    {
        $I->createAccountWithAllMandatoryCustomFields(['firstname' => 'skip_calc_test']);
        $this->accountDetails = json_decode($I->grabResponse(), true)['entity'];
        $I->generatePlan(['name' => 'TEST_PLAN_SKIP_CALC' . microtime(true) * 10000, 'from' => '2025-01-01']);
        $this->planDetails = json_decode($I->grabResponse(), true)['entity'];
        $I->generateRate([
            'tariff_category' => 'retail',
            'key' => self::RATE_KEY,
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
                                    'interval' => 'seconds'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
        ]);
        $this->rateDetails = json_decode($I->grabResponse(), true)['entity'];
    }

    protected function generateSubscriber(ApiTester $I, $firstname)
    {
        $I->generateSubscriber([
            'from' => '2025-01-01',
            'firstname' => $firstname,
            'aid' => $this->accountDetails['aid'],
            'plan' => $this->planDetails['name'],
        ]);
        $this->subscriberDetails = json_decode($I->grabResponse(), true)['entity'];
        return $this->subscriberDetails;
    }

    protected function grabLineAsArray(ApiTester $I, array $query)
    {
        return json_decode(json_encode($I->grabFromCollection('lines', $query)), true);
    }

    public function testSkipCalcOffline(ApiTester $I): void
    {
        $this->createData($I);
        $regularSubscriber = $this->generateSubscriber($I, self::OFFLINE_REGULAR_FIRSTNAME);
        // a subscriber exists for the skipped line too - it must not be enriched anyway
        $this->generateSubscriber($I, self::OFFLINE_SKIPPED_FIRSTNAME);

        Billrun_Factory::config()->setConfigValue('queue.calculators', ['customer', 'rate', 'pricing']);

        $I->processByPath([
            'type' => self::OFFLINE_FILE_TYPE,
            'path' => 'tests/all/calculators/test_files/skip_calc.csv'
        ]);

        // the line matching the filter was saved with skip_calc and no calculator ran on it
        $I->verifyCollectionRecord('lines', [
            'uf.firstname' => self::OFFLINE_SKIPPED_FIRSTNAME,
            'skip_calc' => ['$exists' => true],
        ]);
        $skipped = $this->grabLineAsArray($I, ['uf.firstname' => self::OFFLINE_SKIPPED_FIRSTNAME]);
        $I->assertContains('rate', $skipped['skip_calc'], 'skip_calc "all" should resolve to the queue calculators');
        $I->assertArrayNotHasKey('aid', $skipped, 'customer calc should be skipped');
        $I->assertArrayNotHasKey('sid', $skipped, 'customer calc should be skipped');
        $I->assertArrayNotHasKey('arate_key', $skipped, 'rate calc should be skipped');
        $I->assertArrayNotHasKey('aprice', $skipped, 'pricing calc should be skipped');

        // the regular line was fully calculated
        $I->verifyCollectionRecord('lines', [
            'uf.firstname' => self::OFFLINE_REGULAR_FIRSTNAME,
            'arate_key' => self::RATE_KEY,
            'sid' => $regularSubscriber['sid'],
        ]);
        $regular = $this->grabLineAsArray($I, ['uf.firstname' => self::OFFLINE_REGULAR_FIRSTNAME]);
        $I->assertArrayNotHasKey('skip_calc', $regular, 'non-filtered line should not get skip_calc');
        $I->assertArrayHasKey('aprice', $regular, 'pricing calc should run on non-filtered lines');

        // both lines finished the queue (the skipped line advanced without calculating)
        $I->assertEquals(0, $I->grabCollectionCount('queue', ['type' => self::OFFLINE_FILE_TYPE]));
    }

    public function testSkipCalcRealtime(ApiTester $I): void
    {
        $this->createData($I);
        $this->generateSubscriber($I, 'realtime_skip_calc');

        // event matching the filter: skip_calc must be applied in realtime too
        $I->sendInitialRequestCdr(self::REALTIME_FILE_TYPE, [
            'sid' => $this->subscriberDetails['sid'],
            'date' => '2025-03-01T10:00:00+02:00',
            'callingNumber' => self::REALTIME_SKIPPED_NUMBER,
            'duration' => 60,
        ]);
        $I->verifyCollectionRecord('lines', [
            'uf.callingNumber' => self::REALTIME_SKIPPED_NUMBER,
            'skip_calc' => ['$exists' => true],
        ]);
        $skipped = $this->grabLineAsArray($I, ['uf.callingNumber' => self::REALTIME_SKIPPED_NUMBER]);
        $I->assertArrayNotHasKey('aid', $skipped, 'customer calc should be skipped in realtime');
        $I->assertArrayNotHasKey('sid', $skipped, 'customer calc should be skipped in realtime');
        $I->assertArrayNotHasKey('arate_key', $skipped, 'rate calc should be skipped in realtime');
        $I->assertArrayNotHasKey('aprice', $skipped, 'pricing calc should be skipped in realtime');

        // event not matching the filter: fully calculated
        $I->sendInitialRequestCdr(self::REALTIME_FILE_TYPE, [
            'sid' => $this->subscriberDetails['sid'],
            'date' => '2025-03-01T11:00:00+02:00',
            'callingNumber' => self::REALTIME_REGULAR_NUMBER,
            'duration' => 60,
        ]);
        $I->verifyCollectionRecord('lines', [
            'uf.callingNumber' => self::REALTIME_REGULAR_NUMBER,
            'arate_key' => self::RATE_KEY,
            'sid' => $this->subscriberDetails['sid'],
        ]);
        $regular = $this->grabLineAsArray($I, ['uf.callingNumber' => self::REALTIME_REGULAR_NUMBER]);
        $I->assertArrayNotHasKey('skip_calc', $regular, 'non-filtered realtime line should not get skip_calc');
        $I->assertArrayHasKey('aprice', $regular, 'pricing calc should run on non-filtered realtime lines');
    }

    protected function offlineInputProcessor()
    {
        return [
            'file_type' => self::OFFLINE_FILE_TYPE,
            'parser' => [
                'type' => 'separator',
                'line_types' => [
                    'H' => '/^none$/',
                    'D' => '//',
                    'T' => '/^none$/'
                ],
                'separator' => ',',
                'structure' => [
                    ['name' => 'firstname', 'checked' => true],
                    ['name' => 'date', 'checked' => true],
                    ['name' => 'rate', 'checked' => true],
                    ['name' => 'volume', 'checked' => true]
                ],
                'csv_has_header' => true,
                'csv_has_footer' => false
            ],
            'processor' => [
                'type' => 'Usage',
                'date_field' => 'date',
                'default_usaget' => 'call',
                'default_unit' => 'seconds',
                'default_volume_src' => ['volume']
            ],
            'customer_identification_fields' => [
                'call' => [[
                    'target_key' => 'firstname',
                    'src_key' => 'firstname',
                    'conditions' => [['field' => 'usaget', 'regex' => '/.*/']],
                    'clear_regex' => '//'
                ]]
            ],
            'rate_calculators' => ['retail' => ['call' => [[['type' => 'match', 'rate_key' => 'key', 'line_key' => 'rate']]]]],
            'pricing' => ['call' => []],
            // a receiver is required for an offline file type to be a "complete" configuration
            'receiver' => [
                'type' => 'ftp',
                'connections' => [
                    [
                        'receiver_type' => 'ftp',
                        'passive' => false,
                        'delete_received' => false,
                        'user' => 'admin',
                        'password' => '12345678',
                        'host' => '127.0.0.1',
                        'name' => 'a',
                        'remote_directory' => '/home'
                    ]
                ]
            ],
            'filters' => [
                [
                    'conditions' => [
                        [
                            'field_name' => 'uf.firstname',
                            'op' => '$eq',
                            'value' => self::OFFLINE_SKIPPED_FIRSTNAME
                        ]
                    ],
                    'skip_calc' => ['all']
                ]
            ],
            'enabled' => true,
        ];
    }

    protected function realtimeInputProcessor()
    {
        return [
            'file_type' => self::REALTIME_FILE_TYPE,
            'type' => 'realtime',
            'parser' => [
                'type' => 'json',
                'separator' => '',
                'structure' => [
                    ['name' => 'sid', 'checked' => true],
                    ['name' => 'date', 'checked' => true],
                    ['name' => 'callingNumber', 'checked' => true],
                    ['name' => 'duration', 'checked' => true],
                ],
                'custom_keys' => ['sid', 'date', 'callingNumber', 'duration'],
                'csv_has_header' => false,
                'csv_has_footer' => false,
                'line_types' => ['H' => '/^none$/', 'D' => '//', 'T' => '/^none$/'],
            ],
            'processor' => [
                'type' => 'Realtime',
                'date_field' => 'date',
                'default_usaget' => 'call',
                'default_unit' => 'seconds',
                'default_volume_src' => ['duration'],
                'orphan_files_time' => '6 hours',
            ],
            'customer_identification_fields' => [
                'call' => [[
                    'target_key' => 'sid',
                    'src_key' => 'sid',
                    'conditions' => [['field' => 'usaget', 'regex' => '/.*/']],
                    'clear_regex' => '//',
                ]],
            ],
            'rate_calculators' => [
                'retail' => [
                    'call' => [[[
                        'type' => 'match',
                        'rate_key' => 'key',
                        'line_key' => 'computed',
                        'computed' => [
                            'type' => 'condition',
                            'line_keys' => [['key' => 'callingNumber']],
                            'operator' => '$exists',
                            'must_met' => true,
                            'projection' => [
                                'on_true' => ['key' => 'hard_coded', 'regex' => '', 'value' => self::RATE_KEY],
                                'on_false' => [],
                            ],
                        ],
                    ]]],
                ],
            ],
            'pricing' => ['call' => []],
            'realtime' => [
                'postpay_charge' => true,
            ],
            'response' => [
                'encode' => 'json',
                'fields' => [
                    ['response_field_name' => 'requestType', 'row_field_name' => 'request_type'],
                    ['response_field_name' => 'sid', 'row_field_name' => 'sid'],
                    ['response_field_name' => 'grantedVolume', 'row_field_name' => 'usagev'],
                ],
            ],
            'filters' => [
                [
                    'conditions' => [
                        [
                            'field_name' => 'uf.callingNumber',
                            'op' => '$eq',
                            'value' => self::REALTIME_SKIPPED_NUMBER
                        ]
                    ],
                    'skip_calc' => ['all']
                ]
            ],
            'unify' => [],
            'enabled' => true,
        ];
    }
}

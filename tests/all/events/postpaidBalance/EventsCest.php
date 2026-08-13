<?php

/**
 * EventsConditionsCest
 *
 * Regression test for the balance-events condition logic of
 * Billrun_EventsManager::trigger() (reported 2024-10-28, "events conditions
 * not working"; behaviour broken since the BRCD-4617 refactor).
 *
 * An event with several conditions requires ALL of them to hold (AND), and
 * each condition may list several paths (OR between groups). The AND must
 * hold per path: a group may trigger an event only when it satisfies every
 * condition of the event.
 *
 * Today trigger() collects each condition's matching paths independently and
 * only verifies every condition matched at least one path - never that it is
 * the same path. So for an event with conditions ">5" and "<=100" on groups
 * G1/G2 and a row adding usage to G1 only, a G2 that never crossed ">5" also
 * satisfies "<=100" and a second, spurious event is saved for it.
 *
 * Two tests cover the same defect:
 * - eventOnlyForPathMatchingAllConditions: calls trigger() directly with
 *   hand-built balances - pinpoints the broken loop.
 * - e2eEventOnlyForGroupMatchingAllConditions: full pipeline - CDR files run
 *   through an input processor (processByPath) and the customer/rate/pricing
 *   calculators against a service with two groups, so the balance group
 *   counters, the event trigger and the spurious event all come from the
 *   real flow.
 *
 * A third test covers the related duplicate defect (reported from
 * customer_portal, fixed under BRCD-4637):
 * - e2eEventForSecondGroupCreatedOnce: one event whose single condition
 *   lists both groups as paths (OR). Because the condition only checks the
 *   balance AFTER the row, a group that crossed the threshold on an earlier
 *   line keeps matching on every later line, so pushing the second group
 *   over the threshold saves the event twice.
 *
 * A fourth test combines the two shapes (the "wildcard1" event of the
 * original report): two AND conditions (">5", "<=100"), each listing both
 * groups as paths (OR):
 * - e2eEventPerGroupForAndConditionsWithOrPaths: each group must fire
 *   exactly once, on the row where it satisfies both conditions.
 */
class EventsCest
{
    const EVENT_CODE = 'AND_CONDITIONS_PER_PATH';
    const EVENT_CODE_OR = 'TWO_GROUPS_OR_PATHS';
    const EVENT_CODE_AND_OR = 'AND_CONDITIONS_OR_PATHS';
    const AID = 987001;
    const SID = 987002;
    const PLAN = 'EVENTS_COND_PLAN';
    const SERVICE = 'EVENTS_COND_SERVICE';
    const RATE_G1 = 'EVENTS_COND_CALL_G1';
    const RATE_G2 = 'EVENTS_COND_CALL_G2';
    const FILE_TYPE = 'EVENTS_COND_CSV';
    const FIRSTNAME = 'events_cond_sub';
    const TEST_FILES_PATH = 'tests/all/events/postpaidBalance/test_files/';

    /** original in-process events config, restored in _after */
    protected $originalEventsConfig;

    /** sid of the generated subscriber, resolved by the customer calculator */
    protected $sid;

    /** aid of the generated account */
    protected $aid;

    public function _before(ApiTester $I)
    {
        $I->cleanDB();
        Billrun_Config::getInstance()->loadDbConfig();
        $this->originalEventsConfig = Billrun_Factory::config()->getConfigValue('events', []);
        // the events under test are applied per-test by seedCatalog, see
        // replaceEventsConfig for why setConfigValue must not be used
        $this->resetEventsManager();
        $I->resetBillrunInstances();
    }

    public function _after(ApiTester $I)
    {
        Billrun_Factory::config()->setConfigValue('events', $this->originalEventsConfig);
        $this->resetEventsManager();
    }
    
    /**
     * End to end: CDR files are processed through an input processor and the
     * customer, rate and pricing calculators, against a subscriber whose
     * service includes two groups (G1 and G2, each covering its own rate),
     * so the balance group counters and the event trigger come from the real
     * flow.
     *
     * CDR 1 puts 2 seconds in G2 - below the ">5" threshold, no event.
     * CDR 2 puts 10 seconds in G1 - G1 satisfies both conditions, exactly
     * one event is expected. G2 (2 <= 100, but never > 5) must not fire;
     * with the current bug a second event is saved for it with after = 2.
     */
    public function e2eEventOnlyForGroupMatchingAllConditions(ApiTester $I)
    {
        $this->seedCatalog($I);

        $I->processByPath([
            'type' => self::FILE_TYPE,
            'path' => self::TEST_FILES_PATH . 'events_conditions_2s_g2.csv',
        ]);
        $I->verifyCollectionRecord('lines', [
            'uf.firstname' => self::FIRSTNAME,
            'arate_key' => self::RATE_G2,
            'sid' => $this->sid,
            "in_group" => 2,
            "over_group" => 0
        ]);
        $I->seeNumElementsInCollection('events', 0, ['event_code' => self::EVENT_CODE]);

        $I->processByPath([
            'type' => self::FILE_TYPE,
            'path' => self::TEST_FILES_PATH . 'events_conditions_10s_g1.csv',
        ]);
        $I->verifyCollectionRecord('lines', [
            'uf.firstname' => self::FIRSTNAME,
            'arate_key' => self::RATE_G1,
            'sid' => $this->sid,
            "in_group" => 10,
            "over_group" => 0
        ]);
            $I->seeNumElementsInCollection('events', 0, ['event_code' => self::EVENT_CODE]);

        $I->processByPath([
            'type' => self::FILE_TYPE,
            'path' => self::TEST_FILES_PATH . 'events_conditions_more_4s_g2.csv',
        ]);
        $I->verifyCollectionRecord('lines', [
            'uf.firstname' => self::FIRSTNAME,
            'arate_key' => self::RATE_G2,
            'sid' => $this->sid,
            "in_group" => 4,
            "over_group" => 0
        ]);
        $I->seeNumElementsInCollection('events', 1, ['event_code' => self::EVENT_CODE]);

    }

    /**
     * Reproduces the customer_portal duplicate (BRCD-4637): one event whose
     * single condition lists both groups as paths (OR between the groups),
     * against a service holding the two groups on the same usage type.
     *
     * CDR 1 puts 100 seconds in G1 - crosses ">90", exactly one event.
     * CDR 2 puts 100 seconds in G2 - crosses ">90", exactly one more event
     * is expected. With the current bug the G1 path (unchanged at 100 since
     * CDR 1, still ">90" after the row) matches again and the event of the
     * second group is saved twice.
     */
    public function e2eEventForSecondGroupCreatedOnce(ApiTester $I)
    {
        $this->seedCatalog($I, $this->twoGroupsOrPathsEventSettings());

        $I->processByPath([
            'type' => self::FILE_TYPE,
            'path' => self::TEST_FILES_PATH . 'events_conditions_100s_g1.csv',
        ]);
        $I->verifyCollectionRecord('lines', [
            'uf.firstname' => self::FIRSTNAME,
            'arate_key' => self::RATE_G1,
            'sid' => $this->sid,
            'in_group' => 100,
            'over_group' => 0,
        ]);
        $I->seeNumElementsInCollection('events', 1, ['event_code' => self::EVENT_CODE_OR]);

        // the event fired for G1, pushed by the row from 0 over the ">90"
        // threshold, for the account and subscriber under test
        $I->seeNumElementsInCollection('events', 1, [
            'event_code' => self::EVENT_CODE_OR,
            'matched_path.path' => 'balance.groups.G1.usagev',
            'matched_path.related_entities.key' => 'G1',
            'before' => 0,
            'after' => 100,
            'extra_params.aid' => $this->aid,
            'extra_params.sid' => $this->sid,
        ]);

        $I->processByPath([
            'type' => self::FILE_TYPE,
            'path' => self::TEST_FILES_PATH . 'events_conditions_100s_g2.csv',
        ]);
        $I->verifyCollectionRecord('lines', [
            'uf.firstname' => self::FIRSTNAME,
            'arate_key' => self::RATE_G2,
            'sid' => $this->sid,
            'in_group' => 100,
            'over_group' => 0,
        ]);
        $I->verifyCollectionRecord('balances', [
            'sid' => $this->sid,
            'balance.groups.G1.usagev' => 100,
            'balance.groups.G2.usagev' => 100,
        ]);
        // one event per group crossing the threshold; the buggy trigger()
        // also saves one for G1, which never changed on the second row
        $I->seeNumElementsInCollection('events', 2, ['event_code' => self::EVENT_CODE_OR]);
        // the new event fired for G2, pushed by the row from 0 over the threshold
        $I->seeNumElementsInCollection('events', 1, [
            'event_code' => self::EVENT_CODE_OR,
            'matched_path.path' => 'balance.groups.G2.usagev',
            'matched_path.related_entities.key' => 'G2',
            'before' => 0,
            'after' => 100,
            'extra_params.aid' => $this->aid,
            'extra_params.sid' => $this->sid,
        ]);
        // G1 still has only its original event - the G2 row did not re-fire it
        $I->seeNumElementsInCollection('events', 1, [
            'event_code' => self::EVENT_CODE_OR,
            'matched_path.path' => 'balance.groups.G1.usagev',
        ]);
    }

    /**
     * The complex shape of the original report ("wildcard1"): AND between
     * two conditions (">5" and "<=100"), OR between the two group paths
     * inside each condition. A group may fire only on the row where it
     * satisfies both conditions, and only once.
     *
     * CDR 1 puts 2 seconds in G2 - no group is over ">5", no event.
     * CDR 2 puts 10 seconds in G1 - G1 satisfies both conditions, exactly
     * one event. G2 (2 <= 100, but never > 5) must not fire; the buggy
     * per-condition matching saves a second event for it with after = 2.
     * CDR 3 adds 4 seconds to G2 - G2 (now 6) satisfies both conditions,
     * exactly one more event. G1 (unchanged at 10, still matching both)
     * must not fire again.
     */
    public function e2eEventPerGroupForAndConditionsWithOrPaths(ApiTester $I)
    {
        $this->seedCatalog($I, $this->andConditionsOrPathsEventSettings());

        $I->processByPath([
            'type' => self::FILE_TYPE,
            'path' => self::TEST_FILES_PATH . 'events_conditions_2s_g2.csv',
        ]);
        $I->verifyCollectionRecord('lines', [
            'uf.firstname' => self::FIRSTNAME,
            'arate_key' => self::RATE_G2,
            'sid' => $this->sid,
            'in_group' => 2,
            'over_group' => 0,
        ]);
        $I->seeNumElementsInCollection('events', 0, ['event_code' => self::EVENT_CODE_AND_OR]);

        $I->processByPath([
            'type' => self::FILE_TYPE,
            'path' => self::TEST_FILES_PATH . 'events_conditions_10s_g1.csv',
        ]);
        $I->verifyCollectionRecord('lines', [
            'uf.firstname' => self::FIRSTNAME,
            'arate_key' => self::RATE_G1,
            'sid' => $this->sid,
            'in_group' => 10,
            'over_group' => 0,
        ]);
        $I->seeNumElementsInCollection('events', 1, ['event_code' => self::EVENT_CODE_AND_OR]);
        // the event fired for G1 (0 -> 10), for the account and subscriber
        // under test
        $I->seeNumElementsInCollection('events', 1, [
            'event_code' => self::EVENT_CODE_AND_OR,
            'matched_path.path' => 'balance.groups.G1.usagev',
            'matched_path.related_entities.key' => 'G1',
            'before' => 0,
            'after' => 10,
            'extra_params.aid' => $this->aid,
            'extra_params.sid' => $this->sid,
        ]);
        // the spurious cross-path match's signature: an event for G2, whose
        // 2 seconds satisfy "<=100" but never crossed ">5"
        $I->seeNumElementsInCollection('events', 0, ['event_code' => self::EVENT_CODE_AND_OR, 'after' => 2]);

        $I->processByPath([
            'type' => self::FILE_TYPE,
            'path' => self::TEST_FILES_PATH . 'events_conditions_more_4s_g2.csv',
        ]);
        $I->verifyCollectionRecord('balances', [
            'sid' => $this->sid,
            'balance.groups.G1.usagev' => 10,
            'balance.groups.G2.usagev' => 6,
        ]);
        $I->seeNumElementsInCollection('events', 2, ['event_code' => self::EVENT_CODE_AND_OR]);
        // the new event fired for G2 (2 -> 6)
        $I->seeNumElementsInCollection('events', 1, [
            'event_code' => self::EVENT_CODE_AND_OR,
            'matched_path.path' => 'balance.groups.G2.usagev',
            'matched_path.related_entities.key' => 'G2',
            'before' => 2,
            'after' => 6,
            'extra_params.aid' => $this->aid,
            'extra_params.sid' => $this->sid,
        ]);
        // G1 still has only its original event - the G2 row did not re-fire it
        $I->seeNumElementsInCollection('events', 1, [
            'event_code' => self::EVENT_CODE_AND_OR,
            'matched_path.path' => 'balance.groups.G1.usagev',
        ]);
        // the duplicate's signature: a G1 event fired again by a row that
        // did not change G1
        $I->seeNumElementsInCollection('events', 0, ['event_code' => self::EVENT_CODE_AND_OR, 'before' => 10]);
    }

    /**
     * Input processor, rates, plan, one service with the two groups, and the
     * account + subscriber holding it - all created with the suite
     * generators. Ends with a config reload so the processor flow sees the
     * new file_type, and re-applies the in-process events override that the
     * reload wipes.
     */
    protected function seedCatalog(ApiTester $I, $eventSettings = null)
    {
        $eventSettings = $eventSettings ?: $this->eventSettings();
        $I->setSettings('file_types', $this->inputProcessor());

        $I->createAccountWithAllMandatoryCustomFields(['firstname' => 'events_cond_account']);
        $account = json_decode($I->grabResponse(), true)['entity'];
        $this->aid = $account['aid'];

        $I->generatePlan(['name' => self::PLAN, 'from' => '2025-01-01']);

        foreach ([self::RATE_G1, self::RATE_G2] as $rateKey) {
            $I->generateRate([
                'key' => $rateKey,
                'tariff_category' => 'retail',
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
                                    'uom_display' => ['range' => 'seconds', 'interval' => 'seconds'],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        }

        $I->generateService([
            'name' => self::SERVICE,
            'from' => '2025-01-01',
            'include' => [
                'groups' => [
                    'G1' => $this->groupDefinition(self::RATE_G1),
                    'G2' => $this->groupDefinition(self::RATE_G2),
                ],
            ],
        ]);

        $I->generateSubscriber([
            'from' => '2025-01-01',
            'firstname' => self::FIRSTNAME,
            'aid' => $account['aid'],
            'plan' => self::PLAN,
            'services' => [
                [
                    'name' => self::SERVICE,
                    'from' => '2025-01-01T00:00:00Z',
                    'to' => '2124-01-01T00:00:00Z',
                ],
            ],
        ]);
        $this->sid = json_decode($I->grabResponse(), true)['entity']['sid'];

        // load the new file_type into this process, then apply the events
        // under test on top of the loaded config
        Billrun_Config::getInstance()->loadDbConfig();
        Billrun_Factory::config()->setConfigValue('queue.calculators', ['customer', 'rate', 'pricing']);
        $this->replaceEventsConfig($eventSettings);
        $I->resetBillrunInstances();
    }

    /**
     * Fully replace the in-process events config with the event under test.
     * setConfigValue cannot be used here: it merges recursively over the
     * loaded config, so events left in the DB config (cleanDB does not clean
     * the config collection - e.g. a restored dump) would merge index-wise
     * into the event under test and silently change its conditions.
     */
    protected function replaceEventsConfig($eventSettings)
    {
        $config = Billrun_Factory::config();
        $configProp = new ReflectionProperty('Billrun_Config', 'config');
        $configProp->setAccessible(true);
        $values = $configProp->getValue($config)->toArray();
        $values['events'] = ['balance' => [$eventSettings]];
        $configProp->setValue($config, new Yaf_Config_Simple($values));
        // the manager caches the events config at construction time
        $this->resetEventsManager();
    }

    /**
     * Offline CSV input processor: customer matched by firstname, rate
     * matched by the CSV "rate" column.
     */
    protected function inputProcessor()
    {
        return [
            'file_type' => self::FILE_TYPE,
            'parser' => [
                'type' => 'separator',
                'line_types' => [
                    'H' => '/^none$/',
                    'D' => '//',
                    'T' => '/^none$/',
                ],
                'separator' => ',',
                'structure' => [
                    ['name' => 'firstname', 'checked' => true],
                    ['name' => 'date', 'checked' => true],
                    ['name' => 'rate', 'checked' => true],
                    ['name' => 'volume', 'checked' => true],
                ],
                'csv_has_header' => true,
                'csv_has_footer' => false,
            ],
            'processor' => [
                'type' => 'Usage',
                'date_field' => 'date',
                'default_usaget' => 'call',
                'default_unit' => 'seconds',
                'default_volume_src' => ['volume'],
            ],
            'customer_identification_fields' => [
                'call' => [[
                    'target_key' => 'firstname',
                    'src_key' => 'firstname',
                    'conditions' => [['field' => 'usaget', 'regex' => '/.*/']],
                    'clear_regex' => '//',
                ]],
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
                        'remote_directory' => '/home',
                    ],
                ],
            ],
            'enabled' => true,
        ];
    }

    protected function groupDefinition($rateKey)
    {
        return [
            'account_shared' => false,
            'account_pool' => false,
            'quantity_affected' => false,
            'rates' => [$rateKey],
            'value' => 100,
            'usage_types' => ['call' => ['unit' => 'seconds']],
        ];
    }

    /**
     * Same shape as a UI-defined balance event: two AND conditions, each
     * with the same two group paths (OR).
     */
    protected function eventSettings()
    {
        $paths = [
           
            [
                'path' => 'balance.groups.G2.usagev',
                'total_path' => 'balance.groups.G2.total',
                'related_entities' => [
                    ['type' => 'group', 'key' => 'G2'],
                    ['type' => 'service', 'key' => self::SERVICE],
                ],
            ],
        ];
        return [
            'active' => true,
            'event_code' => self::EVENT_CODE,
            'event_description' => 'AND conditions must hold per path',
            'conditions' => [
                [
                    'paths' =>  [
                        [
                            'path' => 'balance.groups.G1.usagev',
                            'total_path' => 'balance.groups.G1.total',
                            'related_entities' => [
                                ['type' => 'group', 'key' => 'G1'],
                                ['type' => 'service', 'key' => self::SERVICE],
                            ],
                        ]
                    ],
                    'unit' => 'seconds',
                    'usaget' => 'call',
                    'property_type' => 'time',
                    'type' => 'is_greater_than',
                    'value' => '5',
                ],
                [
                    'paths' =>  [
                        [
                            'path' => 'balance.groups.G2.usagev',
                            'total_path' => 'balance.groups.G2.total',
                            'related_entities' => [
                                ['type' => 'group', 'key' => 'G2'],
                                ['type' => 'service', 'key' => self::SERVICE],
                            ],
                        ]
                    ],
                    'unit' => 'seconds',
                    'usaget' => 'call',
                    'property_type' => 'time',
                    'type' => 'is_greater_than',
                    'value' => '5',
                ],
                 [
                    'paths' =>  [
                        [
                            'path' => 'balance.groups.G1.usagev',
                            'total_path' => 'balance.groups.G1.total',
                            'related_entities' => [
                                ['type' => 'group', 'key' => 'G1'],
                                ['type' => 'service', 'key' => self::SERVICE],
                            ],
                        ]
                    ],
                    'unit' => 'seconds',
                    'usaget' => 'call',
                    'property_type' => 'time',
                    'type' => 'is_less_than_or_equal',
                    'value' => '100',
                ],
                [
                    'paths' =>  [
                        [
                            'path' => 'balance.groups.G2.usagev',
                            'total_path' => 'balance.groups.G2.total',
                            'related_entities' => [
                                ['type' => 'group', 'key' => 'G2'],
                                ['type' => 'service', 'key' => self::SERVICE],
                            ],
                        ]
                    ],
                    'unit' => 'seconds',
                    'usaget' => 'call',
                    'property_type' => 'time',
                    'type' => 'is_less_than_or_equal',
                    'value' => '100',
                ],
            ],
        ];
    }

    /**
     * Same shape as the customer_portal event of BRCD-4637: a single
     * condition holding both group paths (OR between the groups).
     */
    protected function twoGroupsOrPathsEventSettings()
    {
        return [
            'active' => true,
            'event_code' => self::EVENT_CODE_OR,
            'event_description' => 'one condition, both groups as paths',
            'conditions' => [
                [
                    'paths' => [
                        [
                            'path' => 'balance.groups.G1.usagev',
                            'total_path' => 'balance.groups.G1.total',
                            'related_entities' => [
                                ['type' => 'group', 'key' => 'G1'],
                                ['type' => 'service', 'key' => self::SERVICE],
                            ],
                        ],
                        [
                            'path' => 'balance.groups.G2.usagev',
                            'total_path' => 'balance.groups.G2.total',
                            'related_entities' => [
                                ['type' => 'group', 'key' => 'G2'],
                                ['type' => 'service', 'key' => self::SERVICE],
                            ],
                        ],
                    ],
                    'unit' => 'seconds',
                    'usaget' => 'call',
                    'property_type' => 'time',
                    'type' => 'is_greater_than',
                    'value' => '90',
                ],
            ],
        ];
    }

    /**
     * The "wildcard1" event of the original report: AND between the two
     * conditions, OR between the two group paths inside each condition.
     */
    protected function andConditionsOrPathsEventSettings()
    {
        $paths = [
            [
                'path' => 'balance.groups.G1.usagev',
                'total_path' => 'balance.groups.G1.total',
                'related_entities' => [
                    ['type' => 'group', 'key' => 'G1'],
                    ['type' => 'service', 'key' => self::SERVICE],
                ],
            ],
            [
                'path' => 'balance.groups.G2.usagev',
                'total_path' => 'balance.groups.G2.total',
                'related_entities' => [
                    ['type' => 'group', 'key' => 'G2'],
                    ['type' => 'service', 'key' => self::SERVICE],
                ],
            ],
        ];
        return [
            'active' => true,
            'event_code' => self::EVENT_CODE_AND_OR,
            'event_description' => 'two AND conditions, both groups as paths in each',
            'conditions' => [
                [
                    'paths' => $paths,
                    'unit' => 'seconds',
                    'usaget' => 'call',
                    'property_type' => 'time',
                    'type' => 'is_greater_than',
                    'value' => '5',
                ],
                [
                    'paths' => $paths,
                    'unit' => 'seconds',
                    'usaget' => 'call',
                    'property_type' => 'time',
                    'type' => 'is_less_than_or_equal',
                    'value' => '100',
                ],
            ],
        ];
    }

    protected function resetEventsManager()
    {
        $instance = new ReflectionProperty('Billrun_EventsManager', 'instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
    }
}

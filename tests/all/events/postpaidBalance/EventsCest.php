<?php

class EventsCest
{
    const EVENT_CODE = 'AND_CONDITIONS_PER_PATH';
    const EVENT_CODE_OR = 'TWO_GROUPS_OR_PATHS';
    const EVENT_CODE_OR_CHANGED = 'TWO_GROUPS_OR_PATHS_HAS_CHANGED';
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
        // cleanDB wipes the events collection too - leftovers from a previous
        // run would break the exact-count assertions
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
     * CDR 2 puts 10 seconds in G1 - the ">5" condition on G2 still fails,
     * so the AND does not hold and no event may be created.
     * CDR 3 adds 4 seconds to G2 - all conditions hold, the event exists.
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
     * By design: a state condition (">90") has no memory - it is evaluated
     * against the balance after every update of the groups balance, so an
     * event whose condition lists both groups as paths (OR) is re-created
     * for EVERY matching group on EVERY such row:
     *
     * CDR 1 puts 95 seconds in G1 - one event (G1).
     * CDR 2 puts 95 seconds in G2 - two more (G2, and G1 again): 3 in total.
     * CDR 3 adds 2 in-group seconds to G1 - two more (G1 and G2 again): 5.
     *
     * An event definition that wants one event per group change must add a
     * has_changed condition - see e2eEventPerChangedGroupWithHasChanged.
     */
    public function e2eEventCreatedOnEveryMatchingRowWithoutHasChanged(ApiTester $I)
    {
        $this->seedCatalog($I, $this->twoGroupsOrPathsEventSettings());

        $I->processByPath([
            'type' => self::FILE_TYPE,
            'path' => self::TEST_FILES_PATH . 'events_conditions_95s_g1.csv',
        ]);
        $I->verifyCollectionRecord('lines', [
            'uf.firstname' => self::FIRSTNAME,
            'arate_key' => self::RATE_G1,
            'sid' => $this->sid,
            'in_group' => 95,
            'over_group' => 0,
        ]);
        $I->seeNumElementsInCollection('events', 1, ['event_code' => self::EVENT_CODE_OR]);
        $I->seeNumElementsInCollection('events', 1, [
            'event_code' => self::EVENT_CODE_OR,
            'before' => 0,
            'after' => 95,
            'extra_params.aid' => $this->aid,
            'extra_params.sid' => $this->sid,
        ]);

        $I->processByPath([
            'type' => self::FILE_TYPE,
            'path' => self::TEST_FILES_PATH . 'events_conditions_95s_g2.csv',
        ]);
        $I->verifyCollectionRecord('balances', [
            'sid' => $this->sid,
            'balance.groups.G1.usagev' => 95,
            'balance.groups.G2.usagev' => 95,
        ]);
        // G2's event, and one more for G1 - still ">90" after the row, even
        // though the row did not change it
        $I->seeNumElementsInCollection('events', 3, ['event_code' => self::EVENT_CODE_OR]);
        $I->seeNumElementsInCollection('events', 1, ['event_code' => self::EVENT_CODE_OR, 'before' => 95]);

        // the third row updates the groups balance (G1 95 -> 97), both
        // groups still match ">90" after it - one more event per group
        $I->processByPath([
            'type' => self::FILE_TYPE,
            'path' => self::TEST_FILES_PATH . 'events_conditions_2s_g1.csv',
        ]);
        $I->verifyCollectionRecord('balances', [
            'sid' => $this->sid,
            'balance.groups.G1.usagev' => 97,
            'balance.groups.G2.usagev' => 95,
        ]);
        $I->seeNumElementsInCollection('events', 5, ['event_code' => self::EVENT_CODE_OR]);
    }

    /**
     * The dedupe recipe for the scenario above: the same ">90" event plus a
     * has_changed condition listing both groups. Conditions are AND and the
     * event is saved per matching path of the last condition, so every row
     * creates exactly one event, for the group the row changed:
     *
     * CDR 1 puts 95 seconds in G1 - one event (G1, 0 -> 95).
     * CDR 2 puts 95 seconds in G2 - one event (G2, 0 -> 95); G1, still over
     * the threshold but unchanged, does not fire again.
     * CDR 3 adds 2 in-group seconds to G1 - one event (G1, 95 -> 97): the
     * group changed while over the threshold, so by design it fires again.
     */
    public function e2eEventPerChangedGroupWithHasChanged(ApiTester $I)
    {
        $this->seedCatalog($I, $this->twoGroupsOrPathsWithHasChangedEventSettings());

        $I->processByPath([
            'type' => self::FILE_TYPE,
            'path' => self::TEST_FILES_PATH . 'events_conditions_95s_g1.csv',
        ]);
        $I->seeNumElementsInCollection('events', 1, ['event_code' => self::EVENT_CODE_OR_CHANGED]);
        $I->seeNumElementsInCollection('events', 1, [
            'event_code' => self::EVENT_CODE_OR_CHANGED,
            'matched_path.path' => 'balance.groups.G1.usagev',
            'matched_path.related_entities.key' => 'G1',
            'before' => 0,
            'after' => 95,
            'extra_params.aid' => $this->aid,
            'extra_params.sid' => $this->sid,
        ]);

        $I->processByPath([
            'type' => self::FILE_TYPE,
            'path' => self::TEST_FILES_PATH . 'events_conditions_95s_g2.csv',
        ]);
        // exactly one more event, for G2; G1 (still over, unchanged) does
        // not fire again
        $I->seeNumElementsInCollection('events', 2, ['event_code' => self::EVENT_CODE_OR_CHANGED]);
        $I->seeNumElementsInCollection('events', 1, [
            'event_code' => self::EVENT_CODE_OR_CHANGED,
            'matched_path.path' => 'balance.groups.G2.usagev',
            'matched_path.related_entities.key' => 'G2',
            'before' => 0,
            'after' => 95,
            'extra_params.aid' => $this->aid,
            'extra_params.sid' => $this->sid,
        ]);
        // G1 still has only its original crossing event (0 -> 95)
        $I->seeNumElementsInCollection('events', 1, [
            'event_code' => self::EVENT_CODE_OR_CHANGED,
            'matched_path.path' => 'balance.groups.G1.usagev',
            'before' => 0,
            'after' => 95,
        ]);

        // the third row changes G1 (95 -> 97) while it is over the
        // threshold - by design exactly one more event, for G1 only
        $I->processByPath([
            'type' => self::FILE_TYPE,
            'path' => self::TEST_FILES_PATH . 'events_conditions_2s_g1.csv',
        ]);
        $I->seeNumElementsInCollection('events', 3, ['event_code' => self::EVENT_CODE_OR_CHANGED]);
        $I->seeNumElementsInCollection('events', 1, [
            'event_code' => self::EVENT_CODE_OR_CHANGED,
            'matched_path.path' => 'balance.groups.G1.usagev',
            'before' => 95,
            'after' => 97,
        ]);
        // G2 did not change - it still has only its crossing event (0 -> 95)
        $I->seeNumElementsInCollection('events', 1, [
            'event_code' => self::EVENT_CODE_OR_CHANGED,
            'matched_path.path' => 'balance.groups.G2.usagev',
            'before' => 0,
            'after' => 95,
        ]);
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
     * AND between the groups, no OR: two ">90" conditions, each holding a
     * single group path.
     */
    /**
     * The dedupe variant of the OR event (per Shani): the same ">90"
     * condition on both groups, plus a has_changed condition listing both
     * groups - so the event is saved only for the group the row changed.
     */
    protected function twoGroupsOrPathsWithHasChangedEventSettings()
    {
        $event = $this->twoGroupsOrPathsEventSettings();
        $event['event_code'] = self::EVENT_CODE_OR_CHANGED;
        $event['event_description'] = 'both groups as paths, has_changed per group';
        $event['conditions'][] = [
            'paths' => $event['conditions'][0]['paths'],
            'unit' => 'seconds',
            'usaget' => 'call',
            'property_type' => 'time',
            'type' => 'has_changed',
            'value' => '',
        ];
        return $event;
    }

    protected function resetEventsManager()
    {
        $instance = new ReflectionProperty('Billrun_EventsManager', 'instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
    }
}

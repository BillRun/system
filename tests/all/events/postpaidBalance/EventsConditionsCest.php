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
 */
class EventsConditionsCest
{
    const EVENT_CODE = 'AND_CONDITIONS_PER_PATH';
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

    public function _before(ApiTester $I)
    {
        $I->cleanDB();
        Billrun_Config::getInstance()->loadDbConfig();
        $this->originalEventsConfig = Billrun_Factory::config()->getConfigValue('events', []);
        Billrun_Factory::config()->setConfigValue('events.balance', [$this->eventSettings()]);//probably in version 6 should be already in new collection and not config
        // the manager caches the events config at construction time
        $this->resetEventsManager();
        $I->resetBillrunInstances();
    }

    public function _after(ApiTester $I)
    {
        Billrun_Factory::config()->setConfigValue('events', $this->originalEventsConfig);
        $this->resetEventsManager();
    }

    /**
     * A row adds 10 seconds to G1; G2 is untouched (0 -> 0).
     * G1 satisfies both ">5" and "<=100" - exactly one event is expected.
     * G2 fails ">5", so no event may be created for it, although it
     * trivially satisfies "<=100".
     */
    public function eventOnlyForPathMatchingAllConditions(ApiTester $I)
    {
        $before = $this->balanceEntity(0, 0);
        $after = $this->balanceEntity(10, 0);

        Billrun_Factory::eventsManager()->trigger(
            Billrun_EventsManager::EVENT_TYPE_BALANCE,
            $before,
            $after,
            [],
            [
                'aid' => self::AID,
                'sid' => self::SID,
                'row' => [
                    'usagev' => 10,
                    'urt' => 1728172800,
                    'usaget' => 'call',
                    'stamp' => 'events_conditions_cest',
                    'unit' => 'seconds',
                ],
            ]
        );

        $I->seeNumElementsInCollection('events', 1, ['event_code' => self::EVENT_CODE]);
        // the spurious G2 event is saved with before/after 0
        $I->dontSeeInCollection('events', ['event_code' => self::EVENT_CODE, 'after' => 0]);
        $I->seeInCollection('events', ['event_code' => self::EVENT_CODE, 'before' => 0, 'after' => 10]);
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
        ]);

        $I->seeNumElementsInCollection('events', 1, ['event_code' => self::EVENT_CODE]);
        // the spurious G2 event carries G2's untouched counter
        $I->dontSeeInCollection('events', ['event_code' => self::EVENT_CODE, 'after' => 2]);
        $I->seeInCollection('events', ['event_code' => self::EVENT_CODE, 'before' => 0, 'after' => 10]);
    }

    /**
     * Input processor, rates, plan, one service with the two groups, and the
     * account + subscriber holding it - all created with the suite
     * generators. Ends with a config reload so the processor flow sees the
     * new file_type, and re-applies the in-process events override that the
     * reload wipes.
     */
    protected function seedCatalog(ApiTester $I)
    {
        $I->setSettings('file_types', $this->inputProcessor());

        $I->createAccountWithAllMandatoryCustomFields(['firstname' => 'events_cond_account']);
        $account = json_decode($I->grabResponse(), true)['entity'];

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

        // load the new file_type into this process, then re-apply the
        // in-process overrides the reload just wiped
        Billrun_Config::getInstance()->loadDbConfig();
        Billrun_Factory::config()->setConfigValue('events.balance', [$this->eventSettings()]);
        Billrun_Factory::config()->setConfigValue('queue.calculators', ['customer', 'rate', 'pricing']);
        $this->resetEventsManager();
        $I->resetBillrunInstances();
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

    protected function balanceEntity($g1Usagev, $g2Usagev)
    {
        return [
            'aid' => self::AID,
            'sid' => self::SID,
            'balance' => [
                'groups' => [
                    'G1' => ['usagev' => $g1Usagev, 'total' => 100],
                    'G2' => ['usagev' => $g2Usagev, 'total' => 100],
                ],
            ],
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
            'event_code' => self::EVENT_CODE,
            'event_description' => 'AND conditions must hold per path',
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

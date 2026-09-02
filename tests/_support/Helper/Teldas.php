<?php
namespace Helper;

/**
 * Shared fixtures and helpers for the teldas plugin acceptance tests
 * (teldasFullProcessCest + teldasRealtimeFullProcessCest). All generic teldas
 * test logic lives here so it is not duplicated across the Cests.
 *
 * Delegates to the BillRunAPI module (via api()) to reuse the generate / create
 * entity helpers and getLastEntity. It can't extend BillRunAPI: both are enabled
 * modules and the actor would then see each action twice (module conflict).
 */
class Teldas extends \Codeception\Module
{
    /** Dialed INA number with no tariff (the BRCD-5292 fixture). */
    const INA_NUMBER = '0800000523';

    /** Rate keys: ina_vas_call must resolve to the teldas rate, not the generic. */
    const TELDAS_RATE_KEY  = 'TELDAS_INA';
    const GENERIC_RATE_KEY = 'REGULAR_CALL';

    /** teldas plugin collections cleaned between tests. */
    const COLLECTIONS = [
        'plugin_teldas_ina_numbers',
        'plugin_teldas_tariffs_profiles',
        'plugin_teldas_tariff_switching_classes',
        'plugin_teldas_non_working_days',
    ];

    /**
     * The BillRunAPI module (entity generators + getLastEntity + setPluginSettings).
     * Teldas can't extend BillRunAPI: both are enabled modules, so inheriting its
     * public methods would make the actor see each action defined twice and fail
     * with a module-conflict. Delegate through getModule() instead (same pattern
     * as the MongoDb module use below).
     *
     * @return \Helper\BillRunAPI
     */
    protected function api()
    {
        return $this->getModule('\Helper\BillRunAPI');
    }

    public function cleanTeldasCollections()
    {
        foreach (self::COLLECTIONS as $name) {
            $collection = \Billrun_Factory::db()->getCollection($name);
            if ($collection) {
                $collection->remove(['_id' => ['$exists' => true]]);
            }
        }
    }

    public function teldasBsonDate($strOrTs)
    {
        $ts = is_string($strOrTs) ? strtotime($strOrTs) : (int) $strOrTs;
        return new \MongoDB\BSON\UTCDateTime($ts * 1000);
    }

    /** teldas plugin options for a given file_type/line_type. */
    public function teldasPluginOptions($fileType)
    {
        return [
            'url'      => 'https://ws.test.numberportability.ch',
            'user'     => 'test',
            'password' => 'test',
            'ina_number_prefixes' => '/^(0800|0848|0900|0901|0906|0840|0842|0844|0878)|^18[0-9][0-9]$/',
            'matching_paths' => [
                [
                    'line_type' => $fileType,
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
    }

    /**
     * Attach the teldas plugin to the in-process dispatcher so its hooks fire
     * during an in-process file-process run (Bootstrap, which normally attaches
     * plugins from config, does not run inside the test process).
     */
    public function enableTeldasPlugin($options)
    {
        // Billrun_Dispatcher keeps a SEPARATE instance per nested run-level
        // ('default0', 'default1', ...), each a lazy clone of default0. The queue
        // calculators fire beforeGetLineAprice from a nested level (run=1), so a
        // clone of default0 made BEFORE we attach (it already exists by the time
        // _before runs) has no teldas - and attaching only to the current instance
        // (default0) misses it. Attach a single plugin instance to EVERY existing
        // dispatcher instance; clones created later derive from default0 (which now
        // carries it). One instance only -> no double-pricing / retry loop, and no
        // config registration (that also created the stale clone).
        $plugin = new \teldasPlugin($options);
        $plugin->setAvailability(true);
        $plugin->setOptions(array_merge($options, ['enabled' => true]));

        $instProp = new \ReflectionProperty('Billrun_Dispatcher', 'instance');
        $instProp->setAccessible(true);
        $instances = $instProp->getValue();
        $attached = false;
        foreach ($instances as $key => $inst) {
            if (strpos($key, 'default') === 0 && $inst instanceof \Billrun_Spl_Subject) {
                $inst->attach($plugin);
                $attached = true;
            }
        }
        if (!$attached) {
            \Billrun_Dispatcher::getInstance()->attach($plugin);
        }
    }

    /**
     * Remove every teldasPlugin observer from the (process-wide singleton)
     * dispatcher. Billrun_Spl_Subject::detach() has an off-by-one bug for the
     * observer at index 0, so we filter the observers list via reflection.
     */
    public function disableTeldasPlugin()
    {
        // Remove teldas from EVERY dispatcher instance (enableTeldasPlugin attached
        // it to all of them). detach() has an off-by-one bug for the observer at
        // index 0, so filter the observers list via reflection.
        $obsProp = new \ReflectionProperty('Billrun_Spl_Subject', 'observers');
        $obsProp->setAccessible(true);
        $instProp = new \ReflectionProperty('Billrun_Dispatcher', 'instance');
        $instProp->setAccessible(true);
        foreach ($instProp->getValue() as $inst) {
            if ($inst instanceof \Billrun_Spl_Subject) {
                $obsProp->setValue($inst, array_values(array_filter($obsProp->getValue($inst), function ($o) {
                    return !($o instanceof \teldasPlugin);
                })));
            }
        }
    }

    /**
     * Register teldas in config (DB). Use for the realtime flow: /realtime is
     * handled by the web container, which bootstraps plugins from config per
     * request, so a test-process dispatcher attach would not reach it.
     */
    public function enableTeldasPluginInConfig($options)
    {
        $this->api()->setPluginSettings([
            'name'          => 'teldasPlugin',
            'enabled'       => true,
            'system'        => true,
            'hide_from_ui'  => true,
            'configuration' => ['values' => $options],
        ]);
        \Billrun_Config::getInstance()->loadDbConfig();
    }

    /** Disable the teldas plugin in config so it does not leak into other tests. */
    public function disableTeldasPluginInConfig()
    {
        $this->api()->setPluginSettings([
            'name'         => 'teldasPlugin',
            'enabled'      => false,
            'system'       => true,
            'hide_from_ui' => true,
        ]);
        \Billrun_Config::getInstance()->loadDbConfig();
    }

    /** Insert the BRCD-5292 no-tariff / ALLNO INA revision verbatim. */
    public function haveNoTariffInaNumber($subscriberNumber = self::INA_NUMBER)
    {
        $this->getModule('MongoDb')->haveInCollection('plugin_teldas_ina_numbers', [
            'subscriberNumber'      => $subscriberNumber,
            'status'                => 'ALLNO',
            'tspId'                 => 98000,
            'tariffProfile'         => null,
            'tariffProfileType'     => null,
            'accessAbroad'          => null,
            'activationDatetime'    => null,
            'expirationDatetime'    => null,
            'modificationDatetime'  => null,
            'terminationDatetime'   => null,
            'transactionDatetime'   => $this->teldasBsonDate('2025-12-11 00:48:00'),
            'modifyPending'         => false,
            'transactionDatetimeTo' => null,
        ]);
    }

    /**
     * Create account + plan + subscriber + two rates (teldas + generic).
     *
     * @return array ['account' => entity, 'subscriber' => entity, 'plan' => entity]
     */
    public function createTeldasFixtures($subscriberFirstname = '0700000001', $planPrefix = 'TELDAS_TEST_PLAN_')
    {
        $this->api()->generatePlan(['name' => $planPrefix . (int) (microtime(true) * 10000), 'from' => '2025-01-01']);
        $plan = $this->api()->getLastEntity();

        // Teldas rate - the INA (ina_vas_call) line must resolve to this one.
        $this->api()->generateRate([
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

        // Generic (non-teldas) rate - must NOT be picked for an INA call.
        $this->api()->generateRate([
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

        $this->api()->createAccountWithAllMandatoryCustomFields(['firstname' => 'teldas_test_account']);
        $account = $this->api()->getLastEntity();

        $this->api()->generateSubscriber([
            'from'      => '2025-01-01',
            'firstname' => $subscriberFirstname,
            'aid'       => $account['aid'],
            'plan'      => $plan['name'],
        ]);
        $subscriber = $this->api()->getLastEntity();

        return ['account' => $account, 'subscriber' => $subscriber, 'plan' => $plan];
    }
}

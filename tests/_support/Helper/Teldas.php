<?php
namespace Helper;

/**
 * Shared fixtures and helpers for the teldas plugin acceptance tests
 * (teldasFullProcessCest + teldasRealtimeFullProcessCest). All generic teldas
 * test logic lives here so it is not duplicated across the Cests.
 *
 * Extends BillRunAPI (like RealTimeApiHelper) so it can reuse the generate /
 * create entity helpers and getLastEntity directly.
 */
class Teldas extends BillRunAPI
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
        // 1) Register the plugin in config. The queue calculators (e.g. pricing,
        //    which fires beforeGetLineAprice) resolve plugins from config, so a
        //    dispatcher-only attach is not enough - without this the plugin is
        //    absent during pricing and a no-tariff line gets priced (0) and
        //    dequeued instead of staying in the queue.
        $this->setPluginSettings([
            'name'          => 'teldasPlugin',
            'enabled'       => true,
            'system'        => true,
            'hide_from_ui'  => true,
            'configuration' => ['values' => $options],
        ]);
        \Billrun_Config::getInstance()->loadDbConfig();

        // 2) Attach to the in-process dispatcher - the parsing stage
        //    (afterGetLineUsageType) fires through it during processByPath.
        $plugin = new \teldasPlugin($options);
        $plugin->setAvailability(true);
        $plugin->setOptions(array_merge($options, ['enabled' => true]));
        \Billrun_Dispatcher::getInstance()->attach($plugin);
    }

    /**
     * Remove every teldasPlugin observer from the (process-wide singleton)
     * dispatcher. Billrun_Spl_Subject::detach() has an off-by-one bug for the
     * observer at index 0, so we filter the observers list via reflection.
     */
    public function disableTeldasPlugins()
    {
        // Detach from the in-process dispatcher.
        $dispatcher = \Billrun_Dispatcher::getInstance();
        $prop = new \ReflectionProperty('Billrun_Spl_Subject', 'observers');
        $prop->setAccessible(true);
        $observers = array_values(array_filter($prop->getValue($dispatcher), function ($o) {
            return !($o instanceof \teldasPlugin);
        }));
        $prop->setValue($dispatcher, $observers);

        // Disable in config so it does not leak into subsequent tests.
        $this->setPluginSettings([
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
        $this->generatePlan(['name' => $planPrefix . (int) (microtime(true) * 10000), 'from' => '2025-01-01']);
        $plan = $this->getLastEntity();

        // Teldas rate - the INA (ina_vas_call) line must resolve to this one.
        $this->generateRate([
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
        $this->generateRate([
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

        $this->createAccountWithAllMandatoryCustomFields(['firstname' => 'teldas_test_account']);
        $account = $this->getLastEntity();

        $this->generateSubscriber([
            'from'      => '2025-01-01',
            'firstname' => $subscriberFirstname,
            'aid'       => $account['aid'],
            'plan'      => $plan['name'],
        ]);
        $subscriber = $this->getLastEntity();

        return ['account' => $account, 'subscriber' => $subscriber, 'plan' => $plan];
    }
}

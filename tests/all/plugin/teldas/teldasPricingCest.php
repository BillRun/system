<?php

/**
 * teldasPricingCest
 *
 * Exercises teldasPlugin::pricingCdr for CDRs that hit a teldas INA number.
 *
 * The tests construct an in-memory $line that mirrors what an input processor
 * would produce for a call to a teldas number (line['type'] set to a synthetic
 * file_type, uf.Subscriber_Number, uf.Duration_Seconds, urt) and invoke the
 * protected pricingCdr() via reflection on a freshly enabled teldasPlugin.
 * Fixture documents are inserted into the teldas Mongo collections per-test
 * (and torn down in _after) so each scenario is hermetic.
 *
 * The input-processor name and field paths used here are intentionally
 * synthetic ("TeldasTestInput", "uf.Subscriber_Number", "uf.Duration_Seconds")
 * so the tests don't depend on any specific real input-processor definition.
 *
 * Scenarios covered:
 *   1. INA-number revision matching (non-matching / terminated / converted
 *      prefix / out-of-revision-window)
 *   2. Online tariff profile pricing - single chargeConfiguration sequence
 *   3. Offline-a tariff profile pricing - weekday / saturday / sunday-holiday
 *      and FIX_PRICE drop-charge and Production-reference smoke tests: real prod CDR examples using files results (XDR)
 *   4. call_offset CDR-split behavior (BRCD-5294): baseCharge & startInterval
 *      apply only on the first CDR of a split call (online), and previously
 *      consumed capacity is skipped on offline-a multi-sequence.
 */
class teldasPricingCest
{
    protected $vat = 0.081;
    protected $epsilon = 0.001;

    /** Synthetic input-processor / line_type name used in fixtures. */
    const TEST_LINE_TYPE = 'TeldasTestInput';

    /** Plugin options injected for every test. The matching_paths shape is the
     *  same as production; only the line_type / field paths are synthetic. */
    protected $pluginOptions = [
        'url'      => 'https://ws.test.numberportability.ch',
        'user'     => 'test',
        'password' => 'test',
        'ina_number_prefixes' => '/^(0800|0848|0900|0901|0906|0840|0842|0844|0878)|^18[0-9][0-9]$/',
        'matching_paths' => [
            [
                'line_type' => self::TEST_LINE_TYPE,
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

    /** Collections cleared before each test. */
    protected $teldasCollections = [
        'plugin_teldas_ina_numbers',
        'plugin_teldas_tariffs_profiles',
        'plugin_teldas_tariff_switching_classes',
        'plugin_teldas_non_working_days',
    ];

    public function _before(AcceptanceTester $I)
    {
        $this->cleanTeldasCollections();
        $I->setTimezone('Europe/Zurich');
    }

    public function _after(AcceptanceTester $I)
    {
        $this->cleanTeldasCollections();
        $I->restoreTimezone();
    }

    protected function cleanTeldasCollections()
    {
        foreach ($this->teldasCollections as $name) {
            $collection = \Billrun_Factory::db()->getCollection($name);
            if ($collection) {
                $collection->remove(['_id' => ['$exists' => true]]);
            }
        }
    }

    /* ---------- fixture builders ---------- */

    /** UTCDateTime helper for fixture documents. */
    protected function bsonDate($strOrTs)
    {
        $ts = is_string($strOrTs) ? strtotime($strOrTs) : (int) $strOrTs;
        return new \MongoDB\BSON\UTCDateTime($ts * 1000);
    }

    /**
     * Insert an INA number revision. Defaults to an open-ended, currently-valid
     * window matching activationDatetime < urt < terminationDatetime.
     */
    protected function insertInaNumber(AcceptanceTester $I, $subscriberNumber, $tariffProfileId, array $override = [])
    {
        $doc = array_merge([
            'subscriberNumber'        => $subscriberNumber,
            'status'                  => 'SRVIN',
            'tspId'                   => 98010,
            'tariffProfile'           => $tariffProfileId,
            'tariffProfileType'       => $tariffProfileId >= 10000 ? 'ONLINE' : 'OFFLINE_A',
            'accessAbroad'            => false,
            'activationDatetime'      => '2010-01-01T00:00:00',
            'expirationDatetime'      => null,
            'modificationDatetime'    => '2010-01-01T00:00:00',
            'terminationDatetime'     => null,
            'transactionDatetime'     => $this->bsonDate('2010-01-01 00:00:00'),
            'transactionDatetimeTo'   => null,
            'modifyPending'           => false,
        ], $override);
        $I->haveInCollection('plugin_teldas_ina_numbers', $doc);
    }

    /** Insert an ONLINE tariff profile with a single chargeConfiguration sequence. */
    protected function insertOnlineTariffProfileSingleSequence(
        AcceptanceTester $I,
        $id,
        array $sequence,
        array $override = []
    ) {
        $doc = array_merge([
            'tspId'                  => 98010,
            'tariffProfileType'      => 'ONLINE',
            'id'                     => $id,
            'industryStandard'       => false,
            'serviceRate'            => 'PRS',
            'validDateTimeFrom'      => '2000-01-01T00:00:00',
            'validDateTimeTo'        => null,
            'transactionDateTime'    => $this->bsonDate('2000-01-01 00:00:00'),
            'transactionDateTimeTo'  => null,
            'tariffSwitchingClassId' => 1,
            'chargeConfigurations'   => [array_merge([
                'sequence'      => 1,
                'chargeRate'    => 0,
                'timeBetweenPulse' => null,
                'startInterval' => 0,
                'baseCharge'    => 0,
            ], $sequence)],
        ], $override);
        $I->haveInCollection('plugin_teldas_tariffs_profiles', $doc);
    }

    /** Insert an OFFLINE_A tariff profile with explicit weekday/saturday/sunday configs. */
    protected function insertOfflineATariffProfile(
        AcceptanceTester $I,
        $id,
        array $weekday,
        array $saturday = null,
        array $sundayAndHoliday = null,
        array $override = []
    ) {
        if ($saturday === null) {
            $saturday = $weekday;
        }
        if ($sundayAndHoliday === null) {
            $sundayAndHoliday = $weekday;
        }
        $doc = array_merge([
            'tspId'                 => 98010,
            'tariffProfileType'     => 'OFFLINE_A',
            'id'                    => $id,
            'industryStandard'      => false,
            'serviceRate'           => 'PRS',
            'validDateTimeFrom'     => '2000-01-01T00:00:00',
            'validDateTimeTo'       => null,
            'transactionDateTime'   => $this->bsonDate('2000-01-01 00:00:00'),
            'transactionDateTimeTo' => null,
            'weekChargeConfiguration' => [
                'weekday'          => $weekday,
                'saturday'         => $saturday,
                'sundayAndHoliday' => $sundayAndHoliday,
            ],
        ], $override);
        $I->haveInCollection('plugin_teldas_tariffs_profiles', $doc);
    }

    /* ---------- line builder ---------- */

    /**
     * Build a synthetic teldas-input CDR line.
     *
     * @param string $subscriberNumber  value at uf.Subscriber_Number (pre-conversion)
     * @param int    $durationSeconds   uf.Duration_Seconds
     * @param string $urtString         strtotime-compatible call-segment start
     * @param int    $callOffsetSeconds seconds elapsed before this segment (same unit as duration)
     */
    protected function makeLine($subscriberNumber, $durationSeconds, $urtString, $callOffsetSeconds = 0)
    {
        return [
            'type'        => self::TEST_LINE_TYPE,
            'usaget'      => 'ina_vas_call',
            'stamp'       => 'teldas-test-' . md5($subscriberNumber . $urtString . $durationSeconds . $callOffsetSeconds),
            'urt'         => new \MongoDate(strtotime($urtString)),
            'call_offset' => $callOffsetSeconds,
            'uf'          => [
                'Subscriber_Number' => $subscriberNumber,
                'Duration_Seconds'  => $durationSeconds,
            ],
        ];
    }

    /* ---------- pricing invocation via reflection ---------- */

    protected function priceLine($line)
    {
        $plugin = new \teldasPlugin($this->pluginOptions);
        $method = new \ReflectionMethod($plugin, 'pricingCdr');
        $method->setAccessible(true);
        return $method->invoke($plugin, $line);
    }

    /* ============================================================
     *  INA-number matching tests
     * ============================================================ */

    public function testInaMatching_noMatchingSubscriberNumber_returnsFalse(AcceptanceTester $I)
    {
        $this->insertInaNumber($I, '0844111222', 10001);

        $line = $this->makeLine('0791234567', 60, '2026-05-13 10:00:00');
        $price = $this->priceLine($line);

        $I->assertFalse($price, 'Pricing must fail when no INA revision matches the line subscriber number');
    }

    public function testInaMatching_terminatedNumber_returnsFalse(AcceptanceTester $I)
    {
        $this->insertOnlineTariffProfileSingleSequence($I, 10001, [
            'chargeRate' => 12, 'baseCharge' => 5, 'startInterval' => 10,
        ]);
        $this->insertInaNumber($I, '0844111222', 10001, [
            'activationDatetime'  => '2020-01-01T00:00:00',
            'terminationDatetime' => '2025-01-01T00:00:00',
        ]);

        $line = $this->makeLine('0844111222', 60, '2026-05-13 10:00:00');
        $price = $this->priceLine($line);

        $I->assertFalse($price, 'Terminated INA revisions must not be priced');
    }

    public function testInaMatching_numberConversionStripsLeading41(AcceptanceTester $I)
    {
        // Subscriber_Number "41844111222" (11 chars: "41" + 9 digits) is rewritten
        // to "0844111222" by the second conversion pattern.
        $this->insertOfflineATariffProfile($I, 5001, [
            ['sequence' => 1, 'rate' => 50, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT'],
        ]);
        $this->insertInaNumber($I, '0844111222', 5001);

        $line = $this->makeLine('41844111222', 60, '2026-05-13 10:00:00'); // Wed 10:00 -> weekday
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta(0.5, $price, $this->epsilon,
            'Conversion regex must rewrite "41" prefix to "0" and price the resolved INA');
    }

    public function testInaMatching_outOfRevisionWindow_returnsFalse(AcceptanceTester $I)
    {
        $this->insertOnlineTariffProfileSingleSequence($I, 10001, [
            'chargeRate' => 12, 'baseCharge' => 5, 'startInterval' => 10,
        ]);
        $this->insertInaNumber($I, '0844111222', 10001, [
            'transactionDatetime'   => $this->bsonDate('2024-01-01 00:00:00'),
            'transactionDatetimeTo' => $this->bsonDate('2024-06-01 00:00:00'),
        ]);

        $line = $this->makeLine('0844111222', 60, '2026-05-13 10:00:00');
        $price = $this->priceLine($line);

        $I->assertFalse($price, 'No revision matching the urt must produce no price');
    }

    /* ============================================================
     *  ONLINE tariff-profile pricing
     * ============================================================ */

    public function testOnlinePricing_singleSequence_baseChargeAndChargeRate(AcceptanceTester $I)
    {
        // chargeRate 12 cents/min, baseCharge 5 cents, startInterval 10 sec free.
        // 60-second call, first CDR -> applyBaseCharge=true, full startInterval pool.
        // price = baseCharge/100 + chargeRate/100/60 * max(60-10, 0)
        //       = 0.05         + 0.12/60 * 50 = 0.05 + 0.1 = 0.15
        $this->insertOnlineTariffProfileSingleSequence($I, 10001, [
            'chargeRate' => 12, 'baseCharge' => 5, 'startInterval' => 10,
        ]);
        $this->insertInaNumber($I, '0844111222', 10001);

        $line = $this->makeLine('0844111222', 60, '2026-05-13 10:00:00');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta(0.15, $price, $this->epsilon,
            'Online single-sequence pricing must apply baseCharge plus chargeRate over duration past startInterval');
    }

    public function testOnlinePricing_zeroDuration_onlyBaseCharge(AcceptanceTester $I)
    {
        $this->insertOnlineTariffProfileSingleSequence($I, 10002, [
            'chargeRate' => 12, 'baseCharge' => 5, 'startInterval' => 10,
        ]);
        $this->insertInaNumber($I, '0844111222', 10002);

        $line = $this->makeLine('0844111222', 0, '2026-05-13 10:00:00');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta(0.05, $price, $this->epsilon,
            'Zero-duration first CDR should still bill the online baseCharge');
    }

    public function testOnlinePricing_durationWithinStartInterval_onlyBaseCharge(AcceptanceTester $I)
    {
        $this->insertOnlineTariffProfileSingleSequence($I, 10003, [
            'chargeRate' => 12, 'baseCharge' => 5, 'startInterval' => 10,
        ]);
        $this->insertInaNumber($I, '0844111222', 10003);

        $line = $this->makeLine('0844111222', 8, '2026-05-13 10:00:00');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta(0.05, $price, $this->epsilon,
            'Duration entirely inside startInterval must charge only baseCharge');
    }

     /* ============================================================
     *  OFFLINE-A tariff-profile pricing
     * ============================================================ */

    public function testOfflineA_weekday_proRata(AcceptanceTester $I)
    {
        $this->insertOfflineATariffProfile($I, 4001, [
            ['sequence' => 1, 'rate' => 50, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT'],
        ]);
        $this->insertInaNumber($I, '0900111000', 4001);

        $line = $this->makeLine('0900111000', 60, '2026-05-13 10:00:00'); // Wednesday
        $price = $this->priceLine($line);

        // useRuleDuration = 60 / 60 = 1; ruleDuration = INF -> aprice = 1 * 50 / 100 = 0.5
        $I->assertEqualsWithDelta(0.5, $price, $this->epsilon,
            'Weekday PRO_RATA should price 60s at rate=50/60s = 0.50 CHF');
    }

    public function testOfflineA_saturday_usesSaturdayConfig(AcceptanceTester $I)
    {
        $this->insertOfflineATariffProfile(
            $I,
            4002,
            [['sequence' => 1, 'rate' => 50, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT']],
            [['sequence' => 1, 'rate' => 70, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT']]
        );
        $this->insertInaNumber($I, '0900111000', 4002);

        // 2026-05-16 is a Saturday
        $line = $this->makeLine('0900111000', 60, '2026-05-16 10:00:00');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta(0.7, $price, $this->epsilon,
            'Saturday config must override weekday config for offline-a pricing');
    }

    public function testOfflineA_sundayAndHoliday_usesSundayConfig(AcceptanceTester $I)
    {
        $this->insertOfflineATariffProfile(
            $I,
            4003,
            [['sequence' => 1, 'rate' => 50, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT']],
            [['sequence' => 1, 'rate' => 70, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT']],
            [['sequence' => 1, 'rate' => 90, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT']]
        );
        $this->insertInaNumber($I, '0900111000', 4003);

        // 2026-05-17 is a Sunday
        $line = $this->makeLine('0900111000', 60, '2026-05-17 10:00:00');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta(0.9, $price, $this->epsilon,
            'Sunday config must override weekday config for offline-a pricing');
    }

    protected function graceDropProRataSequences($seq3Time)
    {
        return [
            ['sequence' => 1, 'rate' => 0,  'time' => 15,        'ruleType' => 'PRO_RATA',  'ruleDuration' => 1, 'sign' => 'DEBIT'],
            ['sequence' => 2, 'rate' => 10, 'time' => 1,         'ruleType' => 'FIX_PRICE', 'ruleDuration' => 1, 'sign' => 'DEBIT'],
            ['sequence' => 3, 'rate' => 10, 'time' => $seq3Time, 'ruleType' => 'PRO_RATA',  'ruleDuration' => 0, 'sign' => 'DEBIT'],
        ];
    }

    public function testProdReference_call_1510s_tariff_2640(AcceptanceTester $I)
    {
        // Prod CDR:
        //   uf.Called_Number    = "900530520" (subscriber_number "0900530520" after conversion)
        //   uf.Chargeable_units = "001510"     (1510 seconds)
        //   uf.Tariff_Class     = "002640"
        //   Charging_Date       = 250519  (2025-05-19, Monday -> weekday)
        //   Charge_start_time   = 105812  (10:58:12 Zurich)
        //   aprice              = 13.876
        //
        $duration = 1510;
        $expectedAprice = 13.876;
        $this->insertOfflineATariffProfile($I, 2640, [
            ['sequence' => 1, 'rate' => 150, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 10, 'sign' => 'DEBIT'],
            ['sequence' => 2, 'rate' => 0,  'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT'],
        ]);
        $this->insertInaNumber($I, '0900530520', 2640);

        $line = $this->makeLine('0900530520', $duration, '2025-05-19 10:58:12');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta($expectedAprice*(1+$this->vat), $price, $this->epsilon,
            'Plugin must reproduce prod aprice 13.876 for a 1510s call to 0900530520');
    }

    public function testProdReference_call_138s_tariff_2640(AcceptanceTester $I)
    {
        // Prod CDR:
        //   uf.Called_Number    = "900530520" (subscriber_number "0900530520" after conversion)
        //   uf.Chargeable_units = "000138"     (138 seconds)
        //   uf.Tariff_Class     = "002640"
        //   Charging_Date       = 240902  (2024-09-02, Monday -> weekday)
        //   Charge_start_time   = 125652  (12:56:52 Zurich)
        //   aprice              = 3.191
        //
        $duration = 138;
        $expectedAprice = 3.191;
        $chargeRate = $expectedAprice * 6000.0 / $duration;
        $this->insertOfflineATariffProfile($I, 2640, [
            ['sequence' => 1, 'rate' => 150, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 10, 'sign' => 'DEBIT'],
            ['sequence' => 2, 'rate' => 0,  'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT'],
        ]);
        $this->insertInaNumber($I, '0900530520', 2640);

        $line = $this->makeLine('0900530520', $duration, '2024-09-02 12:56:52');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta($expectedAprice*(1+$this->vat), $price, $this->epsilon,
            'Plugin must reproduce prod aprice 3.191 for a 138s call to 0900530520');
    }

    public function testProdReference_call_32s_tariff_1(AcceptanceTester $I)
    {
        // Prod CDR:
        //   uf.Called_Number    = "900144033" (subscriber_number "0900144033" after conversion)
        //   uf.Chargeable_units = "000032"     (32 seconds)
        //   uf.Tariff_Class     = "000001"
        //   Charging_Date       = 250623  (2025-06-23, Monday -> weekday)
        //   Charge_start_time   = 182951  (18:29:51 Zurich)
        //   aprice              = 0.2466
        //   final_charge        = 0.2666
        //
        // Tariff 1 is a flat PRO_RATA rate=50 / time=60 (INF capacity), so
        //   plugin output = (32/60) * 0.50 = 0.26666 = expectedAprice*(1+vat).
        $duration = 32;
        $expectedAprice = 0.2466;
        $this->insertOfflineATariffProfile($I, 1, [
            ['sequence' => 1, 'rate' => 50, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT'],
        ]);
        $this->insertInaNumber($I, '0900144033', 1);

        $line = $this->makeLine('0900144033', $duration, '2025-06-23 18:29:51');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta($expectedAprice*(1+$this->vat), $price, $this->epsilon,
            'Plugin must reproduce prod aprice 0.2466 (final_charge 0.2666) for a 32s call to 0900144033');
    }

    public function testProdReference_call_4109s_tariff_2335(AcceptanceTester $I)
    {
        // Prod CDR:
        //   uf.Called_Number    = "900111024" (subscriber_number "0900111024" after conversion)
        //   uf.Chargeable_units = "004109"     (4109 seconds)
        //   uf.Tariff_Class     = "002335"
        //   Charging_Date       = 250623  (2025-06-23, Monday -> weekday)
        //   Charge_start_time   = 143859  (14:38:59 Zurich)
        //   aprice              = 1.9861
        //   final_charge        = 2.147
        //
        // Tariff 2335: 15s free, 0.10 drop, then 0.10 per 200s. For 4109s:
        //   0.10 + (4094/200) * 0.10 = 2.147 = expectedAprice*(1+vat).
        $duration = 4109;
        $expectedAprice = 1.9861;
        $this->insertOfflineATariffProfile($I, 2335, $this->graceDropProRataSequences(200));
        $this->insertInaNumber($I, '0900111024', 2335);

        $line = $this->makeLine('0900111024', $duration, '2025-06-23 14:38:59');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta($expectedAprice*(1+$this->vat), $price, $this->epsilon,
            'Plugin must reproduce prod aprice 1.9861 (final_charge 2.147) for a 4109s call to 0900111024');
    }

    public function testProdReference_call_297s_tariff_2335(AcceptanceTester $I)
    {
        // Prod CDR:
        //   uf.Called_Number    = "900111024" (subscriber_number "0900111024" after conversion)
        //   uf.Chargeable_units = "000297"     (297 seconds)
        //   uf.Tariff_Class     = "002335"
        //   Charging_Date       = 250613  (2025-06-13, Friday -> weekday)
        //   Charge_start_time   = 112120  (11:21:20 Zurich)
        //   aprice              = 0.222941721
        //   final_charge        = 0.241
        //
        // 0.10 + (282/200) * 0.10 = 0.241 = expectedAprice*(1+vat).
        $duration = 297;
        $expectedAprice = 0.222941721;
        $this->insertOfflineATariffProfile($I, 2335, $this->graceDropProRataSequences(200));
        $this->insertInaNumber($I, '0900111024', 2335);

        $line = $this->makeLine('0900111024', $duration, '2025-06-13 11:21:20');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta($expectedAprice*(1+$this->vat), $price, $this->epsilon,
            'Plugin must reproduce prod aprice 0.222941721 (final_charge 0.241) for a 297s call to 0900111024');
    }

    public function testProdReference_call_132s_tariff_2335(AcceptanceTester $I)
    {
        // Prod CDR:
        //   uf.Called_Number    = "900111024" (subscriber_number "0900111024" after conversion)
        //   uf.Chargeable_units = "000132"     (132 seconds)
        //   uf.Tariff_Class     = "002335"
        //   Charging_Date       = 250622  (2025-06-22, Sunday -> sundayAndHoliday)
        //   Charge_start_time   = 204132  (20:41:32 Zurich)
        //   aprice              = 0.1466
        //   final_charge        = 0.1585
        //
        // Tariff 2335 sundayAndHoliday config is identical to weekday, so:
        //   0.10 + (117/200) * 0.10 = 0.1585 = expectedAprice*(1+vat).
        $duration = 132;
        $expectedAprice = 0.1466;
        $this->insertOfflineATariffProfile($I, 2335, $this->graceDropProRataSequences(200));
        $this->insertInaNumber($I, '0900111024', 2335);

        $line = $this->makeLine('0900111024', $duration, '2025-06-22 20:41:32');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta($expectedAprice*(1+$this->vat), $price, $this->epsilon,
            'Plugin must reproduce prod aprice 0.1466 (final_charge 0.1585) for a 132s Sunday call to 0900111024');
    }

    public function testProdReference_call_76s_tariff_2335(AcceptanceTester $I)
    {
        // Prod CDR:
        //   uf.Called_Number    = "900111024" (subscriber_number "0900111024" after conversion)
        //   uf.Chargeable_units = "000076"     (76 seconds)
        //   uf.Tariff_Class     = "002335"
        //   Charging_Date       = 250623  (2025-06-23, Monday -> weekday)
        //   Charge_start_time   = 160543  (16:05:43 Zurich)
        //   aprice              = 0.1207
        //   final_charge        = 0.1305
        //
        // 0.10 + (61/200) * 0.10 = 0.1305 = expectedAprice*(1+vat).
        $duration = 76;
        $expectedAprice = 0.1207;
        $this->insertOfflineATariffProfile($I, 2335, $this->graceDropProRataSequences(200));
        $this->insertInaNumber($I, '0900111024', 2335);

        $line = $this->makeLine('0900111024', $duration, '2025-06-23 16:05:43');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta($expectedAprice*(1+$this->vat), $price, $this->epsilon,
            'Plugin must reproduce prod aprice 0.1207 (final_charge 0.1305) for a 76s call to 0900111024');
    }

    public function testProdReference_call_3483s_tariff_2029_freeRoute(AcceptanceTester $I)
    {
        // Prod CDR (ObjectId 686c88a4d07b2f92ed0f0d62):
        //   uf.Called_Number    = "900949392" -> subscriber_number "0900949392"
        //   uf.Chargeable_units = "003483" (3483 seconds)
        //   uf.Tariff_Class     = "002029"
        //   Charging_Date       = 250707  (2025-07-07, Monday -> weekday)
        //   aprice              = 0
        //   final_charge        = 0
        //
        // Tariff 2029 is a free PRO_RATA rate=0 / time=60 (INF capacity) on
        //   all days, so any duration prices to exactly 0.
        $duration = 3483;
        $this->insertOfflineATariffProfile($I, 2029, [
            ['sequence' => 1, 'rate' => 0, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT'],
        ]);
        $this->insertInaNumber($I, '0900949392', 2029);

        $line = $this->makeLine('0900949392', $duration, '2025-07-07 16:34:44');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta(0.0, $price, $this->epsilon,
            'Plugin must reproduce prod aprice 0 / final_charge 0 for a free-route tariff 2029 call');
    }

    public function testProdReference_call_1696s_tariff_2336(AcceptanceTester $I)
    {
        // Prod CDR (ObjectId 68f1502abcf943dcae07d192):
        //   uf.Called_Number    = "900111006" -> "0900111006"
        //   uf.Chargeable_units = "001696" (1696 seconds)
        //   uf.Tariff_Class     = "002336"
        //   Charging_Date       = 251016  (2025-10-16, Thursday -> weekday)
        //   aprice              = 1.13
        //   final_charge        = 1.22153
        //
        // Tariff 2336 = grace/drop/pro-rata with seq3 time=150. For 1696s:
        //   0 + 0.10 + (1681/150)*0.10 = 1.22067 ~= expectedAprice*(1+vat).
        $duration = 1696;
        $expectedAprice = 1.13;
        $this->insertOfflineATariffProfile($I, 2336, $this->graceDropProRataSequences(150));
        $this->insertInaNumber($I, '0900111006', 2336);

        $line = $this->makeLine('0900111006', $duration, '2025-10-16 20:48:21');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta($expectedAprice*(1+$this->vat), $price, $this->epsilon,
            'Plugin must reproduce prod aprice 1.13 (final_charge 1.22153) for a 1696s call to 0900111006');
    }

    public function testProdReference_call_1741s_tariff_2339(AcceptanceTester $I)
    {
        // Prod CDR (ObjectId 69ce351c11428a679800ae62):
        //   uf.Called_Number    = "900111009" -> "0900111009"
        //   uf.Chargeable_units = "001741" (1741 seconds)
        //   uf.Tariff_Class     = "002339"
        //   Charging_Date       = 260402  (2026-04-02, Thursday -> weekday)
        //   aprice              = 1.423
        //   final_charge        = 1.538263
        //
        // Tariff 2339 = grace/drop/pro-rata with seq3 time=120.
        //   0 + 0.10 + (1726/120)*0.10 = 1.53833 ~= expectedAprice*(1+vat).
        $duration = 1741;
        $expectedAprice = 1.423;
        $this->insertOfflineATariffProfile($I, 2339, $this->graceDropProRataSequences(120));
        $this->insertInaNumber($I, '0900111009', 2339);

        $line = $this->makeLine('0900111009', $duration, '2026-04-02 09:51:19');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta($expectedAprice*(1+$this->vat), $price, $this->epsilon,
            'Plugin must reproduce prod aprice 1.423 (final_charge 1.538263) for a 1741s call to 0900111009');
    }

    public function testProdReference_call_1650s_tariff_2346(AcceptanceTester $I)
    {
        // Prod CDR (ObjectId 6972405e2bdbb6ad620b4c42):
        //   uf.Called_Number    = "900111017" -> "0900111017"
        //   uf.Chargeable_units = "001650" (1650 seconds)
        //   uf.Tariff_Class     = "002346"
        //   Charging_Date       = 260122  (2026-01-22, Thursday -> weekday)
        //   aprice              = 2.11
        //   final_charge        = 2.28091
        //
        // Tariff 2346 = grace/drop/pro-rata with seq3 time=75.
        //   0 + 0.10 + (1635/75)*0.10 = 2.28 ~= expectedAprice*(1+vat).
        $duration = 1650;
        $expectedAprice = 2.11;
        $this->insertOfflineATariffProfile($I, 2346, $this->graceDropProRataSequences(75));
        $this->insertInaNumber($I, '0900111017', 2346);

        $line = $this->makeLine('0900111017', $duration, '2026-01-22 15:03:47');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta($expectedAprice*(1+$this->vat), $price, $this->epsilon,
            'Plugin must reproduce prod aprice 2.11 (final_charge 2.28091) for a 1650s call to 0900111017');
    }

    public function testProdReference_call_2677s_tariff_2348_sunday(AcceptanceTester $I)
    {
        // Prod CDR (ObjectId 68580a281a9e1ca9840df352):
        //   uf.Called_Number    = "900111020" -> "0900111020"
        //   uf.Chargeable_units = "002677" (2677 seconds)
        //   uf.Tariff_Class     = "002348"
        //   Charging_Date       = 250622  (2025-06-22, Sunday -> sundayAndHoliday)
        //   aprice              = 8.301
        //   final_charge        = 8.973381
        //
        // Tariff 2348 = grace/drop/pro-rata with seq3 time=30 (same config on
        //   all day-types). 0 + 0.10 + (2662/30)*0.10 = 8.97333.
        $duration = 2677;
        $expectedAprice = 8.301;
        $this->insertOfflineATariffProfile($I, 2348, $this->graceDropProRataSequences(30));
        $this->insertInaNumber($I, '0900111020', 2348);

        $line = $this->makeLine('0900111020', $duration, '2025-06-22 14:22:44');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta($expectedAprice*(1+$this->vat), $price, $this->epsilon,
            'Plugin must reproduce prod aprice 8.301 (final_charge 8.973381) for a 2677s Sunday call to 0900111020');
    }

    public function testProdReference_call_1722s_tariff_2349(AcceptanceTester $I)
    {
        // Prod CDR (ObjectId 68777282c3af7e6cea048953):
        //   uf.Called_Number    = "900111022" -> "0900111022"
        //   uf.Chargeable_units = "001722" (1722 seconds)
        //   uf.Tariff_Class     = "002349"
        //   Charging_Date       = 250716  (2025-07-16, Wednesday -> weekday)
        //   aprice              = 4.041
        //   final_charge        = 4.368321
        //
        // Tariff 2349 = grace/drop/pro-rata with seq3 time=40.
        //   0 + 0.10 + (1707/40)*0.10 = 4.3675 ~= expectedAprice*(1+vat).
        $duration = 1722;
        $expectedAprice = 4.041;
        $this->insertOfflineATariffProfile($I, 2349, $this->graceDropProRataSequences(40));
        $this->insertInaNumber($I, '0900111022', 2349);

        $line = $this->makeLine('0900111022', $duration, '2025-07-16 10:31:35');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta($expectedAprice*(1+$this->vat), $price, $this->epsilon,
            'Plugin must reproduce prod aprice 4.041 (final_charge 4.368321) for a 1722s call to 0900111022');
    }

    public function testProdReference_call_1849s_tariff_2491_shortcode_1818(AcceptanceTester $I)
    {
        // Prod CDR (ObjectId 68335052ceb99c48e6002a94):
        //   uf.Called_Number    = "1818" (short-code; ina_number_prefixes regex
        //                                 matches /^18[0-9][0-9]$/ with no conversion)
        //   uf.Chargeable_units = "001849" (1849 seconds)
        //   uf.Tariff_Class     = "002491"
        //   Charging_Date       = 250525  (2025-05-25, Sunday -> sundayAndHoliday)
        //   aprice              = 58.908
        //   final_charge        = 63.679548
        //
        // Tariff 2491 (same config on every day):
        //   seq 1 FIX_PRICE rate=199, time=1, ruleDuration=1     -> 1.99 drop, no left decrement
        //   seq 2 NOT_PRO_RATA rate=199, time=60, ruleDuration=1 -> capacity 60s; ceil(1849/60)=31>=1 -> 1*1.99=1.99; left-=60 -> 1789
        //   seq 3 NOT_PRO_RATA rate=199, time=60, ruleDuration=0 -> INF; ceil(1789/60)=30; 30*1.99=59.70
        //   Total = 1.99 + 1.99 + 59.70 = 63.68 ~= expectedAprice*(1+vat).
        $duration = 1849;
        $expectedAprice = 58.908;
        $this->insertOfflineATariffProfile($I, 2491, [
            ['sequence' => 1, 'rate' => 199, 'time' => 1,  'ruleType' => 'FIX_PRICE',    'ruleDuration' => 1, 'sign' => 'DEBIT'],
            ['sequence' => 2, 'rate' => 199, 'time' => 60, 'ruleType' => 'NOT_PRO_RATA', 'ruleDuration' => 1, 'sign' => 'DEBIT'],
            ['sequence' => 3, 'rate' => 199, 'time' => 60, 'ruleType' => 'NOT_PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT'],
        ]);
        $this->insertInaNumber($I, '1818', 2491);

        $line = $this->makeLine('1818', $duration, '2025-05-25 18:03:09');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta($expectedAprice*(1+$this->vat), $price, $this->epsilon,
            'Plugin must reproduce prod aprice 58.908 (final_charge 63.679548) for a 1849s short-code 1818 call');
    }

    public function testProdReference_call_3649s_tariff_2514(AcceptanceTester $I)
    {
        // Prod CDR (ObjectId 68309cea5f698d75700398e2):
        //   uf.Called_Number    = "900111032" -> "0900111032"
        //   uf.Chargeable_units = "003649" (3649 seconds)
        //   uf.Tariff_Class     = "002514"
        //   Charging_Date       = 250523  (2025-05-23, Friday -> weekday)
        //   aprice              = 6.816
        //   final_charge        = 7.368096
        //
        // Tariff 2514 = grace/drop/pro-rata with seq3 time=50.
        //   0 + 0.10 + (3634/50)*0.10 = 7.368 ~= expectedAprice*(1+vat).
        $duration = 3649;
        $expectedAprice = 6.816;
        $this->insertOfflineATariffProfile($I, 2514, $this->graceDropProRataSequences(50));
        $this->insertInaNumber($I, '0900111032', 2514);

        $line = $this->makeLine('0900111032', $duration, '2025-05-23 15:50:09');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta($expectedAprice*(1+$this->vat), $price, $this->epsilon,
            'Plugin must reproduce prod aprice 6.816 (final_charge 7.368096) for a 3649s call to 0900111032');
    }

    public function testProdReference_call_2595s_tariff_2515_sunday(AcceptanceTester $I)
    {
        // Prod CDR (ObjectId 6832eb8a77fbfb7d9b0731e2):
        //   uf.Called_Number    = "900111033" -> "0900111033"
        //   uf.Chargeable_units = "002595" (2595 seconds)
        //   uf.Tariff_Class     = "002515"
        //   Charging_Date       = 250525  (2025-05-25, Sunday -> sundayAndHoliday)
        //   aprice              = 6.723
        //   final_charge        = 7.267563
        //
        // Tariff 2515 = grace/drop/pro-rata with seq3 time=36 (identical
        //   weekday/saturday/sundayAndHoliday).
        //   0 + 0.10 + (2580/36)*0.10 = 7.26667 ~= expectedAprice*(1+vat).
        $duration = 2595;
        $expectedAprice = 6.723;
        $this->insertOfflineATariffProfile($I, 2515, $this->graceDropProRataSequences(36));
        $this->insertInaNumber($I, '0900111033', 2515);

        $line = $this->makeLine('0900111033', $duration, '2025-05-25 10:28:14');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta($expectedAprice*(1+$this->vat), $price, $this->epsilon,
            'Plugin must reproduce prod aprice 6.723 (final_charge 7.267563) for a 2595s Sunday call to 0900111033');
    }

    public function testProdReference_call_1963s_tariff_2644_shortcode_1811(AcceptanceTester $I)
    {
        // Prod CDR (ObjectId 68f194232c1b3c7d78050107):
        //   uf.Called_Number    = "1811" (short-code; ina_number_prefixes matches /^18[0-9][0-9]$/)
        //   uf.Chargeable_units = "001963" (1963 seconds)
        //   uf.Tariff_Class     = "002644"
        //   Charging_Date       = 251016  (2025-10-16, Thursday -> weekday)
        //   aprice              = 60.749
        //   final_charge        = 65.669669
        //
        // Tariff 2644 single seq NOT_PRO_RATA rate=199, time=60, ruleDuration=0
        //   (INF capacity, same for every day). ceil(1963/60)=33; 33*1.99 = 65.67
        //   ~= expectedAprice*(1+vat).
        $duration = 1963;
        $expectedAprice = 60.749;
        $this->insertOfflineATariffProfile($I, 2644, [
            ['sequence' => 1, 'rate' => 199, 'time' => 60, 'ruleType' => 'NOT_PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT'],
        ]);
        $this->insertInaNumber($I, '1811', 2644);

        $line = $this->makeLine('1811', $duration, '2025-10-16 20:59:51');
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta($expectedAprice*(1+$this->vat), $price, $this->epsilon,
            'Plugin must reproduce prod aprice 60.749 (final_charge 65.669669) for a 1963s short-code 1811 call');
    }

    
    // not found avidance should happend for now not supported
    // public function testOfflineA_fixPrice_firesOnZeroDuration(AcceptanceTester $I)
    // {
    //     $this->insertOfflineATariffProfile($I, 4004, [
    //         ['sequence' => 1, 'rate' => 15, 'time' => 0, 'ruleType' => 'FIX_PRICE', 'ruleDuration' => 1, 'sign' => 'DEBIT'],
    //     ]);
    //     $this->insertInaNumber($I, '0900111000', 4004);

    //     $line = $this->makeLine('0900111000', 0, '2026-05-13 10:00:00');
    //     $price = $this->priceLine($line);

    //     $I->assertEqualsWithDelta(0.15, $price, $this->epsilon,
    //         'FIX_PRICE rule must bill the drop charge even for zero-duration calls');
    // }

       
    

    /* ============================================================
     *  call_offset CDR-split tests (BRCD-5294)
     * ============================================================ */

    public function testCallOffset_online_firstCdrFullCharge(AcceptanceTester $I)
    {
        // First CDR of a split call: call_offset = 0 -> full baseCharge + full startInterval pool.
        $this->insertOnlineTariffProfileSingleSequence($I, 10010, [
            'chargeRate' => 12, 'baseCharge' => 10, 'startInterval' => 30,
        ]);
        $this->insertInaNumber($I, '0844200000', 10010);

        // 60s segment; only 30s past the startInterval pool counts.
        $line = $this->makeLine('0844200000', 60, '2026-05-13 10:00:00', 0);
        $price = $this->priceLine($line);

        // 0.10 + 0.12/60 * (60-30) = 0.10 + 0.06 = 0.16
        $I->assertEqualsWithDelta(0.16, $price, $this->epsilon,
            'First CDR of a split call must apply full baseCharge and full startInterval');
    }

    public function testCallOffset_online_secondCdrNoBaseChargeNoStartInterval(AcceptanceTester $I)
    {
        // Second CDR of the same call: call_offset = 60s (i.e. the first segment already
        // consumed 60s, more than the 30s startInterval). No baseCharge, no startInterval rebate.
        $this->insertOnlineTariffProfileSingleSequence($I, 10011, [
            'chargeRate' => 12, 'baseCharge' => 10, 'startInterval' => 30,
        ]);
        $this->insertInaNumber($I, '0844200000', 10011);

        // 120-second second segment, 60s already elapsed before this CDR.
        $line = $this->makeLine('0844200000', 120, '2026-05-13 10:01:00', 60);
        $price = $this->priceLine($line);

        // 0 (no baseCharge) + 0.12/60 * max(120 - 0, 0) = 0.24
        $I->assertEqualsWithDelta(0.24, $price, $this->epsilon,
            'Second CDR of a split call must skip baseCharge and exhausted startInterval');
    }

    public function testCallOffset_online_secondCdrInsideRemainingStartInterval(AcceptanceTester $I)
    {
        // Edge case: startInterval=30, callDurationBefore=10 -> 20s still free on this segment.
        $this->insertOnlineTariffProfileSingleSequence($I, 10012, [
            'chargeRate' => 12, 'baseCharge' => 10, 'startInterval' => 30,
        ]);
        $this->insertInaNumber($I, '0844200000', 10012);

        $line = $this->makeLine('0844200000', 60, '2026-05-13 10:00:30', 10);
        $price = $this->priceLine($line);

        // 0 + 0.12/60 * (60 - 20) = 0.08
        $I->assertEqualsWithDelta(0.08, $price, $this->epsilon,
            'Second CDR must consume only the leftover startInterval pool, not the full one');
    }

    public function testCallOffset_offlineA_skipsAlreadyConsumedSequence(AcceptanceTester $I)
    {
        // Two sequences:
        //   seq 1: PRO_RATA rate=10, time=60, ruleDuration=2  -> capacity = 60s * 2 = 120s
        //   seq 2: PRO_RATA rate=5,  time=60, ruleDuration=0  -> INF capacity
        // call_offset=120s means the entire seq 1 was consumed by earlier CDRs; this CDR
        // must price its 60s purely from seq 2: 60/60 * 5 / 100 = 0.05.
        $this->insertOfflineATariffProfile($I, 4010, [
            ['sequence' => 1, 'rate' => 10, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 2, 'sign' => 'DEBIT'],
            ['sequence' => 2, 'rate' => 5,  'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT'],
        ]);
        $this->insertInaNumber($I, '0900222000', 4010);

        $line = $this->makeLine('0900222000', 60, '2026-05-13 10:02:00', 120);
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta(0.05, $price, $this->epsilon,
            'Offline-a multi-sequence pricing must skip sequences fully consumed by previous CDRs');
    }

    public function testCallOffset_offlineA_partialConsumeOfFirstSequence(AcceptanceTester $I)
    {
        // seq 1: PRO_RATA rate=10, time=60, ruleDuration=2 -> 120s capacity
        // seq 2: PRO_RATA rate=5,  time=60, ruleDuration=0
        // call_offset = 90s (1.5 minutes of seq 1 used). This CDR is 60s long:
        //   - 30s remaining in seq 1 -> 30/60 * 10 / 100 = 0.05
        //   - 30s overflow into seq 2 -> 30/60 * 5 / 100 = 0.025
        // Total expected = 0.075
        $this->insertOfflineATariffProfile($I, 4011, [
            ['sequence' => 1, 'rate' => 10, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 2, 'sign' => 'DEBIT'],
            ['sequence' => 2, 'rate' => 5,  'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT'],
        ]);
        $this->insertInaNumber($I, '0900222000', 4011);

        $line = $this->makeLine('0900222000', 60, '2026-05-13 10:01:30', 90);
        $price = $this->priceLine($line);

        $I->assertEqualsWithDelta(0.075, $price, $this->epsilon,
            'Offline-a pricing must cap leftover capacity in the partially-consumed sequence and roll over into the next');
    }

    public function testProdReference_call_32s_tariff_1_splitInTwo(AcceptanceTester $I)
    {
        // Same scenario as testProdReference_call_32s_tariff_1, but split
        //   into a 12s + 20s pair. Tariff 1 is INF-capacity PRO_RATA so the
        //   sum is exactly the unsplit price (0.2666 ~= 0.2466 * 1.081).
        $expectedAprice = 0.2466;
        $this->insertOfflineATariffProfile($I, 1, [
            ['sequence' => 1, 'rate' => 50, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0, 'sign' => 'DEBIT'],
        ]);
        $this->insertInaNumber($I, '0900144033', 1);

        $seg1 = $this->makeLine('0900144033', 12, '2025-06-23 18:29:51', 0);
        $seg2 = $this->makeLine('0900144033', 20, '2025-06-23 18:30:03', 12);
        $total = $this->priceLine($seg1) + $this->priceLine($seg2);

        $I->assertEqualsWithDelta($expectedAprice*(1+$this->vat), $total, $this->epsilon,
            'Sum of split-CDR prices must equal the unsplit prod price for a 32s tariff-1 call');
    }

    public function testProdReference_call_4109s_tariff_2335_splitInThree(AcceptanceTester $I)
    {
        // Same scenario as testProdReference_call_4109s_tariff_2335 (final
        //   2.147), but split into 50s + 50s + 4009s. Validates that:
        //     - seq 1 grace (15s) is consumed only once;
        //     - seq 2 FIX_PRICE drop (0.10) fires exactly once on seg 1;
        //     - the rest of the duration prices proportionally in seq 3.
        $expectedAprice = 1.9861;
        $this->insertOfflineATariffProfile($I, 2335, $this->graceDropProRataSequences(200));
        $this->insertInaNumber($I, '0900111024', 2335);

        $seg1 = $this->makeLine('0900111024', 50,   '2025-06-23 14:38:59', 0);
        $seg2 = $this->makeLine('0900111024', 50,   '2025-06-23 14:39:49', 50);
        $seg3 = $this->makeLine('0900111024', 4009, '2025-06-23 14:40:39', 100);
        $total = $this->priceLine($seg1) + $this->priceLine($seg2) + $this->priceLine($seg3);

        $I->assertEqualsWithDelta($expectedAprice*(1+$this->vat), $total, $this->epsilon,
            'Sum of 3-way split prices must equal unsplit prod price for a 4109s tariff-2335 call (drop charge fires once, grace consumed once)');
    }

    public function testProdReference_call_1510s_tariff_2640_splitAcrossSeqBoundary(AcceptanceTester $I)
    {
        // Same scenario as testProdReference_call_1510s_tariff_2640 (final
        //   15 = 13.876 * 1.081), but split into 400s + 1110s. The split
        //   point falls *inside* seq 1 (cap 600s = 60s * ruleDuration 10),
        //   so seg 1 prices only part of seq 1 and seg 2 finishes seq 1
        //   and overflows into the free seq 2.
        $expectedAprice = 13.876;
        $this->insertOfflineATariffProfile($I, 2640, [
            ['sequence' => 1, 'rate' => 150, 'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 10, 'sign' => 'DEBIT'],
            ['sequence' => 2, 'rate' => 0,   'time' => 60, 'ruleType' => 'PRO_RATA', 'ruleDuration' => 0,  'sign' => 'DEBIT'],
        ]);
        $this->insertInaNumber($I, '0900530520', 2640);

        $seg1 = $this->makeLine('0900530520', 400,  '2025-05-19 10:58:12', 0);
        $seg2 = $this->makeLine('0900530520', 1110, '2025-05-19 11:04:52', 400);
        $total = $this->priceLine($seg1) + $this->priceLine($seg2);

        $I->assertEqualsWithDelta($expectedAprice*(1+$this->vat), $total, $this->epsilon,
            'Sum of split prices must equal unsplit prod price for a 1510s tariff-2640 call when the split falls inside seq 1');
    }

}
